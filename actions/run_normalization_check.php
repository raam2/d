<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = (new Database())->getConnection();

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<h3>🧹 Normalization Hints</h3><ul>";
foreach ($tables as $tbl) {
    $cols = $pdo->query("SHOW COLUMNS FROM `$tbl`")->fetchAll();
    $colNames = array_column($cols, 'Field');
    $redundant = array_filter($colNames, fn($c) => stripos($c, 'name') !== false || stripos($c, 'desc') !== false);
    if (count($redundant) > 1) {
        echo "<li><strong>$tbl</strong>: Possible redundancy in columns " . implode(', ', $redundant) . "</li>";
    }
}
echo "</ul>";

