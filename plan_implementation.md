# Database-driven php accounting app with dual environment

You want a single entry point (main_entry.php), running on MariaDB locally and MySQL on Hostinger, with the entire web application stored and managed inside the database. Below is a clean, secure architecture, schema, and drop-in code to serve pages, forms, lists, and actions from DB metadata—no external libraries, dark UI, and vanilla JS.

---

## Architecture overview

- Single entry: main_entry.php
- Dual environment PDO (local MariaDB, Hostinger MySQL) via config.php + db.php
- App lives in DB: pages, routes, components, list views, forms, and actions are defined in metadata tables
- Render engine: safe, server-side template rendering (no eval of PHP from DB), supports:
  - Lists: run SELECT and render rows
  - Forms: generate inputs, run INSERT/UPDATE/DELETE with prepared statements
  - Actions: bulk updates via parameterized SQL
- Dark UI: small CSS, zero external assets
- Accounting domain: parties, items, invoices, invoice_items with normalized schema

---

## Database schema for app and accounting

Run this on both environments (local and Hostinger). It sets up the app metadata plus the accounting tables.

```sql
-- App metadata: pages and components
CREATE TABLE app_pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(100) UNIQUE NOT NULL,     -- route key, e.g. 'parties', 'items', 'invoices'
  title VARCHAR(120) NOT NULL,
  page_type ENUM('list','form','workspace') NOT NULL,
  template TEXT NOT NULL,                -- HTML with {{placeholders}}
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE app_components (
  id INT AUTO_INCREMENT PRIMARY KEY,
  page_slug VARCHAR(100) NOT NULL,
  comp_type ENUM('list','form','action') NOT NULL,
  name VARCHAR(100) NOT NULL,
  sql_text TEXT NOT NULL,                -- parameterized SQL with :named params for forms/actions
  meta_json TEXT NOT NULL,               -- JSON config (columns, inputs, mapping)
  ord INT DEFAULT 0,
  FOREIGN KEY (page_slug) REFERENCES app_pages(slug) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Accounting: parties
CREATE TABLE parties (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  gstin VARCHAR(15) UNIQUE,
  party_type ENUM('customer','supplier','both') NOT NULL,
  city VARCHAR(120),
  state VARCHAR(50),
  email VARCHAR(120),
  phone VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Accounting: items
CREATE TABLE items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  hsn_code CHAR(8) NOT NULL,
  unit VARCHAR(20) NOT NULL,
  default_rate DECIMAL(10,2),
  cgst_rate DECIMAL(5,2) DEFAULT 0.00,
  sgst_rate DECIMAL(5,2) DEFAULT 0.00,
  igst_rate DECIMAL(5,2) DEFAULT 0.00,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Accounting: invoices
CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  party_id INT NOT NULL,
  invoice_no VARCHAR(50) NOT NULL,
  invoice_date DATE NOT NULL,
  inv_type ENUM('sale','purchase','credit_note','debit_note') NOT NULL,
  place_of_supply VARCHAR(50),
  reverse_charge TINYINT(1) DEFAULT 0,
  status ENUM('draft','final','cancelled') DEFAULT 'draft',
  itc_eligible TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (party_id) REFERENCES parties(id)
) ENGINE=InnoDB;

-- Accounting: invoice_items
CREATE TABLE invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  item_id INT NOT NULL,
  description VARCHAR(255),
  quantity DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  rate DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  taxable_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00, -- app-computed
  cgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,    -- app-computed
  sgst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,    -- app-computed
  igst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,    -- app-computed
  line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,     -- app-computed
  itc_eligible TINYINT(1) NOT NULL DEFAULT 1,
  is_prepackaged_labelled TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB;

CREATE INDEX idx_invoices_party ON invoices(party_id);
CREATE INDEX idx_invoice_items_invoice ON invoice_items(invoice_id);
CREATE INDEX idx_invoice_items_item ON invoice_items(item_id);
```

---

## Dual environment config and db helpers

Create config.php and db.php. No third-party libs, only PDO.

```php
<?php // config.php
$ENV = getenv('APP_ENV') ?: 'local'; // 'local' or 'hostinger'

$config = [
  'local' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'pass' => 'your_local_password',
    'db'   => 'gst_accounting',
    'charset' => 'utf8mb4'
  ],
  'hostinger' => [
    'host' => 'srv684.hstgr.io',
    'port' => 3306,
    'user' => 'u184420243_gst',
    'pass' => 'Raam2:=195',
    'db'   => 'u184420243_jayanti_enterp',
    'charset' => 'utf8mb4'
  ]
];
```

```php
<?php // db.php
require __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    global $ENV, $config;
    $c = $config[$ENV];

    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['db']};charset={$c['charset']}";
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function fetchAll(string $sql, array $params = []): array { return q($sql,$params)->fetchAll(); }
function fetchOne(string $sql, array $params = []): ?array { $r=q($sql,$params)->fetch(); return $r?:null; }
```

---

## Main entry with db-rendered pages and dark ui

Save as main_entry.php. It loads page definitions from app_pages/app_components and renders everything from the database.

```php
<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// ---------- utils ----------
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function j($v): string { return json_encode($v, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE); }
function template_render(string $tpl, array $ctx): string {
    // Replace {{key}} with escaped values; {{raw:key}} for unescaped
    $out = $tpl;
    foreach ($ctx as $k=>$v) {
        $out = str_replace('{{'.$k.'}}', h((string)$v), $out);
        $out = str_replace('{{raw:'.$k.'}}', (string)$v, $out);
    }
    return $out;
}

// ---------- routing ----------
$slug = $_GET['p'] ?? 'home';

// ---------- load page ----------
$page = fetchOne("SELECT * FROM app_pages WHERE slug=?", [$slug]);
if (!$page) {
    // fallback basic home
    $basic = '<div class="card"><h3>Welcome</h3><p class="muted">Use navigation to open pages.</p></div>';
    $page = ['slug'=>'home','title'=>'Home','page_type'=>'list','template'=>$basic];
}
$comps = fetchAll("SELECT * FROM app_components WHERE page_slug=? ORDER BY ord, id", [$page['slug']]);

// ---------- handle actions (POST) ----------
$notice = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    foreach ($comps as $c) {
        if ($c['comp_type'] === 'action' && $c['name'] === $act) {
            $meta = json_decode($c['meta_json'], true) ?: [];
            $params = [];
            foreach ($meta['params'] ?? [] as $pname) {
                $params[$pname] = $_POST[$pname] ?? null;
            }
            // Named params binding (convert to positional)
            $sql = $c['sql_text'];
            $order = [];
            foreach ($params as $k=>$v) {
                $sql = preg_replace('/:'.preg_quote($k,'/').'\b/', '?', $sql, 1);
                $order[] = $v;
            }
            q($sql, $order);
            $notice = $meta['success'] ?? 'Action completed.';
        }
    }
}

// ---------- render head ----------
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title><?=h($page['title'])?></title>
<style>
:root{color-scheme:dark}
body{margin:0;background:#0e0f13;color:#e5e7eb;font-family:system-ui,Segoe UI,Arial}
header{background:#111827;padding:12px 16px;border-bottom:1px solid #1f2937;display:flex;gap:12px;align-items:center}
a{color:#60a5fa;text-decoration:none}a:hover{text-decoration:underline}
.container{display:flex;min-height:calc(100vh - 52px)}
.sidebar{width:260px;background:#0b0c10;border-right:1px solid #1f2937;padding:12px;overflow:auto}
.main{flex:1;padding:16px}
.card{border:1px solid #1f2937;background:#0b0c10;padding:12px;border-radius:6px;margin-bottom:12px}
.btn{background:#1f2937;border:1px solid #374151;color:#e5e7eb;padding:6px 10px;border-radius:4px;cursor:pointer}
.btn-danger{background:#7f1d1d;border-color:#b91c1c}
input,select,textarea{background:#0b0c10;color:#e5e7eb;border:1px solid #374151;border-radius:4px;padding:6px}
table{border-collapse:collapse;width:100%;font-size:14px}
th,td{border:1px solid #1f2937;padding:6px 8px;vertical-align:top}
th{background:#111827}
.grid{display:grid;grid-template-columns:220px 1fr;gap:10px;max-width:900px}
.row{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0}
.muted{color:#94a3b8}
.success{color:#86efac}
.danger{color:#fca5a5}
.mono{font-family:ui-monospace,Menlo,monospace}
</style>
</head><body>
<header>
  <strong><?=h($page['title'])?></strong>
  <a class="btn" href="?p=home">Home</a>
  <a class="btn" href="?p=parties">Parties</a>
  <a class="btn" href="?p=items">Items</a>
  <a class="btn" href="?p=invoices">Invoices</a>
</header>
<div class="container">
  <div class="sidebar">
    <div class="card"><strong>Pages</strong>
      <ul style="list-style:none;padding-left:0">
        <?php foreach (fetchAll("SELECT slug,title FROM app_pages ORDER BY slug") as $pg): ?>
          <li><a href="?p=<?=h($pg['slug'])?>"><?=h($pg['title'])?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="card"><strong>Quick search</strong>
      <form method="get" action="?p=search">
        <input type="hidden" name="p" value="search">
        <input name="q" placeholder="party/item/invoice">
        <button class="btn" type="submit">Go</button>
      </form>
    </div>
  </div>
  <div class="main">
    <?php if ($notice): ?><div class="card success"><?=h($notice)?></div><?php endif; ?>

    <?php
    // ---------- render template ----------
    echo template_render($page['template'], []);

    // ---------- render components ----------
    foreach ($comps as $c) {
        $meta = json_decode($c['meta_json'], true) ?: [];
        if ($c['comp_type'] === 'list') {
            // lists: optional params from GET
            $params = [];
            foreach ($meta['params'] ?? [] as $pname) {
                $params[$pname] = $_GET[$pname] ?? null;
            }
            $sql = $c['sql_text']; $order=[];
            foreach ($params as $k=>$v) { $sql = preg_replace('/:'.preg_quote($k,'/').'\b/', '?', $sql, 1); $order[]=$v; }
            $rows = fetchAll($sql, $order);
            echo '<div class="card"><table><tr>';
            foreach (($meta['columns'] ?? array_keys($rows[0] ?? [])) as $col) echo '<th>'.h($col).'</th>';
            echo '</tr>';
            foreach ($rows as $r) {
                echo '<tr>';
                foreach (($meta['columns'] ?? array_keys($r)) as $col) {
                    $val = $r[$col] ?? '';
                    // FK link decoration
                    if (!empty($meta['fk_links'][$col])) {
                        $lk = $meta['fk_links'][$col]; // ['to'=>'invoices','param'=>'id', 'map_col'=>'invoice_id']
                        $href = '?p='.urlencode($lk['to']).'&'.$lk['param'].'='.urlencode((string)$val);
                        echo '<td>'.h((string)$val).' <a href="'.$href.'" target="_blank">↗</a></td>';
                    } else {
                        echo '<td>'.h((string)$val).'</td>';
                    }
                }
                echo '</tr>';
            }
            echo '</table></div>';
        } elseif ($c['comp_type'] === 'form') {
            // forms: render inputs from meta, submit to action with same name
            echo '<div class="card"><form method="post" class="grid">';
            echo '<input type="hidden" name="act" value="'.h($c['name']).'">';
            foreach ($meta['inputs'] ?? [] as $inp) {
                echo '<label>'.h($inp['label']).'</label>';
                $val = isset($_GET[$inp['name']]) ? h($_GET[$inp['name']]) : '';
                $type = $inp['type'] ?? 'text';
                $step = ($type === 'number' && isset($inp['step'])) ? ' step="'.h($inp['step']).'"' : '';
                echo '<input name="'.h($inp['name']).'" type="'.h($type).'" value="'.$val.'"'.$step.'>';
            }
            echo '<div></div><button class="btn" type="submit">'.h($meta['submit'] ?? 'Submit').'</button></form></div>';
        } elseif ($c['comp_type'] === 'action') {
            // actions: show as inline control
            echo '<div class="card"><form method="post" class="row">';
            echo '<input type="hidden" name="act" value="'.h($c['name']).'">';
            foreach ($meta['params'] ?? [] as $pname) {
                echo '<input name="'.h($pname).'" placeholder="'.h($pname).'">';
            }
            echo '<button class="btn" type="submit">'.h($meta['label'] ?? $c['name']).'</button></form></div>';
        }
    }
    ?>
  </div>
</div>
</body></html>
```

---

## Bootstrap: pages and components inside the database

Insert minimal pages for Parties, Items, and Invoices with lists, forms, and quick actions. You can extend later.

```sql
-- Home page
INSERT INTO app_pages (slug, title, page_type, template)
VALUES ('home','Home','list','<div class="card"><h3>Offline Accounting</h3><p class="muted">Use the sidebar.</p></div>');

-- Parties page
INSERT INTO app_pages (slug, title, page_type, template)
VALUES ('parties','Parties','list','<div class="card"><h3>Parties</h3><p class="muted">Add and browse parties.</p></div>');

INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES
('parties','list','parties_list',
 'SELECT id,name,gstin,party_type,city,state FROM parties ORDER BY name',
 '{"columns":["id","name","gstin","party_type","city","state"],
   "fk_links":{"id":{"to":"invoices","param":"party_id"}}}', 10),
('parties','form','party_create',
 'INSERT INTO parties(name,gstin,party_type,city,state,email,phone,created_at) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)',
 '{"inputs":[
    {"name":"name","label":"Name"},
    {"name":"gstin","label":"GSTIN"},
    {"name":"party_type","label":"Type"},
    {"name":"city","label":"City"},
    {"name":"state","label":"State"},
    {"name":"email","label":"Email"},
    {"name":"phone","label":"Phone"}
   ],
   "submit":"Add party",
   "params":["name","gstin","party_type","city","state","email","phone"],
   "success":"Party added."}', 20);

-- Items page
INSERT INTO app_pages (slug, title, page_type, template)
VALUES ('items','Items','list','<div class="card"><h3>Items</h3><p class="muted">Manage catalog.</p></div>');

INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES
('items','list','items_list',
 'SELECT id,name,hsn_code,unit,default_rate,(cgst_rate+sgst_rate+igst_rate) AS gst_total FROM items ORDER BY name',
 '{"columns":["id","name","hsn_code","unit","default_rate","gst_total"]}', 10),
('items','form','item_create',
 'INSERT INTO items(name,hsn_code,unit,default_rate,cgst_rate,sgst_rate,igst_rate,created_at) VALUES (?,?,?,?,?,?,?,CURRENT_TIMESTAMP)',
 '{"inputs":[
    {"name":"name","label":"Name"},
    {"name":"hsn_code","label":"HSN"},
    {"name":"unit","label":"Unit"},
    {"name":"default_rate","label":"Default rate","type":"number","step":"0.01"},
    {"name":"cgst_rate","label":"CGST %","type":"number","step":"0.01"},
    {"name":"sgst_rate","label":"SGST %","type":"number","step":"0.01"},
    {"name":"igst_rate","label":"IGST %","type":"number","step":"0.01"}
   ],
   "submit":"Add item",
   "params":["name","hsn_code","unit","default_rate","cgst_rate","sgst_rate","igst_rate"],
   "success":"Item added."}', 20),
('items','action','bulk_rate_update',
 'UPDATE items SET default_rate = ROUND(COALESCE(default_rate,0) * (1 + ?/100.0), 2)',
 '{"params":["percent"],"label":"Bulk rate ±%","success":"Default rates updated."}', 30);

-- Invoices page
INSERT INTO app_pages (slug, title, page_type, template)
VALUES ('invoices','Invoices','list','<div class="card"><h3>Invoices</h3><p class="muted">Create and manage invoices.</p></div>');

INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES
('invoices','form','invoice_create',
 'INSERT INTO invoices(party_id,invoice_no,invoice_date,inv_type,place_of_supply,reverse_charge,status,itc_eligible,created_at,updated_at)
  VALUES (?,?,?,?,?,?,\"draft\",?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)',
 '{"inputs":[
   {"name":"party_id","label":"Party ID"},
   {"name":"invoice_no","label":"Invoice No"},
   {"name":"invoice_date","label":"Date","type":"date"},
   {"name":"inv_type","label":"Type"},
   {"name":"place_of_supply","label":"Place of supply"},
   {"name":"reverse_charge","label":"Reverse charge"},
   {"name":"itc_eligible","label":"ITC eligible"}
  ],
  "submit":"Create invoice",
  "params":["party_id","invoice_no","invoice_date","inv_type","place_of_supply","reverse_charge","itc_eligible"],
  "success":"Invoice created."}', 10),
('invoices','list','invoices_list',
 'SELECT i.id, i.invoice_no, i.invoice_date, i.inv_type, i.status, p.name AS party
    FROM invoices i JOIN parties p ON p.id=i.party_id
   WHERE (:party_id IS NULL OR i.party_id = :party_id)
   ORDER BY i.invoice_date DESC, i.id DESC LIMIT 300',
 '{"params":["party_id"],
   "columns":["id","party","invoice_no","invoice_date","inv_type","status"],
   "fk_links":{"id":{"to":"invoice_workspace","param":"id"}}}', 20);

-- Invoice workspace page (open in new tab via list link)
INSERT INTO app_pages (slug, title, page_type, template)
VALUES ('invoice_workspace','Invoice Workspace','workspace',
'<div class="card"><h3>Invoice Workspace</h3><p class="muted">Add items, bulk update, finalize.</p></div>');

-- Workspace components:
-- Show summary
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES
('invoice_workspace','list','workspace_summary',
 'SELECT i.id, i.invoice_no, i.invoice_date, i.inv_type, i.status, p.name AS party
    FROM invoices i JOIN parties p ON p.id=i.party_id WHERE i.id = :id',
 '{"params":["id"],"columns":["id","party","invoice_no","invoice_date","inv_type","status"]}', 5);

-- List lines
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES
('invoice_workspace','list','workspace_lines',
 'SELECT ii.id, it.name AS item, ii.quantity, ii.rate, ii.discount_percent,
         ii.taxable_amount, ii.cgst_amount, ii.sgst_amount, ii.igst_amount, ii.line_total
    FROM invoice_items ii JOIN items it ON it.id=ii.item_id
   WHERE ii.invoice_id = :id ORDER BY ii.id',
 '{"params":["id"],
   "columns":["id","item","quantity","rate","discount_percent","taxable_amount","cgst_amount","sgst_amount","igst_amount","line_total"]}', 10);

-- Add line form (action will compute amounts in main_entry.php or here via SQL if preferred)
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES
('invoice_workspace','form','workspace_add_line',
 'INSERT INTO invoice_items(invoice_id,item_id,description,quantity,rate,discount_percent,taxable_amount,cgst_amount,sgst_amount,igst_amount,line_total,itc_eligible,is_prepackaged_labelled)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
 '{"inputs":[
    {"name":"item_id","label":"Item ID"},
    {"name":"description","label":"Description"},
    {"name":"quantity","label":"Qty","type":"number","step":"0.001"},
    {"name":"rate","label":"Rate","type":"number","step":"0.01"},
    {"name":"discount_percent","label":"Discount %","type":"number","step":"0.01"},
    {"name":"itc_eligible","label":"ITC eligible"},
    {"name":"is_prepackaged_labelled","label":"Prepackaged"}
   ],
   "submit":"Add line",
   "params":["item_id","description","quantity","rate","discount_percent","itc_eligible","is_prepackaged_labelled"],
   "success":"Line added."}', 20);

-- Bulk rate update action (±%)
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES
('invoice_workspace','action','workspace_bulk_rate',
 'UPDATE invoice_items SET rate = ROUND(rate * (1 + ?/100.0), 2) WHERE invoice_id = ? AND item_id = ?',
 '{"params":["percent","invoice_id","item_id"],"label":"Bulk rate ±%","success":"Rates updated."}', 30);

-- Finalize action
INSERT INTO app_components (page_slug, comp_type, name, sql_text, meta_json, ord)
VALUES
('invoice_workspace','action','workspace_finalize',
 'UPDATE invoices SET status=\"final\", updated_at=CURRENT_TIMESTAMP WHERE id = ?',
 '{"params":["id"],"label":"Finalize","success":"Invoice finalized."}', 40);
```

Note: For line insert, you likely want to compute `taxable_amount`, `cgst_amount`, etc. in PHP before calling the INSERT action. You can do this by intercepting the workspace_add_line action in main_entry.php (e.g., check `act==='workspace_add_line'`, fetch item rates, compute amounts, then execute INSERT). If you prefer DB-only, you can replace the INSERT with a small stored procedure—but Hostinger often restricts routines, so PHP-side computation is safer.

---

## Running in both environments

- Local (MariaDB): set `APP_ENV=local` and run `php -S localhost:8080 -t /path/to/your/app`
- Hostinger (MySQL): set `$ENV='hostinger'` in config.php or via environment config; deploy files; open `https://yourdomain/main_entry.php?p=parties`

---

## Next steps and customizations

- Add type-aware inputs in meta_json (date/number/checkbox).
- Add delete/edit forms for rows:
  - Action with `DELETE FROM ... WHERE id = :id`
  - Form with `UPDATE ... SET ... WHERE id = :id`
- Add CSV export components:
  - comp_type='action' that streams `text/csv` using parameters from GET
- Add IGST vs CGST/SGST logic:
  - Compute taxes in PHP based on place_of_supply/state equality
- Add sequences by series_code + inv_type:
  - Additional table `series_sequences(series_code, inv_type, next_seq)` and an action to allocate next number

If you share your exact column names and any preferred workflows (e.g., auto-fill rate from item, specific bulk corrections required), I’ll wire those into the metadata and the PHP hook points so your app is turnkey on both environments.
