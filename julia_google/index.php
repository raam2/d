<?php
// --- Configuration ---
$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'gst_accounting';
$db_user = 'gstwork';
$db_pass = 'gstwork@123';
$db_char = 'utf8mb4';

// --- Database Connection ---
$dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=$db_char";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (\PDOException $e) {
    http_response_code(503);
    echo "<h1>Database Connection Error</h1><p>Could not connect to the database. Please check the configuration.</p>";
    error_log("DB Connection Error: " . $e->getMessage());
    exit;
}

// --- URL Routing ---
$page_name = $_GET['page'] ?? 'dashboard';
$page_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $page_name);

// --- Database-Driven Page Rendering ---

// 1. Fetch the page from the database
$stmt = $pdo->prepare("SELECT id, code FROM Pages WHERE name = ? LIMIT 1");
$stmt->execute([$page_name]);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    echo "<h1>404 - Page Not Found</h1>";
    echo "<p>The page '<strong>" . htmlspecialchars($page_name) . "</strong>' could not be found in the database.</p>";
    exit;
}

$page_id = $page['id'];
$page_code = $page['code'];

// 2. Fetch associated CSS
$css_stmt = $pdo->prepare("
    SELECT cf.code
    FROM CSS_Files cf
    JOIN Page_CSS pc ON cf.id = pc.css_id
    WHERE pc.page_id = ?
");
$css_stmt->execute([$page_id]);
$css_files = $css_stmt->fetchAll();

// 3. Fetch associated JS
$js_stmt = $pdo->prepare("
    SELECT jf.code
    FROM JS_Files jf
    JOIN Page_JS pj ON jf.id = pj.js_id
    WHERE pj.page_id = ?
");
$js_stmt->execute([$page_id]);
$js_files = $js_stmt->fetchAll();

// --- Dynamic Page Assembly ---

// Start output buffering to capture the evaluated PHP code's output
ob_start();

// Execute the page code. The $pdo variable is available to it.
try {
    // The '?>' is important to exit PHP mode before the page's code,
    // which might start with HTML.
    eval('?>' . $page_code);
} catch (ParseError $e) {
    ob_end_clean();
    http_response_code(500);
    echo "<h1>Execution Error</h1><p>There is a syntax error in the page code from the database.</p>";
    error_log("EVAL PARSE ERROR in page '$page_name': " . $e->getMessage());
    exit;
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo "<h1>Execution Error</h1><p>An error occurred while executing the page code.</p>";
    error_log("EVAL RUNTIME ERROR in page '$page_name': " . $e->getMessage());
    exit;
}

$page_content = ob_get_clean();

// --- Final HTML Output ---
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(ucfirst($page_name)); ?></title>
    <?php
    if (!empty($css_files)) {
        echo "<style>\n";
        foreach ($css_files as $css) {
            echo "/* CSS from database */\n";
            echo $css['code'] . "\n\n";
        }
        echo "</style>\n";
    }
    ?>
</head>
<body>

<?php echo $page_content; ?>

<?php
if (!empty($js_files)) {
    echo "<script>\n";
    echo "// JS from database\n";
    foreach ($js_files as $js) {
        echo $js['code'] . "\n\n";
    }
    echo "</script>\n";
}
?>

</body>
</html>
