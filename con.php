<?php
/**
 * Database Configuration
 * Simple configuration for the accounting system
 */

// Database connection details
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306'); 
define('DB_NAME', 'gst_accounting');
define('DB_USER', 'gstwork');
define('DB_PASS', 'gstwork@123');

// Test connection (optional)
try {
    $test_pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    // Connection successful
} catch (PDOException $e) {
    // Connection failed - will be handled by the application
}