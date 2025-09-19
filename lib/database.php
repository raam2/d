<?php
/**
 * Database connection using credentials from con.php
 */

// Read connection details from existing con.php file
$con_file = __DIR__ . '/../con.php';
if (!file_exists($con_file)) {
    $con_file = __DIR__ . '/../config/con.php'; 
}

if (!file_exists($con_file)) {
    die("Database configuration file not found");
}

$con_content = file_get_contents($con_file);

// Extract database credentials using simple pattern matching
$host = '127.0.0.1';
$port = '3306';
$dbname = 'gst_accounting';
$username = 'gstwork';
$password = 'gstwork@123';

// Try to parse from config file if it's in a different format
if (preg_match('/MYSQL_HOST=([^\s\n]+)/', $con_content, $matches)) {
    $host = trim($matches[1]);
}
if (preg_match('/MYSQL_PORT=([^\s\n]+)/', $con_content, $matches)) {
    $port = trim($matches[1]);
}
if (preg_match('/MYSQL_DB=([^\s\n]+)/', $con_content, $matches)) {
    $dbname = trim($matches[1]);
}
if (preg_match('/MYSQL_USER=([^\s\n]+)/', $con_content, $matches)) {
    $username = trim($matches[1]);
}
if (preg_match('/MYSQL_PASS=([^\s\n]+)/', $con_content, $matches)) {
    $password = trim($matches[1]);
}

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    $db = new PDO($dsn, $username, $password, $options);
    
    // Set timezone to India
    $db->exec("SET time_zone = '+05:30'");
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

/**
 * Helper function to execute transactions safely
 */
function db_transaction(callable $callback) {
    global $db;
    
    try {
        $db->beginTransaction();
        $result = $callback($db);
        $db->commit();
        return $result;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

/**
 * Helper function to get app settings
 */
function get_setting($key, $default = null) {
    global $db;
    
    // Create settings table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    return $result ? $result['setting_value'] : $default;
}

/**
 * Helper function to set app settings
 */
function set_setting($key, $value) {
    global $db;
    
    $stmt = $db->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([$key, $value]);
}