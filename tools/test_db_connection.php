<?php
/**
 * Test Database Connection for bharat_accounts
 */

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

try {
    $host = '127.0.0.1';
    $port = '3306';
    $dbname = 'gst_accounting';
    $username = 'gstwork';
    $password = 'gstwork@123';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    echo "✅ Database connection successful!\n";
    echo "Database: {$dbname}\n";
    echo "Host: {$host}:{$port}\n";
    echo "User: {$username}\n";

    // Test basic table existence
    $tables = ['companies', 'parties', 'items', 'invoices', 'invoice_items', 'gst_rates'];
    echo "\n📋 Checking required tables:\n";

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetch()) {
            echo "  ✅ {$table}\n";
        } else {
            echo "  ❌ {$table} (missing)\n";
        }
    }

    // Check GST rates setup
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gst_rates");
    $gstCount = $stmt->fetch()['count'];
    echo "\n🔢 GST rates configured: {$gstCount}\n";

    if ($gstCount > 0) {
        $stmt = $pdo->query("SELECT id, cgst, sgst, igst, total_rate FROM gst_rates ORDER BY id");
        while ($row = $stmt->fetch()) {
            echo "  ID {$row['id']}: CGST {$row['cgst']}% + SGST {$row['sgst']}% = {$row['total_rate']}%\n";
        }
    }

} catch (PDOException $e) {
    echo "❌ Database connection failed:\n";
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
