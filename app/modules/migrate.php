<?php
$message = '';
$error = '';

if ($_POST['migrate'] ?? false) {
    try {
        // Read the SQL file
        $sql_file = 'database/gst_accounting_all.sql';
        if (!file_exists($sql_file)) {
            throw new Exception("SQL file not found: $sql_file");
        }
        
        $sql_content = file_get_contents($sql_file);
        
        // Split into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $sql_content)),
            function($stmt) {
                return !empty($stmt) && !str_starts_with(trim($stmt), '--');
            }
        );
        
        $db->beginTransaction();
        
        $executed = 0;
        foreach ($statements as $statement) {
            if (trim($statement)) {
                $db->exec($statement);
                $executed++;
            }
        }
        
        $db->commit();
        $message = "Database migration completed successfully! Executed $executed statements.";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Migration failed: " . $e->getMessage();
    }
}

// Check current database status
$tables_exist = false;
try {
    $stmt = $db->query("SHOW TABLES LIKE 'accounts'");
    $tables_exist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $error = "Cannot check database status: " . $e->getMessage();
}
?>

<div class="page-header">
    <h2>Database Setup</h2>
    <span class="text-muted">Initialize accounting tables and data</span>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Database Migration</h3>
    </div>
    
    <?php if ($tables_exist): ?>
        <div class="alert alert-success">
            ✓ Accounting tables already exist in the database.
        </div>
        
        <p>The following tables are already set up:</p>
        <ul>
            <li><strong>accounts</strong> - Chart of accounts</li>
            <li><strong>journal_entries</strong> - Journal entry headers</li>
            <li><strong>journal_lines</strong> - Journal entry details</li>
            <li><strong>bank_reconciliation</strong> - Bank reconciliation records</li>
            <li><strong>app_settings</strong> - Application settings</li>
        </ul>
        
        <p>You can now use the accounting system. If you need to reset the database, use the button below:</p>
        
        <form method="post" onsubmit="return confirm('This will recreate all tables and may cause data loss. Are you sure?');">
            <input type="hidden" name="migrate" value="1">
            <button type="submit" class="btn btn-danger">Reset Database (Caution: Data Loss)</button>
        </form>
        
    <?php else: ?>
        <div class="alert alert-warning">
            Database tables are not set up. Click the button below to create the required accounting tables.
        </div>
        
        <p>This will create the following tables with sample data:</p>
        <ul>
            <li><strong>accounts</strong> - Complete chart of accounts for Indian GST accounting</li>
            <li><strong>journal_entries</strong> - Double-entry journal system</li>
            <li><strong>journal_lines</strong> - Individual debit/credit entries</li>
            <li><strong>bank_reconciliation</strong> - Bank reconciliation system</li>
            <li><strong>app_settings</strong> - Application configuration</li>
        </ul>
        
        <form method="post">
            <input type="hidden" name="migrate" value="1">
            <button type="submit" class="btn btn-primary">Setup Database Now</button>
        </form>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Database Information</h3>
    </div>
    
    <div class="form-row">
        <div class="form-group">
            <label>Host:</label>
            <input type="text" class="form-control" value="127.0.0.1" readonly>
        </div>
        <div class="form-group">
            <label>Database:</label>
            <input type="text" class="form-control" value="gst_accounting" readonly>
        </div>
        <div class="form-group">
            <label>User:</label>
            <input type="text" class="form-control" value="gstwork" readonly>
        </div>
    </div>
    
    <p class="text-muted">
        Database connection is configured in <code>config/database.php</code>
    </p>
</div>

<?php if ($tables_exist): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Current Database Statistics</h3>
    </div>
    
    <?php
    try {
        // Get table counts
        $tables = ['accounts', 'journal_entries', 'journal_lines'];
        $stats = [];
        
        foreach ($tables as $table) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
            $stats[$table] = $stmt->fetch()['count'];
        }
    ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['accounts'] ?></div>
                <div class="stat-label">Chart of Accounts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['journal_entries'] ?></div>
                <div class="stat-label">Journal Entries</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['journal_lines'] ?></div>
                <div class="stat-label">Journal Lines</div>
            </div>
        </div>
        
    <?php
    } catch (Exception $e) {
        echo '<div class="alert alert-error">Cannot load statistics: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
</div>
<?php endif; ?>