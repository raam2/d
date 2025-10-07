<?php
/**
 * Main Entry Point
 * Serves all pages, CSS, and JS from database
 */

require_once __DIR__ . '/db.php';

// Helper function for safe HTML output
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// Get PDO connection
$pdo = DB::conn();

// Handle CSS requests
if (isset($_GET['css'])) {
    header('Content-Type: text/css; charset=utf-8');
    $css_name = $_GET['css'];
    
    $stmt = $pdo->prepare("SELECT code FROM CSS_Files WHERE name = ?");
    $stmt->execute([$css_name]);
    $css = $stmt->fetchColumn();
    
    if ($css) {
        echo $css;
    } else {
        http_response_code(404);
        echo "/* CSS file not found */";
    }
    exit;
}

// Handle JS requests
if (isset($_GET['js'])) {
    header('Content-Type: application/javascript; charset=utf-8');
    $js_name = $_GET['js'];
    
    $stmt = $pdo->prepare("SELECT code FROM JS_Files WHERE name = ?");
    $stmt->execute([$js_name]);
    $js = $stmt->fetchColumn();
    
    if ($js) {
        echo $js;
    } else {
        http_response_code(404);
        echo "// JS file not found";
    }
    exit;
}

// Get requested page (default to dashboard)
$page_name = $_GET['page'] ?? 'dashboard';

// Fetch page from database
$stmt = $pdo->prepare("SELECT id, name, code, menu_label FROM Pages WHERE name = ?");
$stmt->execute([$page_name]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    $page_content = '<div class="container"><h1>404 - Page Not Found</h1><p>The requested page does not exist.</p><p><a href="?page=dashboard">Return to Dashboard</a></p></div>';
    $page_title = 'Page Not Found';
} else {
    $page_title = $page['menu_label'] ?? $page['name'];
    
    // Render page code
    ob_start();
    eval('?>' . $page['code']);
    $page_content = ob_get_clean();
}

// Fetch menu items
$menu_stmt = $pdo->query("
    SELECT name, menu_label, menu_group, menu_order 
    FROM Pages 
    WHERE menu_label IS NOT NULL 
    ORDER BY menu_group, menu_order, menu_label
");
$menu_items = $menu_stmt->fetchAll();

// Group menu items
$menu_groups = [];
foreach ($menu_items as $item) {
    $group = $item['menu_group'] ?? 'Other';
    if (!isset($menu_groups[$group])) {
        $menu_groups[$group] = [];
    }
    $menu_groups[$group][] = $item;
}

// Fetch CSS files for this page
$css_stmt = $pdo->prepare("
    SELECT cf.name 
    FROM Page_CSS pc 
    JOIN CSS_Files cf ON pc.css_id = cf.id 
    WHERE pc.page_id = ?
");
$css_stmt->execute([$page['id'] ?? 0]);
$css_files = $css_stmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch JS files for this page
$js_stmt = $pdo->prepare("
    SELECT jf.name 
    FROM Page_JS pj 
    JOIN JS_Files jf ON pj.js_id = jf.id 
    WHERE pj.page_id = ?
");
$js_stmt->execute([$page['id'] ?? 0]);
$js_files = $js_stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($page_title); ?> - GST Accounting</title>
    
    <?php foreach ($css_files as $css_file): ?>
    <link rel="stylesheet" href="?css=<?php echo urlencode($css_file); ?>">
    <?php endforeach; ?>
    
    <style>
    /* Inline dark theme base styles */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
        background-color: #1e1e1e;
        color: #d4d4d4;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        line-height: 1.6;
        padding: 0;
    }
    h1, h2, h3 { color: #ffffff; margin-bottom: 1rem; }
    a { color: #9cdcfe; text-decoration: none; }
    a:hover { text-decoration: underline; }
    
    .header {
        background-color: #252526;
        border-bottom: 1px solid #333;
        padding: 1rem 2rem;
    }
    .header h1 {
        font-size: 1.5rem;
        margin-bottom: 0.5rem;
    }
    
    .nav {
        background-color: #2d2d30;
        padding: 0.75rem 2rem;
        border-bottom: 1px solid #333;
    }
    .nav-group {
        display: inline-block;
        margin-right: 2rem;
    }
    .nav-group-title {
        color: #858585;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }
    .nav-group a {
        display: inline-block;
        margin-right: 1rem;
        font-size: 0.95rem;
    }
    
    .main-content {
        padding: 2rem;
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        background-color: #252526;
        padding: 2rem;
        border-radius: 8px;
        border: 1px solid #333;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin: 1rem 0;
        background-color: #1e1e1e;
    }
    table th {
        background-color: #2d2d30;
        color: #fff;
        padding: 0.75rem;
        text-align: left;
        border: 1px solid #444;
    }
    table td {
        padding: 0.5rem 0.75rem;
        border: 1px solid #444;
    }
    table tr:hover {
        background-color: #2d2d30;
    }
    
    input, textarea, select, button {
        background-color: #333;
        color: #fff;
        border: 1px solid #555;
        padding: 0.5rem;
        border-radius: 4px;
        font-family: inherit;
    }
    button {
        cursor: pointer;
        background-color: #0e639c;
        border-color: #0e639c;
        padding: 0.5rem 1rem;
    }
    button:hover {
        background-color: #1177bb;
    }
    label {
        display: inline-block;
        margin-bottom: 0.25rem;
        color: #ccc;
    }
    
    .menu { margin-bottom: 1rem; }
    .menu a { margin-right: 1rem; }
    </style>
</head>
<body>
    <div class="header">
        <h1>GST Accounting System</h1>
        <p style="color: #858585; font-size: 0.9rem;">Indian Business Accounting with GST Compliance</p>
    </div>
    
    <nav class="nav">
        <?php foreach ($menu_groups as $group => $items): ?>
        <div class="nav-group">
            <div class="nav-group-title"><?php echo e($group); ?></div>
            <div>
                <?php foreach ($items as $item): ?>
                <a href="?page=<?php echo urlencode($item['name']); ?>"><?php echo e($item['menu_label']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </nav>
    
    <div class="main-content">
        <?php echo $page_content; ?>
    </div>
    
    <?php foreach ($js_files as $js_file): ?>
    <script src="?js=<?php echo urlencode($js_file); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
