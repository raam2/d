Based on your database structure, I can see this is a comprehensive GST accounting system. Let me create the complete `home.php` file that will work with your database-driven architecture where all business logic is stored in the database.

```php name=home.php
<?php
/**
 * GST Accounting System - Single File Frontend
 * All business logic executed from database-stored PHP code
 * Compatible with Indian GST compliance requirements
 */

// Database Configuration
class DB {
    private static $pdo = null;
    
    public static function conn() {
        if (self::$pdo === null) {
            try {
                $dsn = "mysql:host=127.0.0.1;dbname=gst_accounting;charset=utf8mb4";
                $username = "gstwork"; // Update with your DB username
                $password = "gstwork@123"; // Update with your DB password
                
                self::$pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ]);
            } catch (PDOException $e) {
                self::logError('Database Connection', $e->getMessage());
                die('<div style="color:red;padding:20px;">Database connection failed. Please check configuration.</div>');
            }
        }
        return self::$pdo;
    }
    
    public static function logError($context, $message, $page = null) {
        try {
            $pdo = self::conn();
            $stmt = $pdo->prepare("INSERT INTO error_logs (page_name, message, context) VALUES (?, ?, ?)");
            $stmt->execute([$page ?: 'system', $message, $context]);
        } catch (Exception $e) {
            error_log("Failed to log error: " . $e->getMessage());
        }
    }
}

// Security Functions
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validate_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
            $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die('<div style="color:red;">CSRF token validation failed.</div>');
        }
    }
}

function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Main Application Class
class GSTAccountingApp {
    private $pdo;
    private $currentPage;
    
    public function __construct() {
        session_start();
        $this->pdo = DB::conn();
        $this->currentPage = sanitize_input($_GET['page'] ?? 'dashboard');
    }
    
    public function run() {
        try {
            // Check if debug mode is enabled
            $debug_mode = $this->getSetting('debug_mode') === 'on';
            if ($debug_mode) {
                error_reporting(E_ALL);
                ini_set('display_errors', 1);
            }
            
            // Validate CSRF for POST requests
            validate_csrf_token();
            
            // Render the application
            $this->renderApplication();
            
        } catch (Exception $e) {
            DB::logError('Application Error', $e->getMessage(), $this->currentPage);
            $this->renderError('Application Error: ' . $e->getMessage());
        }
    }
    
    private function getSetting($key) {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    }
    
    private function renderApplication() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>GST Accounting System</title>
            <?php $this->renderCSS(); ?>
            <style>
                /* Base Application Styles */
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body { 
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
                    background: #f5f7fa; 
                    color: #333; 
                    line-height: 1.6; 
                }
                
                .header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 1rem 2rem;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                
                .header h1 { display: inline-block; margin-right: 2rem; }
                .header .user-info { float: right; margin-top: 0.5rem; }
                
                .container {
                    display: flex;
                    min-height: calc(100vh - 80px);
                }
                
                .sidebar {
                    width: 250px;
                    background: white;
                    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
                    padding: 1rem;
                    overflow-y: auto;
                }
                
                .main-content {
                    flex: 1;
                    padding: 2rem;
                    background: white;
                    margin: 1rem;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                    overflow-y: auto;
                }
                
                .menu-group {
                    margin-bottom: 1.5rem;
                }
                
                .menu-group h3 {
                    color: #666;
                    font-size: 0.9rem;
                    text-transform: uppercase;
                    margin-bottom: 0.5rem;
                    border-bottom: 2px solid #eee;
                    padding-bottom: 0.3rem;
                }
                
                .menu-item {
                    display: block;
                    padding: 0.7rem 1rem;
                    color: #555;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-bottom: 0.3rem;
                    transition: all 0.3s ease;
                }
                
                .menu-item:hover, .menu-item.active {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    transform: translateX(5px);
                }
                
                .alert {
                    padding: 1rem;
                    margin: 1rem 0;
                    border-radius: 5px;
                    border-left: 4px solid;
                }
                
                .alert-success { 
                    background: #d4edda; 
                    color: #155724; 
                    border-color: #28a745; 
                }
                
                .alert-error { 
                    background: #f8d7da; 
                    color: #721c24; 
                    border-color: #dc3545; 
                }
                
                .alert-warning { 
                    background: #fff3cd; 
                    color: #856404; 
                    border-color: #ffc107; 
                }
                
                .btn {
                    display: inline-block;
                    padding: 0.7rem 1.5rem;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    border: none;
                    cursor: pointer;
                    font-size: 0.9rem;
                    transition: transform 0.2s ease;
                }
                
                .btn:hover { transform: translateY(-2px); }
                .btn-secondary { background: #6c757d; }
                .btn-success { background: #28a745; }
                .btn-danger { background: #dc3545; }
                
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 1rem 0;
                    background: white;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                }
                
                th, td {
                    padding: 0.8rem;
                    text-align: left;
                    border-bottom: 1px solid #eee;
                }
                
                th {
                    background: #f8f9fa;
                    font-weight: 600;
                    color: #555;
                }
                
                tr:hover { background: #f8f9fa; }
                
                .form-group {
                    margin-bottom: 1rem;
                }
                
                .form-group label {
                    display: block;
                    margin-bottom: 0.3rem;
                    font-weight: 500;
                    color: #555;
                }
                
                .form-control {
                    width: 100%;
                    padding: 0.7rem;
                    border: 2px solid #eee;
                    border-radius: 5px;
                    font-size: 0.9rem;
                    transition: border-color 0.3s ease;
                }
                
                .form-control:focus {
                    outline: none;
                    border-color: #667eea;
                    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
                }
                
                .clearfix::after {
                    content: "";
                    display: table;
                    clear: both;
                }
                
                .footer {
                    text-align: center;
                    padding: 1rem;
                    color: #666;
                    border-top: 1px solid #eee;
                    margin-top: 2rem;
                }
                
                @media (max-width: 768px) {
                    .container { flex-direction: column; }
                    .sidebar { width: 100%; }
                    .header { padding: 1rem; }
                    .main-content { margin: 0.5rem; padding: 1rem; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>📊 GST Accounting System</h1>
                <div class="user-info">
                    <span>Welcome, Admin</span> | 
                    <span><?php echo date('d M Y, H:i'); ?></span>
                </div>
                <div class="clearfix"></div>
            </div>
            
            <div class="container">
                <nav class="sidebar">
                    <?php $this->renderMenu(); ?>
                </nav>
                
                <main class="main-content">
                    <?php $this->renderPageContent(); ?>
                </main>
            </div>
            
            <div class="footer">
                <p>&copy; <?php echo date('Y'); ?> GST Accounting System. All rights reserved.</p>
            </div>
            
            <?php $this->renderJavaScript(); ?>
        </body>
        </html>
        <?php
    }
    
    private function renderMenu() {
        try {
            $stmt = $this->pdo->query("
                SELECT name, menu_label, menu_group, menu_order 
                FROM Pages 
                WHERE menu_label IS NOT NULL 
                ORDER BY menu_group, menu_order, menu_label
            ");
            
            $menuItems = $stmt->fetchAll();
            $menuGroups = [];
            
            foreach ($menuItems as $item) {
                $group = $item['menu_group'] ?: 'Other';
                $menuGroups[$group][] = $item;
            }
            
            foreach ($menuGroups as $groupName => $items) {
                echo "<div class='menu-group'>";
                echo "<h3>{$groupName}</h3>";
                
                foreach ($items as $item) {
                    $active = ($this->currentPage === $item['name']) ? 'active' : '';
                    echo "<a href='?page=" . urlencode($item['name']) . "' class='menu-item {$active}'>";
                    echo htmlspecialchars($item['menu_label']);
                    echo "</a>";
                }
                
                echo "</div>";
            }
            
        } catch (Exception $e) {
            DB::logError('Menu Rendering', $e->getMessage());
            echo "<div class='alert-error'>Error loading menu</div>";
        }
    }
    
    private function renderPageContent() {
        try {
            // Get page details
            $stmt = $this->pdo->prepare("SELECT * FROM Pages WHERE name = ?");
            $stmt->execute([$this->currentPage]);
            $page = $stmt->fetch();
            
            if (!$page) {
                $this->render404();
                return;
            }
            
            // Execute the PHP code from database
            if (!empty($page['code'])) {
                // Add CSRF token for forms
                echo "<input type='hidden' name='csrf_token' value='" . generate_csrf_token() . "' id='csrf_token'>";
                
                // Execute the stored PHP code
                eval('?>' . $page['code']);
            } else {
                echo "<div class='alert-warning'>No content available for this page.</div>";
            }
            
        } catch (ParseError $e) {
            DB::logError('Page Parse Error', $e->getMessage(), $this->currentPage);
            $this->renderError('Page parsing error: ' . $e->getMessage());
        } catch (Exception $e) {
            DB::logError('Page Execution Error', $e->getMessage(), $this->currentPage);
            $this->renderError('Page execution error: ' . $e->getMessage());
        }
    }
    
    private function renderCSS() {
        try {
            // Get page-specific CSS
            $stmt = $this->pdo->prepare("
                SELECT cf.code 
                FROM CSS_Files cf
                JOIN Page_CSS pc ON cf.id = pc.css_id
                JOIN Pages p ON pc.page_id = p.id
                WHERE p.name = ?
            ");
            $stmt->execute([$this->currentPage]);
            
            $cssFiles = $stmt->fetchAll();
            
            if (!empty($cssFiles)) {
                echo "<style type='text/css'>";
                foreach ($cssFiles as $css) {
                    echo $css['code'] . "\n";
                }
                echo "</style>";
            }
            
        } catch (Exception $e) {
            DB::logError('CSS Rendering', $e->getMessage(), $this->currentPage);
        }
    }
    
    private function renderJavaScript() {
        try {
            // Get page-specific JavaScript
            $stmt = $this->pdo->prepare("
                SELECT jf.code 
                FROM JS_Files jf
                JOIN Page_JS pj ON jf.id = pj.js_id
                JOIN Pages p ON pj.page_id = p.id
                WHERE p.name = ?
            ");
            $stmt->execute([$this->currentPage]);
            
            $jsFiles = $stmt->fetchAll();
            
            if (!empty($jsFiles)) {
                echo "<script type='text/javascript'>";
                echo "document.addEventListener('DOMContentLoaded', function() {";
                
                foreach ($jsFiles as $js) {
                    echo $js['code'] . "\n";
                }
                
                // Add CSRF token to all AJAX requests
                echo "
                    // Add CSRF token to all forms and AJAX requests
                    const csrfToken = document.getElementById('csrf_token')?.value;
                    if (csrfToken) {
                        // Add to all forms
                        document.querySelectorAll('form').forEach(function(form) {
                            if (!form.querySelector('input[name=\"csrf_token\"]')) {
                                const csrfInput = document.createElement('input');
                                csrfInput.type = 'hidden';
                                csrfInput.name = 'csrf_token';
                                csrfInput.value = csrfToken;
                                form.appendChild(csrfInput);
                            }
                        });
                        
                        // Override XMLHttpRequest for AJAX requests
                        const originalSend = XMLHttpRequest.prototype.send;
                        XMLHttpRequest.prototype.send = function(data) {
                            if (data instanceof FormData) {
                                data.append('csrf_token', csrfToken);
                            }
                            return originalSend.call(this, data);
                        };
                    }
                ";
                
                echo "});";
                echo "</script>";
            }
            
        } catch (Exception $e) {
            DB::logError('JavaScript Rendering', $e->getMessage(), $this->currentPage);
        }
    }
    
    private function render404() {
        echo "<div class='alert-error'>";
        echo "<h2>Page Not Found</h2>";
        echo "<p>The requested page '<strong>" . htmlspecialchars($this->currentPage) . "</strong>' was not found.</p>";
        echo "<p><a href='?page=dashboard' class='btn'>Go to Dashboard</a></p>";
        echo "</div>";
    }
    
    private function renderError($message) {
        echo "<div class='alert-error'>";
        echo "<h3>System Error</h3>";
        echo "<p>" . htmlspecialchars($message) . "</p>";
        echo "<p>The error has been logged for investigation.</p>";
        echo "</div>";
    }
}

// Initialize and run the application
try {
    $app = new GSTAccountingApp();
    $app->run();
} catch (Exception $e) {
    DB::logError('Application Fatal Error', $e->getMessage());
    echo "<div style='color:red;padding:20px;'>Fatal application error. Please contact administrator.</div>";
}
?>
```

## Analysis of Your Database - Incomplete Work Identified:

Based on your database structure, here are the **incomplete areas** that need attention:

### 1. **Missing Core Tables** (Referenced but not found):
- `invoices` table - Main invoice header (referenced by `invoice_items`)
- `items` table - Product/service master (referenced by `invoice_items`, `inventory_movements`)
- `parties` table - Customer/supplier master (referenced in Pages code)
- `journal_entries` & `journal_lines` - General ledger (referenced by bank reconciliation)

### 2. **Empty Critical Tables**:
- Most tables are created but have no data or minimal data
- `bank_statements` & `bank_reconciliation` tables are empty
- `inventory_movements` & `inventory_valuations` are empty
- `diagnostics` table is empty

### 3. **Incomplete Page Implementations**:
- Several pages in the `Pages` table have truncated code (`[...]`)
- Missing complete implementations for:
  - Bank reconciliation workflows
  - Inventory management pages
  - Financial reporting pages
  - GST return preparation pages

### 4. **Missing Essential Features**:
- User authentication and role management
- Audit trail mechanisms
- Data backup/restore procedures
- Multi-company support (if needed)

## What the `home.php` Will Do:

✅ **Single-file frontend** that reads all business logic from database
✅ **Dynamic page rendering** from `Pages` table
✅ **CSS/JS injection** from database-stored files  
✅ **Error logging** to database
✅ **Security features** (CSRF protection, input sanitization)
✅ **Responsive design** with modern UI
✅ **Database-driven menu** system
✅ **GST compliance** ready structure

## Is Everything Working After `home.php`?

The `home.php` file I created will work with your existing database structure, but you'll need to:

1. **Complete the missing tables** (invoices, items, parties, journal_entries, etc.)
2. **Finish the truncated PHP code** in the `Pages` table
3. **Add sample data** for testing
4. **Configure database connection** parameters

Would you like me to:
1. Create the missing database tables?
2. Complete the page implementations?
3. Add sample data for testing?
4. Fix any specific functionality you need?

The application framework is solid, but the business logic completion depends on finishing the database structure and page implementations.
