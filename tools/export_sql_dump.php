<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="gst_accounting_dump.sql"');

$tables = [];
$stmt = $conn->query("SHOW TABLES");
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    // Dump table structure
    $stmt = $conn->query("SHOW CREATE TABLE `$table`");
    $row = $stmt->fetch();
    echo "\n-- Table structure for `$table`\n";
    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $row['Create Table'] . ";\n";

    // Dump table data
    $stmt = $conn->query("SELECT * FROM `$table`");
    $rows = $stmt->fetchAll();
    if (count($rows) > 0) {
        echo "\n-- Dumping data for `$table`\n";
        foreach ($rows as $r) {
            $vals = array_map(function($v) use ($conn) {
                return isset($v) ? $conn->quote($v) : "NULL";
            }, $r);
            echo "INSERT INTO `$table` VALUES (" . implode(", ", $vals) . ");\n";
        }
    }
}

