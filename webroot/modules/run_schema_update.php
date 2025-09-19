<?php
/**
 * Run GST Accounting Full SQL Schema Update
 * Executes sql_database/gst_accounting_full.sql on the configured database.
 * Place this file in modules/ and access via ?module=run_schema_update
 */

require_once __DIR__ . '/../con.php'; // Uses your DB config

session_start();
$_SESSION['is_admin'] = true; // TEMP: for testing only

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_schema'])) {
    $sql_file = __DIR__ . '/../sql_database/gst_accounting_full.sql';
    if (!file_exists($sql_file)) {
        $message = 'SQL schema file not found!';
    } else {
        $sql = file_get_contents($sql_file);
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec($sql);
            $message = '✅ Database schema updated successfully!';
        } catch (PDOException $e) {
            $message = '❌ Error executing schema: ' . $e->getMessage();
        }
    }
}
?>
<h2>🛠️ Run GST Accounting Schema Update</h2>
<?php if ($message): ?>
    <div style="padding:12px;background:#222;margin-bottom:12px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<form method="post">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf']) ?>">
    <button type="submit" name="run_schema" style="padding:10px 24px;font-size:16px;background:#4caf50;color:#fff;border:none;border-radius:4px;">Run Schema Update</button>
</form>
<p style="margin-top:16px;color:#aaa;">This will execute <b>sql_database/gst_accounting_full.sql</b> on your configured database. <br>Take a backup before proceeding.</p>
