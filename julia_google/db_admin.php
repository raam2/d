<?php
// --- Configuration & Connection ---
$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'gst_accounting';
$db_user = 'gstwork';
$db_pass = 'gstwork@123';
$db_char = 'utf8mb4';

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
    die("<h1>Database Connection Error</h1><p>Could not connect to the database. Please check the configuration.</p>");
}

// --- Helper Functions & Initial Setup ---
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

$base_url = 'db_admin.php';
$message = '';
session_start();

// --- Action Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table = $_POST['table'] ?? null;
    $action = $_POST['action'] ?? null;
    $record_id = $_POST['record_id'] ?? null;
    $primary_key = $_POST['primary_key'] ?? null;

    // Validate table name
    $all_tables_for_validation = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (!$table || !in_array($table, $all_tables_for_validation)) {
        $_SESSION['flash_message'] = "<p class='msg-error'>Invalid table specified for POST request.</p>";
        header("Location: $base_url");
        exit;
    }

    try {
        if ($action === 'save') {
            $schema_stmt = $pdo->prepare("SELECT COLUMN_NAME, EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $schema_stmt->execute([$table]);
            $schema_info = $schema_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $allowed_columns = array_keys($schema_info);

            // Filter POST data to only include keys that are actual columns
            $data = array_intersect_key($_POST, array_flip($allowed_columns));

            if ($record_id && $primary_key) { // UPDATE
                // Don't try to update the primary key itself
                unset($data[$primary_key]);
                
                $set_parts = [];
                foreach ($data as $key => $value) {
                    $set_parts[] = "`$key` = ?";
                }
                $set_clause = implode(', ', $set_parts);
                
                $sql = "UPDATE `$table` SET $set_clause WHERE `$primary_key` = ?";
                $params = array_values($data);
                $params[] = $record_id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $_SESSION['flash_message'] = "<p class='msg-success'>Record #$record_id in `$table` updated successfully.</p>";

            } else { // INSERT
                // If primary key is auto-increment, don't include it in insert if it's empty
                if ($primary_key && str_contains($schema_info[$primary_key], 'auto_increment') && empty($data[$primary_key])) {
                    unset($data[$primary_key]);
                }

                $columns = array_keys($data);
                $placeholders = array_fill(0, count($columns), '?');
                
                $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                $params = array_values($data);

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $new_id = $pdo->lastInsertId();
                $_SESSION['flash_message'] = "<p class='msg-success'>New record created in `$table` with ID #$new_id.</p>";
            }

        } elseif ($action === 'delete' && $record_id && $primary_key) { // DELETE
            $sql = "DELETE FROM `$table` WHERE `$primary_key` = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$record_id]);
            $_SESSION['flash_message'] = "<p class='msg-deleted'>Record #$record_id from `$table` deleted successfully.</p>";
        }
        
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "<p class='msg-error'>Database Error: " . e($e->getMessage()) . "</p>";
    }

    header("Location: $base_url?table=$table");
    exit;
}

// --- Display Logic ---
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

$all_tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$selected_table = $_GET['table'] ?? null;
$action = $_GET['action'] ?? 'list';
$record_id = $_GET['id'] ?? null;

if ($selected_table && !in_array($selected_table, $all_tables)) {
    $selected_table = null;
    $message = "<p class='msg-error'>Invalid table specified.</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generic Database Manager</title>
    <style>
        body { background: #1e1e1e; color: #d4d4d4; font-family: sans-serif; line-height: 1.6; padding: 0; margin: 0; }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .sidebar { position: fixed; top: 0; left: 0; width: 250px; height: 100vh; background: #252526; border-right: 1px solid #333; padding: 1rem; overflow-y: auto; }
        .main-content { margin-left: 270px; padding: 1rem; }
        h1, h2 { color: #fff; border-bottom: 1px solid #444; padding-bottom: 10px; }
        a { color: #9cdcfe; text-decoration: none; }
        a:hover { text-decoration: underline; }
        ul { list-style: none; padding: 0; }
        .sidebar-nav li a { display: block; padding: 8px 10px; border-radius: 4px; }
        .sidebar-nav li a:hover { background: #333; }
        .sidebar-nav li a.active { background: #094771; font-weight: bold; }
        .msg-success, .msg-deleted, .msg-error { padding: 10px; border-radius: 4px; margin-bottom: 1rem; }
        .msg-success { background: #27ae60; }
        .msg-deleted { background: #e74c3c; }
        .msg-error { background: #c0392b; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 8px 12px; border: 1px solid #333; text-align: left; vertical-align: top; }
        th { background: #3a3a3a; }
        tr:nth-child(even) { background: #2a2a2a; }
        .pagination a, .pagination span { margin: 0 5px; }
        .pagination .current-page { font-weight: bold; color: #fff; }
        .btn { display: inline-block; padding: 10px 20px; border: none; border-radius: 4px; color: white; cursor: pointer; text-decoration: none; font-size: 1rem; }
        .btn-create { background: #27ae60; }
        .btn-delete { background: none; border: none; color: #e74c3c; cursor: pointer; padding: 0; text-decoration: underline; font-size: inherit; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 10px; background: #333; color: #fff; border: 1px solid #555; border-radius: 4px; box-sizing: border-box; }
        textarea { min-height: 150px; }
        .form-group { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Tables</h2>
        <ul class="sidebar-nav">
            <?php foreach ($all_tables as $table): ?>
                <li><a href="<?php echo $base_url; ?>?table=<?php echo e($table); ?>" class="<?php echo ($table === $selected_table) ? 'active' : ''; ?>"><?php echo e($table); ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="main-content">
        <h1>Database Admin Panel</h1>
        <?php echo $message; ?>

        <?php if ($selected_table): ?>
            <?php
            $pk_stmt = $pdo->prepare("SELECT k.COLUMN_NAME FROM information_schema.table_constraints t JOIN information_schema.key_column_usage k USING(constraint_name,table_schema,table_name) WHERE t.constraint_type='PRIMARY KEY' AND t.table_schema=DATABASE() AND t.table_name=?");
            $pk_stmt->execute([$selected_table]);
            $primary_key = $pk_stmt->fetchColumn();
            ?>

            <?php if ($action === 'list'): ?>
                <?php
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $per_page = 25;
                $offset = ($page - 1) * $per_page;
                $total_records = (int)$pdo->query("SELECT COUNT(*) FROM `$selected_table`")->fetchColumn();
                $total_pages = ceil($total_records / $per_page);
                $columns = $pdo->query("DESCRIBE `$selected_table`")->fetchAll(PDO::FETCH_COLUMN);
                $data_stmt = $pdo->prepare("SELECT * FROM `$selected_table` LIMIT :limit OFFSET :offset");
                $data_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
                $data_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $data_stmt->execute();
                $records = $data_stmt->fetchAll();
                ?>
                <h2>Table: <strong><?php echo e($selected_table); ?></strong></h2>
                <p><a href="?table=<?php echo e($selected_table); ?>&action=create" class="btn btn-create">+ Create New Record</a></p>
                
                <table>
                    <thead><tr><?php foreach ($columns as $c): ?><th><?php echo e($c); ?></th><?php endforeach; ?><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <?php foreach ($record as $value): ?><td><?php echo e(mb_strimwidth((string)$value, 0, 100, "...")); ?></td><?php endforeach; ?>
                                <td style="white-space: nowrap;">
                                    <?php if ($primary_key && isset($record[$primary_key])): ?>
                                        <a href="?table=<?php echo e($selected_table); ?>&action=edit&id=<?php echo e($record[$primary_key]); ?>">Edit</a> |
                                        <form method="POST" action="<?php echo $base_url; ?>" style="display:inline;" onsubmit="return confirm('Are you sure?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="table" value="<?php echo e($selected_table); ?>">
                                            <input type="hidden" name="primary_key" value="<?php echo e($primary_key); ?>">
                                            <input type="hidden" name="record_id" value="<?php echo e($record[$primary_key]); ?>">
                                            <button type="submit" class="btn-delete">Delete</button>
                                        </form>
                                    <?php else: echo 'N/A'; endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="pagination" style="margin-top: 1rem;">
                    <?php if ($total_pages > 1): ?>
                        <?php if ($page > 1): ?><a href="?table=<?php echo e($selected_table); ?>&page=<?php echo $page - 1; ?>">&laquo; Prev</a><?php endif; ?>
                        <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                        <?php if ($page < $total_pages): ?><a href="?table=<?php echo e($selected_table); ?>&page=<?php echo $page + 1; ?>">Next &raquo;</a><?php endif; ?>
                    <?php endif; ?>
                </div>

            <?php elseif ($action === 'edit' || $action === 'create'): ?>
                <?php
                $schema_stmt = $pdo->prepare("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
                $schema_stmt->execute([$selected_table]);
                $schema = $schema_stmt->fetchAll();
                $record = null;
                if ($action === 'edit' && $primary_key && $record_id) {
                    $record_stmt = $pdo->prepare("SELECT * FROM `$selected_table` WHERE `$primary_key` = ?");
                    $record_stmt->execute([$record_id]);
                    $record = $record_stmt->fetch();
                }
                ?>
                <h2><?php echo ($action === 'edit') ? 'Editing Record in ' : 'Creating Record in '; ?><strong><?php echo e($selected_table); ?></strong></h2>
                <p><a href="?table=<?php echo e($selected_table); ?>">&larr; Back to table view</a></p>
                
                <form method="POST" action="<?php echo $base_url; ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="table" value="<?php echo e($selected_table); ?>">
                    <input type="hidden" name="primary_key" value="<?php echo e($primary_key); ?>">
                    <input type="hidden" name="record_id" value="<?php echo e($record_id); ?>">

                    <?php foreach($schema as $column): ?>
                        <?php
                        $is_pk = ($column['COLUMN_NAME'] === $primary_key);
                        $is_autoincrement = str_contains($column['EXTRA'], 'auto_increment');
                        if ($is_pk && $is_autoincrement && $action === 'create') continue;
                        $is_readonly = $is_pk && $action === 'edit';
                        ?>
                        <div class="form-group">
                            <label><?php echo e($column['COLUMN_NAME']); ?></label>
                            <?php
                            $value = $record[$column['COLUMN_NAME']] ?? $column['COLUMN_DEFAULT'];
                            if (str_contains($column['COLUMN_TYPE'], 'text')) {
                                echo "<textarea name='" . e($column['COLUMN_NAME']) . "'" . ($is_readonly ? ' readonly' : '') . ">" . e($value) . "</textarea>";
                            } else {
                                echo "<input type='text' name='" . e($column['COLUMN_NAME']) . "' value='" . e($value) . "'" . ($is_readonly ? ' readonly' : '') . ">";
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-create">Save Record</button>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <h2>Welcome</h2>
            <p>Please select a table from the left-hand menu to view its contents.</p>
        <?php endif; ?>
    </div>
</body>
</html>
