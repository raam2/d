<?php
/**
 * Database Connection Handler
 * Provides PDO connection with fallback support
 */

require_once __DIR__ . '/config.php';

class DB {
    private static $pdo = null;
    
    /**
     * Get PDO connection instance (singleton)
     */
    public static function conn() {
        if (self::$pdo === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    DB_HOST,
                    DB_PORT,
                    DB_NAME,
                    DB_CHARSET
                );
                
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                
            } catch (PDOException $e) {
                // Log error if debug mode is on
                if (APP_DEBUG) {
                    error_log("Database Connection Error: " . $e->getMessage());
                    die("Database connection failed. Please check your configuration.");
                } else {
                    die("Database connection failed.");
                }
            }
        }
        
        return self::$pdo;
    }
    
    /**
     * Execute a query and return the result
     */
    public static function query($sql, $params = []) {
        $pdo = self::conn();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Log diagnostic message
     */
    public static function logDiagnostic($level, $message) {
        try {
            $pdo = self::conn();
            $stmt = $pdo->prepare("INSERT INTO diagnostics (level, message) VALUES (?, ?)");
            $stmt->execute([$level, $message]);
        } catch (Exception $e) {
            error_log("Failed to log diagnostic: " . $e->getMessage());
        }
    }
}
