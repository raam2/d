<?php
// Simple database connection test script
// This helps verify that the database is accessible before loading the full application

ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Database Connection Test</title>\n";
echo "<style>body{font-family:monospace;background:#0b0c10;color:#e5e7eb;padding:20px}";
echo ".success{color:#86efac}.error{color:#fca5a5}.info{color:#60a5fa}</style>\n</head>\n<body>\n";
echo "<h1>Database Connection Test</h1>\n";

try {
    require __DIR__ . '/config.php';
    
    global $ENV, $config;
    
    echo "<div class='info'>Environment: <strong>$ENV</strong></div>\n";
    echo "<div class='info'>Database Host: <strong>{$config[$ENV]['host']}</strong></div>\n";
    echo "<div class='info'>Database Name: <strong>{$config[$ENV]['db']}</strong></div>\n";
    echo "<div class='info'>Database User: <strong>{$config[$ENV]['user']}</strong></div>\n";
    echo "<hr>\n";
    
    require __DIR__ . '/db.php';
    
    $pdo = db();
    echo "<div class='success'>✓ Database connection successful!</div>\n";
    
    // Test app_pages table
    $pages = fetchAll('SELECT slug, title FROM app_pages ORDER BY slug');
    echo "<div class='success'>✓ Found " . count($pages) . " pages in app_pages table</div>\n";
    echo "<ul>\n";
    foreach ($pages as $page) {
        echo "<li>{$page['slug']} - {$page['title']}</li>\n";
    }
    echo "</ul>\n";
    
    // Test app_components table
    $components = fetchAll('SELECT page_slug, name FROM app_components ORDER BY page_slug, ord');
    echo "<div class='success'>✓ Found " . count($components) . " components in app_components table</div>\n";
    echo "<ul>\n";
    foreach ($components as $comp) {
        echo "<li>{$comp['page_slug']} / {$comp['name']}</li>\n";
    }
    echo "</ul>\n";
    
    // Check for other critical tables
    $tables = ['parties', 'items', 'invoices', 'diagnostics'];
    echo "<hr>\n<h2>Critical Tables Check</h2>\n";
    foreach ($tables as $table) {
        try {
            $result = fetchOne("SELECT COUNT(*) as cnt FROM $table");
            echo "<div class='success'>✓ Table '$table' exists with {$result['cnt']} records</div>\n";
        } catch (PDOException $e) {
            echo "<div class='error'>✗ Table '$table' not found or inaccessible</div>\n";
        }
    }
    
    echo "<hr>\n";
    echo "<div class='success'><strong>All checks passed! The application should work correctly.</strong></div>\n";
    echo "<div class='info'><a href='main_entry.php?p=dashboard' style='color:#60a5fa'>Go to Dashboard</a></div>\n";
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    echo "<div class='error'>Stack trace:</div>\n";
    echo "<pre style='background:#1f2937;padding:10px;overflow:auto'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>\n";
}

echo "</body>\n</html>";
