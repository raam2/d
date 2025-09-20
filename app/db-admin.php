<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ROOT', __DIR__);
require_once APP_ROOT . '/config/database.php';

// --- Main Router ---
$action = $_GET['action'] ?? 'show_tables';
$db_name = $_GET['db'] ?? null;

try {
    $conn = Database::getConnection();
    switch ($action) {
        case 'view_table':
            if (!$db_name) throw new Exception("Database not specified.");
            handle_html_view($conn, $db_name, $_GET['table']);
            break;
        case 'export':
            if (!$db_name) throw new Exception("Database not specified.");
            handle_export($conn, $db_name, $_GET['tables'] ?? '', $_GET['format'] ?? 'csv');
            break;
        case 'get_distinct_values':
            if (!$db_name) throw new Exception("Database not specified.");
            handle_get_distinct_values($conn, $db_name, $_GET['table'], $_GET['column']);
            break;
        case 'show_tables':
        default:
            show_table_list_page($conn, $db_name);
            break;
    }
} catch (Exception $e) {
    display_error_page("An error occurred", $e->getMessage());
}
// --- End Main Router ---


/**
 * Renders an HTML page to view a single table's data with filters.
 */
function handle_html_view($conn, $db_name, $table) {
    if (!is_valid_table_name($table)) throw new Exception("Invalid table name for view.");
    
    $data = $conn->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    $columns = empty($data) ? [] : array_keys($data[0]);

    render_page_start("View Table: " . htmlspecialchars($table), true);
    ?>
    <!-- CHANGED: Added target="_blank" and rel attribute -->
    <a href="?db=<?php echo htmlspecialchars($db_name); ?>" class="back-link" target="_blank" rel="noopener noreferrer">&larr; Back to Table List</a>
    <h1>Viewing Table: <strong><?php echo htmlspecialchars($table); ?></strong></h1>
    
    <?php if (empty($data)): ?>
        <p>No data found in this table.</p>
    <?php else: ?>
        <div class="table-wrapper">
            <table id="data-table">
                <thead>
                    <tr class="columns-row">
                        <?php foreach ($columns as $col): ?>
                            <th data-column="<?php echo htmlspecialchars($col); ?>"><?php echo htmlspecialchars($col); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="filters-row">
                        <?php foreach ($columns as $col): ?>
                            <th>
                                <input type="text" class="column-filter-input" placeholder="Filter <?php echo htmlspecialchars($col); ?>..."
                                       data-column="<?php echo htmlspecialchars($col); ?>"
                                       data-table="<?php echo htmlspecialchars($table); ?>"
                                       data-db="<?php echo htmlspecialchars($db_name); ?>">
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                        <tr><?php foreach ($row as $cell): ?><td><?php echo htmlspecialchars((string)$cell); ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif;
    render_page_end(true);
}

/**
 * Displays a list of all tables in the database with action links.
 */
function show_table_list_page($conn, $db_name) {
    render_page_start("DB Admin");

    if (!$db_name) {
        echo '<p class="usage-info">Please specify a database in the URL to see its tables. Example: <code>?db=gst_accounting</code></p>';
        render_page_end();
        return;
    }

    echo "<h2>Tables in database: <strong>" . htmlspecialchars($db_name) . "</strong></h2>";
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "<p>No tables found in this database.</p>";
    } else {
        echo '<ul class="table-list">';
        foreach ($tables as $table) {
            $view_link = "?" . http_build_query(['db' => $db_name, 'action' => 'view_table', 'table' => $table]);
            $csv_link = "?" . http_build_query(['db' => $db_name, 'action' => 'export', 'tables' => $table, 'format' => 'csv']);
            $sql_link = "?" . http_build_query(['db' => $db_name, 'action' => 'export', 'tables' => $table, 'format' => 'sql']);
            echo '<li><span>' . htmlspecialchars($table) . '</span><div class="links">';
            // CHANGED: Added target="_blank" and rel attribute to all three links
            echo "<a href='{$view_link}' class='action-link view' target='_blank' rel='noopener noreferrer'>View</a>";
            echo "<a href='{$csv_link}' class='action-link dump-csv' target='_blank' rel='noopener noreferrer'>Export CSV</a>";
            echo "<a href='{$sql_link}' class='action-link dump-sql' target='_blank' rel='noopener noreferrer'>Export SQL</a>";
            echo '</div></li>';
        }
        echo '</ul>';
        
        $all_tables_str = implode(',', $tables);
        $all_csv_link = "?" . http_build_query(['db' => $db_name, 'action' => 'export', 'tables' => $all_tables_str, 'format' => 'csv']);
        $all_sql_link = "?" . http_build_query(['db' => $db_name, 'action' => 'export', 'tables' => $all_tables_str, 'format' => 'sql']);
        
        echo '<div class="global-actions">';
        echo '<h3>Database-Wide Actions</h3>';
        // CHANGED: Added target="_blank" and rel attribute to both links
        echo "<a href='{$all_csv_link}' class='action-link dump-csv' target='_blank' rel='noopener noreferrer'>Export All Tables as Single CSV</a>";
        echo "<a href='{$all_sql_link}' class='action-link dump-sql' target='_blank' rel='noopener noreferrer'>Export All Tables as SQL</a>";
        echo '</div>';
    }

    render_page_end();
}

/**
 * API ACTION: Gets distinct values for a column to populate filters.
 */
function handle_get_distinct_values($conn, $db_name, $table, $column) {
    if (!is_valid_table_name($table) || !is_valid_table_name($column)) {
        throw new Exception("Invalid table or column name.");
    }
    $stmt = $conn->prepare("SELECT DISTINCT `{$column}` FROM `{$table}` ORDER BY `{$column}` ASC");
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $items = [];
    foreach ($results as $value) {
        $items[] = ['value' => $value];
    }
    header('Content-Type: application/json');
    echo json_encode(['items' => $items]);
    exit();
}

/**
 * Handles all data exports (CSV, SQL, single/multi-table).
 */
function handle_export($conn, $db_name, $tables_str, $format) {
    $tables = array_filter(explode(',', $tables_str));
    if (empty($tables)) throw new Exception("No tables specified for export.");
    foreach ($tables as $table) {
        if (!is_valid_table_name($table)) throw new Exception("Invalid table name in list: " . htmlspecialchars($table));
    }
    $filename = $db_name . (count($tables) > 1 ? '_all' : '_' . $tables[0]) . '.' . $format;
    if ($format === 'sql') {
        header('Content-Type: application/sql; charset=utf-8');
    } else {
        header('Content-Type: text/csv; charset=utf-8');
    }
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    if (ob_get_level()) ob_end_clean();
    $output = fopen('php://output', 'w');
    foreach ($tables as $i => $table) {
        if ($format === 'sql') {
            fwrite($output, "--\n-- Structure for table `{$table}`\n--\n\n");
            $create_table_stmt = $conn->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            fwrite($output, $create_table_stmt['Create Table'] . ";\n\n");
            fwrite($output, "--\n-- Dumping data for table `{$table}`\n--\n\n");
            $data_stmt = $conn->query("SELECT * FROM `{$table}`");
            while ($row = $data_stmt->fetch(PDO::FETCH_ASSOC)) {
                $cols = array_keys($row);
                $vals = array_map(function($val) use ($conn) { return $val === null ? 'NULL' : $conn->quote($val); }, array_values($row));
                fwrite($output, "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`) VALUES (" . implode(', ', $vals) . ");\n");
            }
            fwrite($output, "\n");
        } else {
            $data = $conn->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (empty($data)) continue;
            if (count($tables) > 1) {
                fputcsv($output, ["TABLE: {$table}"]);
            }
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            if (count($tables) > 1) {
                fputcsv($output, []);
            }
        }
    }
    fclose($output);
    exit();
}

// --- HTML & Utility Functions ---

function is_valid_table_name($name) { return preg_match('/^[a-zA-Z0-9_]+$/', $name); }

function render_page_start($title, $include_scripts = false) {
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>" . htmlspecialchars($title) . "</title><link rel='stylesheet' href='assets/css/db-admin.css'>";
    if ($include_scripts) {
        echo "<link href='https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css' rel='stylesheet'>";
    }
    echo "</head><body><div class='container'>";
}

function render_page_end($include_scripts = false) {
    echo "</div>";
    if ($include_scripts) {
        echo "<script src='https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js'></script>";
        echo "<script src='assets/js/db-admin.js'></script>";
    }
    echo "</body></html>";
}

function display_error_page($title, $message) {
    render_page_start("Error");
    echo "<h1>" . htmlspecialchars($title) . "</h1><p class='error'>" . htmlspecialchars($message) . "</p>";
    render_page_end();
}
?>
