<?php
declare(strict_types=1);
require __DIR__ . '/db.php';

// ---------- utils ----------
function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function j($v): string {
    return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function template_render(string $tpl, array $ctx): string {
    // Replace {{key}} with escaped values; {{raw:key}} for unescaped
    $out = $tpl;
    foreach ($ctx as $k => $v) {
        $out = str_replace('{{' . $k . '}}', h((string)$v), $out);
        $out = str_replace('{{raw:' . $k . '}}', (string)$v, $out);
    }
    return $out;
}

// ---------- routing ----------
$slug = $_GET['p'] ?? 'dashboard';

// ---------- load page ----------
$page = fetchOne("SELECT * FROM app_pages WHERE slug=?", [$slug]);
if (!$page) {
    // fallback basic home
    $basic = '<div class="card"><h3>Welcome</h3><p class="muted">Use navigation to open pages.</p></div>';
    $page = ['slug' => 'dashboard', 'title' => 'Dashboard', 'page_type' => 'workspace', 'template' => $basic];
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
            foreach ($params as $k => $v) {
                $sql = preg_replace('/:' . preg_quote($k, '/') . '\b/', '?', $sql, 1);
                $order[] = $v;
            }
            q($sql, $order);
            $notice = $meta['success'] ?? 'Action completed.';
        }
    }
}

// ---------- render head ----------
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title><?= h($page['title']) ?></title>
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
  <strong><?= h($page['title']) ?></strong>
  <a class="btn" href="?p=dashboard">Dashboard</a>
  <a class="btn" href="?p=parties">Parties</a>
  <a class="btn" href="?p=items">Items</a>
  <a class="btn" href="?p=invoices">Invoices</a>
</header>
<div class="container">
  <div class="sidebar">
    <div class="card"><strong>Pages</strong>
      <ul style="list-style:none;padding-left:0">
        <?php foreach (fetchAll("SELECT slug,title FROM app_pages ORDER BY slug") as $pg): ?>
          <li><a href="?p=<?= h($pg['slug']) ?>"><?= h($pg['title']) ?></a></li>
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
    <?php if ($notice): ?><div class="card success"><?= h($notice) ?></div><?php endif; ?>

    <?php
    // ---------- render template ----------
    echo template_render($page['template'], ['title' => $page['title']]);

    // ---------- render components ----------
    foreach ($comps as $c) {
        $meta = json_decode($c['meta_json'], true) ?: [];
        if ($c['comp_type'] === 'list') {
            // lists: optional params from GET
            $params = [];
            foreach ($meta['params'] ?? [] as $pname) {
                $params[$pname] = $_GET[$pname] ?? null;
            }
            $sql = $c['sql_text'];
            $order = [];
            foreach ($params as $k => $v) {
                $sql = preg_replace('/:' . preg_quote($k, '/') . '\b/', '?', $sql, 1);
                $order[] = $v;
            }
            $rows = fetchAll($sql, $order);
            echo '<div class="card"><table><tr>';
            foreach (($meta['columns'] ?? array_keys($rows[0] ?? [])) as $col) {
                echo '<th>' . h($col['label'] ?? $col['field'] ?? $col) . '</th>';
            }
            echo '</tr>';
            foreach ($rows as $r) {
                echo '<tr>';
                foreach (($meta['columns'] ?? array_keys($r)) as $col) {
                    $field = is_array($col) ? ($col['field'] ?? '') : $col;
                    $val = $r[$field] ?? '';
                    // FK link decoration
                    if (!empty($meta['fk_links'][$field])) {
                        $lk = $meta['fk_links'][$field];
                        $href = '?p=' . urlencode($lk['to']) . '&' . $lk['param'] . '=' . urlencode((string)$val);
                        echo '<td>' . h((string)$val) . ' <a href="' . $href . '" target="_blank">↗</a></td>';
                    } else {
                        echo '<td>' . h((string)$val) . '</td>';
                    }
                }
                echo '</tr>';
            }
            echo '</table></div>';
        } elseif ($c['comp_type'] === 'form') {
            // forms: render inputs from meta, submit to action with same name
            echo '<div class="card"><form method="post" class="grid">';
            echo '<input type="hidden" name="act" value="' . h($c['name']) . '">';
            foreach ($meta['fields'] ?? $meta['inputs'] ?? [] as $inp) {
                echo '<label>' . h($inp['label']) . '</label>';
                $val = isset($_GET[$inp['name']]) ? h($_GET[$inp['name']]) : ($inp['default'] ?? '');
                $type = $inp['type'] ?? 'text';
                $step = ($type === 'number' && isset($inp['step'])) ? ' step="' . h($inp['step']) . '"' : '';
                $required = !empty($inp['required']) ? ' required' : '';
                $pattern = isset($inp['pattern']) ? ' pattern="' . h($inp['pattern']) . '"' : '';
                $placeholder = isset($inp['placeholder']) ? ' placeholder="' . h($inp['placeholder']) . '"' : '';
                
                if ($type === 'select') {
                    echo '<select name="' . h($inp['name']) . '"' . $required . '>';
                    foreach ($inp['options'] ?? [] as $opt) {
                        $selected = ($val == $opt['value']) ? ' selected' : '';
                        echo '<option value="' . h($opt['value']) . '"' . $selected . '>' . h($opt['label']) . '</option>';
                    }
                    echo '</select>';
                } else {
                    echo '<input name="' . h($inp['name']) . '" type="' . h($type) . '" value="' . $val . '"' . $step . $required . $pattern . $placeholder . '>';
                }
            }
            echo '<div></div><button class="btn" type="submit">' . h($meta['submit'] ?? 'Submit') . '</button></form></div>';
        } elseif ($c['comp_type'] === 'action') {
            // actions: show as inline control
            echo '<div class="card"><form method="post" class="row">';
            echo '<input type="hidden" name="act" value="' . h($c['name']) . '">';
            foreach ($meta['params'] ?? [] as $pname) {
                echo '<input name="' . h($pname) . '" placeholder="' . h($pname) . '">';
            }
            echo '<button class="btn" type="submit">' . h($meta['label'] ?? $c['name']) . '</button></form></div>';
        }
    }
    ?>
  </div>
</div>
</body></html>
