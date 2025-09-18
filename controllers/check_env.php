<?php
// check_env.php — quick environment check
declare(strict_types=1);
@ini_set('display_errors','1'); error_reporting(E_ALL);

echo "<h3>Environment</h3>";
echo "<ul>";
echo "<li>PHP_VERSION: ".PHP_VERSION."</li>";
echo "<li>Server API: ".php_sapi_name()."</li>";
echo "<li>Document Root: ".($_SERVER['DOCUMENT_ROOT'] ?? '(cli)')."</li>";
echo "<li>Loaded Extensions: ".implode(', ', get_loaded_extensions())."</li>";
echo "</ul>";

echo "<h4>PDO MySQL test</h4>";
if (file_exists(__DIR__.'/db.php')) {
  require_once __DIR__.'/db.php';
  try {
    if (isset($pdo) && $pdo instanceof PDO) $cx = $pdo;
    elseif (class_exists('Database')) { $cx = (new Database())->getConnection(); }
    else { throw new RuntimeException('db.php has neither $pdo nor Database class'); }
    $v = $cx->query('SELECT VERSION()')->fetchColumn();
    echo "<p>DB connected. VERSION(): ".htmlspecialchars($v)."</p>";
  } catch (Throwable $e) {
    echo "<pre>DB connect failed: ".htmlspecialchars($e->getMessage())."</pre>";
  }
} else {
  echo "<p>db.php not found next to this file.</p>";
}
