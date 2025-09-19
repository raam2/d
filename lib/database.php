<?php
/**
 * Database connection using credentials from con.php
 */

// Include configuration
$con_file = __DIR__ . '/../con.php';
if (file_exists($con_file)) {
    require_once $con_file;
}

// Set default values if constants are not defined
if (!defined('DB_HOST')) {
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', 'gst_accounting');
    define('DB_USER', 'gstwork');
    define('DB_PASS', 'gstwork@123');
}

$db = null;
$db_error = null;

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ];
    
    $db = new PDO($dsn, DB_USER, DB_PASS, $options);
    
    // Set timezone to India
    $db->exec("SET time_zone = '+05:30'");
    
} catch (PDOException $e) {
    $db_error = $e->getMessage();
    $db = null;
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