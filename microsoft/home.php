<?php
/**
 * GST Accounting System - Production Ready Version
 * Handles database syntax errors gracefully
 * Author: raam2
 * Date: 2025-09-27 13:09:00 UTC
 */

// Production error handling
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

session_start();

// Database Connection
class DB {
    private static $pdo = null;
    
    public static function conn() {
        if (self::$pdo === null) {
            try {
                self::$pdo = new PDO(
                    "mysql:host=127.0.0.1;dbname=gst_accounting;charset=utf8mb4",
                    "gstwork",
                    "gstwork@123",
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                self::logError('Database Connection', $e->getMessage());
                die('<div style="color:red;padding:20px;">Database connection failed</div>');
            }
        }
        return self::$pdo;
    }
    
    public static function logError($context, $message, $page = 'system') {
        try {
            $stmt = self::conn()->prepare("INSERT INTO error_logs (page_name, message, context) VALUES (?, ?, ?)");
            $stmt->execute([$page, $message, $context]);
        } catch (Exception $e) {
            error_log("GST Error: $context - $message");
        }
    }
}

// Get current page
$page = $_GET['page'] ?? 'dashboard';
$page = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $page);

// Get page data
$pdo = DB::conn();
$stmt = $pdo->prepare("SELECT * FROM Pages WHERE name = ?");
$stmt->execute([$page]);
$pageData = $stmt->fetch();

// Get debug mode setting
$debugStmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'debug_mode'");
$debugStmt->execute();
$debugResult = $debugStmt->fetch();
$debugMode = ($debugResult && $debugResult['setting_value'] === 'on');

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Accounting System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8f9fa; color: #333; line-height: 1.6; }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .container {
            display: flex;
            max-width: 1400px;
            margin: 0 auto;
            min-height: calc(100vh - 80px);
        }
        
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            padding: 1.5rem;
        }
        
        .main-content {
            flex: 1;
            padding: 2rem;
            background: white;
            margin: 1rem 1rem 1rem 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .menu-group { margin-bottom: 1.5rem; }
        .menu-group h3 {
            color: #2c3e50;
            font-size: 0.85rem;
            text-transform: uppercase;
            margin-bottom: 0.8rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #ecf0f1;
        }
        
        .menu-item {
            display: block;
            padding: 0.8rem 1rem;
            color: #2c3e50;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 0.3rem;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover { background: #ecf0f1; border-left-color: #3498db; transform: translateX(3px); }
        .menu-item.active { background: #3498db; color: white; border-left-color: #2c3e50; }
        
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 6px;
            border-left: 4px solid;
        }
        
        .alert-success { background: #d5f4e6; color: #0f5132; border-color: #27ae60; }
        .alert-error { background: #f8d7da; color: #842029; border-color: #e74c3c; }
        .alert-warning { background: #fff3cd; color: #664d03; border-color: #f39c12; }
        .alert-info { background: #cff4fc; color: #055160; border-color: #3498db; }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            margin: 0.2rem;
            transition: all 0.3s ease;
        }
        
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-success { background: #27ae60; }
        .btn-danger { background: #e74c3c; }
        .btn-warning { background: #f39c12; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.3rem; font-weight: 500; }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }
        .form-control:focus { outline: none; border-color: #3498db; box-shadow: 0 0 0 3px rgba(52,152,219,0.1); }
        
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; background: white; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        th, td { padding: 0.8rem; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #ecf0f1; font-weight: 600; color: #2c3e50; text-transform: uppercase; font-size: 0.8rem; }
        tr:hover { background: #f8f9fa; }
        
        .row { display: flex; flex-wrap: wrap; margin: 0 -0.5rem; }
        .col { flex: 1; padding: 0 0.5rem; }
        .col-2 { flex: 0 0 50%; padding: 0 0.5rem; }
        .col-3 { flex: 0 0 33.333%; padding: 0 0.5rem; }
        .col-4 { flex: 0 0 25%; padding: 0 0.5rem; }
        
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); padding: 1.5rem; margin-bottom: 1rem; }
        .card-header { border-bottom: 1px solid #dee2e6; padding-bottom: 1rem; margin-bottom: 1rem; }
        
        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .sidebar { width: 100%; }
            .main-content { margin: 1rem 0 0 0; }
            .header-content { flex-direction: column; gap: 0.5rem; }
            .row { flex-direction: column; }
            .col, .col-2, .col-3, .col-4 { flex: none; width: 100%; }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>🏛️ GST Accounting System</h1>
            <div>
                <?php echo date('d M Y, H:i'); ?> • <?php echo htmlspecialchars($page); ?>
                <?php if ($debugMode): ?>
                    • <span style="color: #f39c12;">🐛 DEBUG</span>
                <?php endif; ?>
            </div>
        </div>
    </header>
    
    <div class="container">
        <nav class="sidebar">
            <?php
            try {
                $menuStmt = $pdo->query("
                    SELECT name, menu_label, menu_group, menu_order 
                    FROM Pages 
                    WHERE menu_label IS NOT NULL 
                    ORDER BY 
                        CASE menu_group 
                            WHEN 'Main' THEN 1 
                            WHEN 'Invoices' THEN 2 
                            WHEN 'Parties' THEN 3 
                            WHEN 'Tools' THEN 4 
                            ELSE 5 
                        END,
                        menu_order, menu_label
                ");
                
                $menuGroups = [];
                while ($row = $menuStmt->fetch()) {
                    $group = $row['menu_group'] ?: 'Other';
                    $menuGroups[$group][] = $row;
                }
                
                foreach ($menuGroups as $groupName => $items) {
                    echo "<div class='menu-group'>";
                    echo "<h3>" . htmlspecialchars($groupName) . "</h3>";
                    
                    foreach ($items as $item) {
                        $active = ($page === $item['name']) ? 'active' : '';
                        echo "<a href='?page=" . urlencode($item['name']) . "' class='menu-item {$active}'>";
                        echo htmlspecialchars($item['menu_label']);
                        echo "</a>";
                    }
                    
                    echo "</div>";
                }
                
            } catch (Exception $e) {
                echo "<div class='alert alert-error'>Error loading menu</div>";
                DB::logError('Menu error', $e->getMessage());
            }
            ?>
        </nav>
        
        <main class="main-content">
            <?php
            if (!$pageData) {
                echo "<div class='alert alert-error'>";
                echo "<h2>📋 Page Not Found</h2>";
                echo "<p>The page '<strong>" . htmlspecialchars($page) . "</strong>' does not exist.</p>";
                echo "<p><a href='?page=dashboard' class='btn'>🏠 Go to Dashboard</a></p>";
                echo "</div>";
            } else {
                if (empty($pageData['code'])) {
                    echo "<div class='alert alert-warning'>This page has no content configured.</div>";
                } else {
                    // Execute page code with error handling
                    try {
                        // Clean the code
                        $code = $pageData['code'];
                        $code = str_replace('[...]', '', $code);
                        $code = preg_replace('/^\s*<\?php\s*/', '', $code);
                        $code = preg_replace('/\s*\?\>\s*$/', '', $code);
                        
                        if ($debugMode) {
                            echo "<div class='alert alert-info'><small>Executing page: $page</small></div>";
                        }
                        
                        // Execute with output buffering
                        ob_start();
                        $startTime = microtime(true);
                        
                        eval($code);
                        
                        $executionTime = microtime(true) - $startTime;
                        $output = ob_get_clean();
                        
                        echo $output;
                        
                        if ($debugMode) {
                            echo "<div class='alert alert-info'><small>Execution time: " . round($executionTime * 1000, 2) . "ms</small></div>";
                        }
                        
                    } catch (ParseError $e) {
                        ob_end_clean();
                        DB::logError('Parse Error', $e->getMessage(), $page);
                        
                        echo "<div class='alert alert-error'>";
                        echo "<strong>🔧 Syntax Error:</strong> " . htmlspecialchars($e->getMessage());
                        echo "<br><strong>Line:</strong> " . $e->getLine();
                        
                        if ($debugMode) {
                            echo "<br><small>Run the database fixer: <a href='fix_database_code.php' target='_blank'>fix_database_code.php</a></small>";
                        }
                        
                        echo "</div>";
                        
                    } catch (Error $e) {
                        ob_end_clean();
                        DB::logError('Fatal Error', $e->getMessage(), $page);
                        
                        echo "<div class='alert alert-error'>";
                        echo "<strong>💥 Fatal Error:</strong> " . htmlspecialchars($e->getMessage());
                        echo "</div>";
                        
                    } catch (Exception $e) {
                        ob_end_clean();
                        DB::logError('Runtime Error', $e->getMessage(), $page);
                        
                        echo "<div class='alert alert-error'>";
                        echo "<strong>⚠️ Runtime Error:</strong> " . htmlspecialchars($e->getMessage());
                        echo "</div>";
                    }
                }
            }
            ?>
        </main>
    </div>
    
    <script>
        // Add CSRF tokens to forms
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                if (!form.querySelector('input[name="csrf_token"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'csrf_token';
                    input.value = '<?php echo $_SESSION["csrf_token"] ?? bin2hex(random_bytes(16)); ?>';
                    form.appendChild(input);
                }
            });
        });
    </script>
</body>
</html>
