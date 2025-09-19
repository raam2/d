<?php
/**
 * Settings Module
 * Allows admin to set the organization state code (used for GST split logic)
 */

require_once __DIR__ . '/../lib/database.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['org_state'])) {
    $state = strtoupper(trim($_POST['org_state']));
    if (!preg_match('/^[A-Z]{2}$/', $state)) {
        $message = "Invalid state code. Use two letters, e.g. UP, DL, MH.";
        $message_type = "err";
    } else {
        set_setting('org_state', $state);
        $message = "Organization state code saved as <b>" . htmlspecialchars($state) . "</b>.";
        $message_type = "ok";
    }
}

$current_state = get_setting('org_state', 'UP');
?>

<h2>⚙️ Organization Settings</h2>

<?php if ($message): ?>
    <div class="<?= $message_type ?>">
        <?= $message ?>
    </div>
<?php endif; ?>

<div style="background: #2d2d2d; padding: 20px; border-radius: 6px; max-width: 500px;">
    <form method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf']) ?>">
        <label>
            <b>Organization State Code (for GST)</b>
            <input type="text" name="org_state" maxlength="2" style="width:120px" value="<?= htmlspecialchars($current_state) ?>" required>
            <small>Example: UP, DL, MH, RJ</small>
        </label>
        <div style="margin-top:12px">
            <button type="submit" class="button">Save Settings</button>
        </div>
    </form>
</div>

<div style="margin-top:30px; color:#aaa;">
    <p>
        <b>Note:</b> The state code determines whether GST is split as CGST/SGST (intra-state) or IGST (inter-state) on invoices.
        <br>Change this only if your organization moves or is registered in a different state.
    </p>
</div>

<style>
.button {
    display: inline-block;
    background: #4CAF50;
    color: white;
    padding: 8px 16px;
    text-decoration: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    border: none;
}
.button:hover { background: #45a049; }
.ok {
    background: #1b5e20;
    color: #a5d6a7;
    padding: 10px;
    border-radius: 4px;
    border-left: 4px solid #4CAF50;
    margin-bottom: 15px;
}
.err {
    background: #b71c1c;
    color: #ffcdd2;
    padding: 10px;
    border-radius: 4px;
    border-left: 4px solid #f44336;
    margin-bottom: 15px;
}
label {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-weight: 500;
}
input[type="text"] {
    background: #333;
    color: #e0e0e0;
    border: 1px solid #555;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 14px;
}
input[type="text"]:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
}
small { color: #bbb; font-size: 12px; }
</style>
