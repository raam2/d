<?php
/**
 * Diagnostic page to check database connection and metadata
 * Access this at: diagnostic.php
 */

// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><title>Diagnostic</title>";
echo "<style>body{background:#0e0f13;color:#e5e7eb;font-family:monospace;padding:20px}";
echo "h1,h2{color:#60a5fa}pre{background:#1f2937;padding:10px;border-radius:4px;overflow:auto}";
echo ".ok{color:#86efac}.error{color:#fca5a5}</style></head><body>";

echo "<h1>Database-Driven App Diagnostics</h1>";

// Step 1: Check PHP version
echo "<h2>1. PHP Version</h2>";
echo "<pre class='ok'>PHP " . phpversion() . "</pre>";

// Step 2: Check if config.php loads
echo "<h2>2. Configuration</h2>";
try {
    require __DIR__ . '/config.php';
    echo "<pre class='ok'>✓ config.php loaded successfully</pre>";
    echo "<pre>Environment: <strong>" . htmlspecialchars($ENV) . "</strong></pre>";
    echo "<pre>Database: " . htmlspecialchars($config[$ENV]['db']) . "</pre>";
    echo "<pre>Host: " . htmlspecialchars($config[$ENV]['host']) . "</pre>";
    echo "<pre>User: " . htmlspecialchars($config[$ENV]['user']) . "</pre>";
} catch (Exception $e) {
    echo "<pre class='error'>✗ Error loading config: " . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

// Step 3: Check if db.php loads
echo "<h2>3. Database Helper Functions</h2>";
try {
    require __DIR__ . '/db.php';
    echo "<pre class='ok'>✓ db.php loaded successfully</pre>";
    echo "<pre>✓ Functions available: db(), q(), fetchOne(), fetchAll()</pre>";
} catch (Exception $e) {
    echo "<pre class='error'>✗ Error loading db.php: " . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

// Step 4: Test database connection
echo "<h2>4. Database Connection</h2>";
try {
    $pdo = db();
    echo "<pre class='ok'>✓ Connected to database successfully</pre>";
    
    // Get server info
    $version = $pdo->query("SELECT VERSION()")->fetchColumn();
    echo "<pre>Database Version: " . htmlspecialchars($version) . "</pre>";
} catch (PDOException $e) {
    echo "<pre class='error'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</pre>";
    echo "<h3>Common Issues:</h3>";
    echo "<ul>";
    echo "<li>Check database credentials in config.php</li>";
    echo "<li>Ensure database server is running</li>";
    echo "<li>Verify network connectivity to database host</li>";
    echo "<li>Check APP_ENV is set correctly (current: " . htmlspecialchars($ENV) . ")</li>";
    echo "</ul>";
    exit;
}

// Step 5: Check if app_pages table exists
echo "<h2>5. Database Tables</h2>";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'app_pages'");
    if ($result->rowCount() > 0) {
        echo "<pre class='ok'>✓ app_pages table exists</pre>";
        
        // Count pages
        $count = $pdo->query("SELECT COUNT(*) FROM app_pages")->fetchColumn();
        echo "<pre>Pages in database: " . $count . "</pre>";
        
        // Show all pages
        echo "<h3>Available Pages:</h3>";
        $pages = fetchAll("SELECT slug, title, page_type FROM app_pages ORDER BY slug");
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;color:#e5e7eb'>";
        echo "<tr><th>Slug</th><th>Title</th><th>Type</th><th>Test Link</th></tr>";
        foreach ($pages as $p) {
            $link = "main_entry.php?p=" . urlencode($p['slug']);
            echo "<tr><td>" . htmlspecialchars($p['slug']) . "</td>";
            echo "<td>" . htmlspecialchars($p['title']) . "</td>";
            echo "<td>" . htmlspecialchars($p['page_type']) . "</td>";
            echo "<td><a href='" . $link . "' style='color:#60a5fa'>Test →</a></td></tr>";
        }
        echo "</table>";
    } else {
        echo "<pre class='error'>✗ app_pages table does not exist</pre>";
        echo "<p>Run the SQL patch to create the required tables.</p>";
    }
} catch (PDOException $e) {
    echo "<pre class='error'>✗ Error checking tables: " . htmlspecialchars($e->getMessage()) . "</pre>";
}

// Step 6: Check if app_components table exists
try {
    $result = $pdo->query("SHOW TABLES LIKE 'app_components'");
    if ($result->rowCount() > 0) {
        echo "<pre class='ok'>✓ app_components table exists</pre>";
        
        // Count components
        $count = $pdo->query("SELECT COUNT(*) FROM app_components")->fetchColumn();
        echo "<pre>Components in database: " . $count . "</pre>";
    } else {
        echo "<pre class='error'>✗ app_components table does not exist</pre>";
    }
} catch (PDOException $e) {
    echo "<pre class='error'>✗ Error checking components: " . htmlspecialchars($e->getMessage()) . "</pre>";
}

// Step 7: Sample data check
echo "<h2>6. Sample Component Data</h2>";
try {
    $sample = fetchOne("SELECT * FROM app_components LIMIT 1");
    if ($sample) {
        echo "<pre class='ok'>✓ Sample component found</pre>";
        echo "<pre>Page: " . htmlspecialchars($sample['page_slug']) . "</pre>";
        echo "<pre>Type: " . htmlspecialchars($sample['comp_type']) . "</pre>";
        echo "<pre>Name: " . htmlspecialchars($sample['name']) . "</pre>";
        
        // Parse JSON
        $meta = json_decode($sample['meta_json'], true);
        if ($meta) {
            echo "<pre class='ok'>✓ JSON metadata is valid</pre>";
        } else {
            echo "<pre class='error'>✗ JSON metadata is invalid</pre>";
        }
    } else {
        echo "<pre class='error'>⚠ No components found in database</pre>";
    }
} catch (PDOException $e) {
    echo "<pre class='error'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "<h2>Summary</h2>";
echo "<p class='ok' style='font-size:18px'>If all checks above passed, the application should work!</p>";
echo "<p>Try opening: <a href='main_entry.php?p=dashboard' style='color:#60a5fa;font-size:16px'>main_entry.php?p=dashboard</a></p>";

echo "</body></html>";
