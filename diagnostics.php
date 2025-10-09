<?php
/**
 * Diagnostic script to identify why main_entry.php is not responding
 * This script performs a series of tests to pinpoint the exact issue
 */

header('Content-Type: text/html; charset=UTF-8');
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "<!DOCTYPE html>\n<html>\n<head>\n<meta charset='UTF-8'>\n<title>Application Diagnostics</title>\n";
echo "<style>
body { font-family: monospace; background: #0b0c10; color: #e5e7eb; padding: 20px; max-width: 1200px; margin: 0 auto; }
h1, h2 { color: #60a5fa; border-bottom: 2px solid #1f2937; padding-bottom: 10px; }
.test { background: #0f172a; border: 1px solid #1f2937; border-radius: 6px; padding: 15px; margin: 15px 0; }
.test-name { font-weight: bold; color: #93c5fd; margin-bottom: 8px; }
.success { color: #86efac; }
.error { color: #fca5a5; }
.warning { color: #fcd34d; }
.info { color: #60a5fa; }
pre { background: #0b1120; border: 1px solid #1f2937; padding: 10px; overflow: auto; font-size: 12px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #1f2937; padding: 8px; text-align: left; }
th { background: #111827; }
.section { margin: 30px 0; }
</style>\n</head>\n<body>\n";

echo "<h1>🔍 Application Diagnostics</h1>\n";
echo "<p class='info'>Running comprehensive diagnostics to identify issues...</p>\n";

// Test 1: PHP Version
echo "<div class='section'>\n<h2>1. PHP Environment</h2>\n";
echo "<div class='test'>\n";
echo "<div class='test-name'>PHP Version Check</div>\n";
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo "<div class='success'>✓ PHP $phpVersion (OK - Required 7.4+)</div>\n";
} else {
    echo "<div class='error'>✗ PHP $phpVersion (Too old - Required 7.4+)</div>\n";
}
echo "</div>\n";

// Test 2: Required Extensions
echo "<div class='test'>\n";
echo "<div class='test-name'>Required PHP Extensions</div>\n";
$required = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
foreach ($required as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='success'>✓ Extension '$ext' loaded</div>\n";
    } else {
        echo "<div class='error'>✗ Extension '$ext' NOT loaded (CRITICAL)</div>\n";
    }
}
echo "</div>\n";

// Test 3: File Existence
echo "<div class='test'>\n";
echo "<div class='test-name'>Required Files</div>\n";
$files = ['config.php', 'db.php', 'main_entry.php'];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (file_exists($path)) {
        echo "<div class='success'>✓ File '$file' exists</div>\n";
    } else {
        echo "<div class='error'>✗ File '$file' NOT found (CRITICAL)</div>\n";
    }
}
echo "</div>\n";
echo "</div>\n"; // End section 1

// Test 4: Config Loading
echo "<div class='section'>\n<h2>2. Configuration</h2>\n";
echo "<div class='test'>\n";
echo "<div class='test-name'>Config.php Loading</div>\n";
try {
    require_once __DIR__ . '/config.php';
    echo "<div class='success'>✓ config.php loaded successfully</div>\n";
    
    global $ENV, $config;
    echo "<div class='info'>Environment: <strong>$ENV</strong></div>\n";
    
    if (isset($config[$ENV])) {
        echo "<div class='success'>✓ Configuration for '$ENV' found</div>\n";
        echo "<table>\n";
        echo "<tr><th>Setting</th><th>Value</th></tr>\n";
        foreach ($config[$ENV] as $key => $value) {
            $display = ($key === 'pass') ? str_repeat('*', strlen((string)$value)) : htmlspecialchars((string)$value);
            echo "<tr><td>$key</td><td>$display</td></tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<div class='error'>✗ Configuration for '$ENV' NOT found (CRITICAL)</div>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error loading config.php: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}
echo "</div>\n";
echo "</div>\n"; // End section 2

// Test 5: Database Connection
echo "<div class='section'>\n<h2>3. Database Connectivity</h2>\n";
echo "<div class='test'>\n";
echo "<div class='test-name'>Database Connection</div>\n";
try {
    require_once __DIR__ . '/db.php';
    $pdo = db();
    echo "<div class='success'>✓ Database connection established</div>\n";
    
    // Get server version
    $stmt = $pdo->query('SELECT VERSION() as version');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<div class='info'>Database Version: " . htmlspecialchars($result['version']) . "</div>\n";
    
    // Get current database
    $stmt = $pdo->query('SELECT DATABASE() as db');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<div class='info'>Current Database: " . htmlspecialchars($result['db']) . "</div>\n";
    
} catch (PDOException $e) {
    echo "<div class='error'>✗ Database connection FAILED</div>\n";
    echo "<div class='error'>Error: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    echo "<div class='warning'>Check database credentials in config.php</div>\n";
    echo "</div>\n</div>\n</body>\n</html>";
    exit;
} catch (Exception $e) {
    echo "<div class='error'>✗ Unexpected error: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    echo "</div>\n</div>\n</body>\n</html>";
    exit;
}
echo "</div>\n";

// Test 6: Critical Tables
echo "<div class='test'>\n";
echo "<div class='test-name'>Critical Tables Check</div>\n";
$criticalTables = [
    'app_pages' => 'Application pages metadata',
    'app_components' => 'UI components metadata',
    'parties' => 'Customers and suppliers',
    'items' => 'Inventory items',
    'invoices' => 'Invoice headers',
    'diagnostics' => 'Activity log'
];

$allTablesExist = true;
foreach ($criticalTables as $table => $description) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `$table`");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<div class='success'>✓ Table '$table' exists with {$result['cnt']} records</div>\n";
    } catch (PDOException $e) {
        echo "<div class='error'>✗ Table '$table' NOT found or inaccessible</div>\n";
        $allTablesExist = false;
    }
}

if (!$allTablesExist) {
    echo "<div class='warning'>⚠ Some tables are missing. Import database_already_exit.sql</div>\n";
}
echo "</div>\n";
echo "</div>\n"; // End section 3

// Test 7: App Pages Data
echo "<div class='section'>\n<h2>4. Application Metadata</h2>\n";
echo "<div class='test'>\n";
echo "<div class='test-name'>App Pages</div>\n";
try {
    $pages = fetchAll('SELECT id, slug, title, page_type FROM app_pages ORDER BY slug');
    if (count($pages) > 0) {
        echo "<div class='success'>✓ Found " . count($pages) . " pages</div>\n";
        echo "<table>\n<tr><th>ID</th><th>Slug</th><th>Title</th><th>Type</th></tr>\n";
        foreach ($pages as $page) {
            echo "<tr>";
            echo "<td>{$page['id']}</td>";
            echo "<td><code>{$page['slug']}</code></td>";
            echo "<td>{$page['title']}</td>";
            echo "<td>{$page['page_type']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<div class='error'>✗ No pages found in app_pages table (CRITICAL)</div>\n";
        echo "<div class='warning'>Import database_already_exit.sql to populate pages</div>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error querying app_pages: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}
echo "</div>\n";

// Test 8: App Components Data
echo "<div class='test'>\n";
echo "<div class='test-name'>App Components</div>\n";
try {
    $components = fetchAll('SELECT page_slug, comp_type, name FROM app_components ORDER BY page_slug, ord');
    if (count($components) > 0) {
        echo "<div class='success'>✓ Found " . count($components) . " components</div>\n";
        echo "<table>\n<tr><th>Page</th><th>Type</th><th>Name</th></tr>\n";
        foreach ($components as $comp) {
            echo "<tr>";
            echo "<td><code>{$comp['page_slug']}</code></td>";
            echo "<td>{$comp['comp_type']}</td>";
            echo "<td>{$comp['name']}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
    } else {
        echo "<div class='error'>✗ No components found in app_components table (CRITICAL)</div>\n";
        echo "<div class='warning'>Import database_already_exit.sql to populate components</div>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error querying app_components: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}
echo "</div>\n";
echo "</div>\n"; // End section 4

// Test 9: Simulate Page Load
echo "<div class='section'>\n<h2>5. Page Rendering Test</h2>\n";
echo "<div class='test'>\n";
echo "<div class='test-name'>Dashboard Page Simulation</div>\n";
try {
    $pageSlug = 'dashboard';
    $page = fetchOne('SELECT * FROM app_pages WHERE slug = ?', [$pageSlug]);
    
    if ($page) {
        echo "<div class='success'>✓ Dashboard page loaded from database</div>\n";
        echo "<div class='info'>Title: {$page['title']}</div>\n";
        echo "<div class='info'>Type: {$page['page_type']}</div>\n";
        
        $components = fetchAll('SELECT * FROM app_components WHERE page_slug = ? ORDER BY ord, id', [$page['slug']]);
        echo "<div class='success'>✓ Found " . count($components) . " components for dashboard</div>\n";
        
        echo "<div class='info'>Template preview (first 200 chars):</div>\n";
        echo "<pre>" . htmlspecialchars(substr($page['template'], 0, 200)) . "...</pre>\n";
    } else {
        echo "<div class='error'>✗ Dashboard page NOT found in database (CRITICAL)</div>\n";
    }
} catch (Exception $e) {
    echo "<div class='error'>✗ Error simulating page load: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}
echo "</div>\n";
echo "</div>\n"; // End section 5

// Final Summary
echo "<div class='section'>\n<h2>6. Summary & Next Steps</h2>\n";
echo "<div class='test'>\n";

$issues = [];
if (version_compare($phpVersion, '7.4.0', '<')) {
    $issues[] = "PHP version too old";
}
if (!extension_loaded('pdo') || !extension_loaded('pdo_mysql')) {
    $issues[] = "Missing required PHP extensions";
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $issues[] = "Database connection failed";
}
if (!$allTablesExist) {
    $issues[] = "Missing required database tables";
}

if (empty($issues)) {
    echo "<div class='success'><strong>✓ All checks passed!</strong></div>\n";
    echo "<div class='info'>The application should work correctly.</div>\n";
    echo "<div class='info'><strong>Next step:</strong> <a href='main_entry.php?p=dashboard' style='color:#60a5fa'>Open Dashboard</a></div>\n";
} else {
    echo "<div class='error'><strong>✗ Issues found:</strong></div>\n";
    echo "<ul>\n";
    foreach ($issues as $issue) {
        echo "<li class='error'>$issue</li>\n";
    }
    echo "</ul>\n";
    echo "<div class='warning'><strong>Recommended actions:</strong></div>\n";
    echo "<ol>\n";
    if (in_array("Database connection failed", $issues)) {
        echo "<li>Check database credentials in config.php</li>\n";
        echo "<li>Verify database server is running and accessible</li>\n";
    }
    if (in_array("Missing required database tables", $issues)) {
        echo "<li>Import database_already_exit.sql to create tables</li>\n";
    }
    echo "</ol>\n";
}

echo "</div>\n";
echo "</div>\n"; // End section 6

echo "</body>\n</html>";
