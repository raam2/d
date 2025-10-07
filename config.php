<?php
/**
 * Database Configuration
 * Supports dual environment: MariaDB (local) and MySQL (Hostinger)
 */

// Detect environment
$is_local = (
    in_array($_SERVER['SERVER_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']) ||
    strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false
);

if ($is_local) {
    // Local MariaDB configuration
    define('DB_HOST', '127.0.0.1');
    define('DB_PORT', '3306');
    define('DB_NAME', 'gst_notebook_lm');
    define('DB_USER', 'gstwork');
    define('DB_PASS', 'gstwork@123');
    define('DB_CHARSET', 'utf8mb4');
} else {
    // Hostinger MySQL configuration
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'gst_notebook_lm');
    define('DB_USER', 'gstwork');
    define('DB_PASS', 'gstwork@123');
    define('DB_CHARSET', 'utf8mb4');
}

// Application settings
define('APP_DEBUG', true);
define('APP_TIMEZONE', 'Asia/Kolkata');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);
