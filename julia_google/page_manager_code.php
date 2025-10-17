<?php
// This is the self-contained code for the Page Manager.
// It will be inserted into the `Pages` table.
// It assumes the $pdo variable is available, as provided by index.php.

// --- Security & Configuration ---
// Helper function for rendering safe HTML
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

$base_url = '?page=page_manager';

// --- Action Handling ---
$action = $_GET['action'] ?? 'list'; // Default action is to list everything
$type = $_GET['type'] ?? null;
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$message = '';

// --- Handle POST requests for saving/creating/deleting ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['action'] ?? '';
    $post_type = $_POST['type'] ?? '';
    $post_id = isset($_POST['id']) ? (int)$_POST['id'] : null;

    try {
        if ($post_action === 'save') {
            $table_name = '';
            if ($post_type === 'page') $table_name = 'Pages';
            if ($post_type === 'css') $table_name = 'CSS_Files';
            if ($post_type === 'js') $table_name = 'JS_Files';

            if ($table_name) {
                if ($post_id) { // Update existing
                    $stmt = $pdo->prepare("UPDATE `$table_name` SET `name` = ?, `code` = ? WHERE `id` = ?");
                    $stmt->execute([$_POST['name'], $_POST['code'], $post_id]);
                    $message = "<p style='color: #2ecc71;'>Successfully updated $post_type #$post_id.</p>";
                } else { // Create new
                    $stmt = $pdo->prepare("INSERT INTO `$table_name` (`name`, `code`) VALUES (?, ?)");
                    $stmt->execute([$_POST['name'], $_POST['code']]);
                    $new_id = $pdo->lastInsertId();
                    $message = "<p style='color: #2ecc71;'>Successfully created $post_type #$new_id.</p>";
                }
            }
        } elseif ($post_action === 'delete') {
            $table_name = '';
            if ($post_type === 'page') $table_name = 'Pages';
            if ($post_type === 'css') $table_name = 'CSS_Files';
            if ($post_type === 'js') $table_name = 'JS_Files';

            if ($table_name && $post_id) {
                // You might want to add checks here to prevent deleting linked items
                $stmt = $pdo->prepare("DELETE FROM `$table_name` WHERE `id` = ?");
                $stmt->execute([$post_id]);
                $message = "<p style='color: #e74c3c;'>Successfully deleted $post_type #$post_id.</p>";
            }
        }
    } catch (PDOException $e) {
        $message = "<p style='color: #e74c3c;'>Database Error: " . e($e->getMessage()) . "</p>";
    }
    // Redirect to the list view after a POST action to prevent form re-submission
    header("Location: $base_url&message=" . urlencode(base64_encode($message)));
    exit;
}

if (isset($_GET['message'])) {
    $message = base64_decode($_GET['message']);
}

// --- Display Logic ---
?>
<div class="container">
    <h1>Page Content Manager</h1>
    <div class="menu">
        <a href="?page=dashboard">Back to Dashboard</a>
    </div>

    <?php echo $message; ?>

    <?php if ($action === 'list'): ?>
        <!-- LIST VIEW -->
        <div style="display: flex; gap: 2rem;">
            <div style="flex: 1;">
                <h2>Pages</h2>
                <a href="<?php echo $base_url; ?>&action=edit&type=page">Create New Page</a>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($pdo->query("SELECT id, name FROM Pages ORDER BY name") as $row): ?>
                        <li><a href="<?php echo $base_url; ?>&action=edit&type=page&id=<?php echo $row['id']; ?>"><?php echo e($row['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div style="flex: 1;">
                <h2>CSS Files</h2>
                <a href="<?php echo $base_url; ?>&action=edit&type=css">Create New CSS</a>
                <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($pdo->query("SELECT id, name FROM CSS_Files ORDER BY name") as $row): ?>
                        <li><a href="<?php echo $base_url; ?>&action=edit&type=css&id=<?php echo $row['id']; ?>"><?php echo e($row['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div style="flex: 1;">
                <h2>JS Files</h2>
                <a href="<?php echo $base_url; ?>&action=edit&type=js">Create New JS</a>
                 <ul style="list-style: none; padding-left: 0;">
                    <?php foreach ($pdo->query("SELECT id, name FROM JS_Files ORDER BY name") as $row): ?>
                        <li><a href="<?php echo $base_url; ?>&action=edit&type=js&id=<?php echo $row['id']; ?>"><?php echo e($row['name']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

    <?php elseif ($action === 'edit' && $type): ?>
        <!-- EDIT/CREATE VIEW -->
        <?php
        $item = null;
        $table_name = '';
        if ($type === 'page') $table_name = 'Pages';
        if ($type === 'css') $table_name = 'CSS_Files';
        if ($type === 'js') $table_name = 'JS_Files';

        if ($id && $table_name) {
            $stmt = $pdo->prepare("SELECT * FROM `$table_name` WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
        }
        $page_title = $id ? "Editing $type #" . e($id) : "Creating New $type";
        ?>
        <h2><?php echo $page_title; ?></h2>
        <a href="<?php echo $base_url; ?>">&larr; Back to list</a>
        
        <form action="<?php echo $base_url; ?>" method="POST" style="margin-top: 1rem;">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="type" value="<?php echo e($type); ?>">
            <input type="hidden" name="id" value="<?php echo e($id); ?>">
            
            <div style="margin-bottom: 1rem;">
                <label for="name" style="display: block; margin-bottom: 5px;">Name:</label>
                <input type="text" id="name" name="name" value="<?php echo e($item['name'] ?? ''); ?>" required style="width: 100%; padding: 8px; background: #333; color: #fff; border: 1px solid #555;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label for="code" style="display: block; margin-bottom: 5px;">Code:</label>
                <textarea id="code" name="code" rows="25" style="width: 100%; padding: 8px; background: #333; color: #fff; border: 1px solid #555; font-family: monospace;"><?php echo e($item['code'] ?? ''); ?></textarea>
            </div>
            <div>
                <button type="submit" style="padding: 10px 20px; background: #2ecc71; color: white; border: none; cursor: pointer;">Save Changes</button>
            </div>
        </form>

        <?php if ($id): ?>
        <form action="<?php echo $base_url; ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this?');" style="margin-top: 2rem;">
             <input type="hidden" name="action" value="delete">
             <input type="hidden" name="type" value="<?php echo e($type); ?>">
             <input type="hidden" name="id" value="<?php echo e($id); ?>">
             <button type="submit" style="padding: 10px 20px; background: #e74c3c; color: white; border: none; cursor: pointer;">Delete <?php echo e($type); ?></button>
        </form>
        <?php endif; ?>

    <?php endif; ?>
</div>
