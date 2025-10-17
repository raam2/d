<?php
// file: new_index.php
require_once __DIR__ . '/db.php';

$pdo = DB::conn();
$page = $_GET['page'] ?? 'home';
if (!is_string($page) || strlen($page) > 180) $page = 'home';

$stmt = $pdo->prepare("SELECT code, menu_label FROM Pages WHERE name=?");
$stmt->execute([$page]);
$pageRow = $stmt->fetch(PDO::FETCH_ASSOC);

$title = $pageRow ? $pageRow['menu_label'] : 'Page not found';

// Load settings for branding
$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings") as $s) {
    $settings[$s["setting_key"]] = $s["setting_value"];
}
$companyName = $settings["company_name"] ?? "Bharat Accounting";
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($companyName." – ".$title) ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin:0; }
    header { background:#0b5; color:#fff; padding:10px 16px; display:flex; justify-content:space-between; }
    nav { background:#f7f7f7; border-bottom:1px solid #ddd; }
    nav ul { list-style:none; margin:0; padding:8px 12px; display:flex; flex-wrap:wrap; gap:8px; }
    nav a { text-decoration:none; color:#064; padding:4px 8px; border-radius:4px; }
    nav a.active { background:#dff5e8; border:1px solid #0b5; }
    main { padding:16px; }
    footer { border-top:1px solid #eee; padding:12px 16px; font-size:12px; color:#666; }
    .error { background:#ffecec; color:#900; padding:8px; border:1px solid #f5c2c7; }
  </style>
</head>
<body>
  <header>
    <div><?= htmlspecialchars($companyName) ?></div>
    <div><?= htmlspecialchars($title) ?></div>
  </header>
  <nav>
    <ul>
      <?php
      $menuStmt = $pdo->query("SELECT name, menu_label, menu_group FROM Pages ORDER BY menu_group, menu_order, name");
      foreach ($menuStmt as $m) {
          $active = ($m['name'] === $page) ? 'active' : '';
          echo "<li><a class=\"$active\" href=\"?page=".htmlspecialchars($m['name'])."\">".htmlspecialchars($m['menu_group'].' → '.$m['menu_label'])."</a></li>";
      }
      ?>
    </ul>
  </nav>
  <main>
    <?php
    if (!$pageRow) {
        http_response_code(404);
        echo "<div class='error'>404: Page '".htmlspecialchars($page)."' not found.</div>";
    } else {
        ob_start();
        try {
            eval("?>".$pageRow['code']."<?php ");
        } catch (Throwable $t) {
            DB::logError("Page '".$page."' failed: ".$t->getMessage()." at line ".$t->getLine());
            echo "<div class='error'>Page error logged to diagnostics.</div>";
        }
        echo ob_get_clean();
    }
    ?>
  </main>
  <footer>
    <small>Powered by DB‑driven pages • <?= date('Y-m-d H:i') ?> • <a href="?page=tools/diagnostics">Diagnostics</a></small>
  </footer>
</body>
</html>

