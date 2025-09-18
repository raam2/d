<?php
require 'bootstrap.php';
// $pdo अब bootstrap.php से उपलब्ध है

$table = $_GET['table'] ?? '';
$pk = $_GET['pk'] ?? '';

if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    http_response_code(400);
    exit('Invalid table name.');
}

// टेबल का अस्तित्व जांचें
$chk = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
");
$chk->execute([':t' => $table]);
if (!$chk->fetchColumn()) {
    http_response_code(404);
    exit('Table not found.');
}

// कॉलम मेटाडेटा
$cols = $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);

// प्राइमरी की कॉलम ढूंढें
$pkCol = null;
foreach ($cols as $col) {
    if ($col['Key'] === 'PRI') {
        $pkCol = $col['Field'];
        break;
    }
}
if ($pkCol === null) {
    $pkCol = $cols[0]['Field'] ?? 'id';
}

// POST request - Update करें
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sets = [];
    $params = [];
    
    foreach ($cols as $c) {
        $col = $c['Field'];
        $value = $_POST[$col] ?? null;
        
        // Empty string को NULL में convert करें यदि column nullable है
        if ($value === '' && $c['Null'] === 'YES') {
            $value = null;
        }
        
        $sets[] = "`{$col}` = :{$col}";
        $params[":{$col}"] = $value;
    }
    
    $params[':pk'] = $_POST['original_pk'];
    
    $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE `{$pkCol}` = :pk";
    
    try {
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            $message = "Record updated successfully!";
            // Updated PK value के लिए redirect
            $newPk = $_POST[$pkCol] ?? $_POST['original_pk'];
            header("Location: table_view.php?table=" . urlencode($table) . "&message=" . urlencode($message));
            exit;
        } else {
            $error = "Update failed.";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// GET request - रिकॉर्ड लोड करें
$stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$pkCol}` = ?");
$stmt->execute([$pk]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('Record not found.');
}

// HTML helper function
function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Edit Record - <?= h($table) ?></title>
  <style>
    body { font-family: Arial, sans-serif; margin: 40px; background-color: #f5f5f5; }
    .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    h2 { color: #333; margin-bottom: 20px; }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
    input[type="text"], input[type="number"], input[type="email"], input[type="date"], input[type="datetime-local"], textarea, select {
      width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;
    }
    textarea { height: 80px; resize: vertical; }
    .btn { padding: 10px 20px; margin: 10px 5px 0 0; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
    .btn-primary { background-color: #007bff; color: white; }
    .btn-secondary { background-color: #6c757d; color: white; text-decoration: none; display: inline-block; }
    .btn:hover { opacity: 0.9; }
    .error { color: #dc3545; background-color: #f8d7da; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    .success { color: #155724; background-color: #d4edda; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    .field-info { font-size: 12px; color: #666; margin-top: 3px; }
  </style>
</head>
<body>

<div class="container">
  <h2>Edit Record in <?= h($table) ?></h2>
  
  <?php if (isset($error)): ?>
    <div class="error"><?= h($error) ?></div>
  <?php endif; ?>
  
  <?php if (isset($_GET['message'])): ?>
    <div class="success"><?= h($_GET['message']) ?></div>
  <?php endif; ?>

  <form method="post">
    <?php foreach ($cols as $c): ?>
      <?php 
        $col = $c['Field'];
        $type = strtolower($c['Type']);
        $isNull = $c['Null'] === 'YES';
        $value = $row[$col] ?? '';
        
        // Input type based on column type
        $inputType = 'text';
        if (strpos($type, 'int') !== false || strpos($type, 'decimal') !== false || strpos($type, 'float') !== false) {
          $inputType = 'number';
        } elseif (strpos($type, 'date') !== false) {
          $inputType = 'date';
        } elseif (strpos($type, 'datetime') !== false || strpos($type, 'timestamp') !== false) {
          $inputType = 'datetime-local';
          if ($value && $value !== '0000-00-00 00:00:00') {
            $value = date('Y-m-d\TH:i', strtotime($value));
          } else {
            $value = '';
          }
        } elseif (strpos($type, 'email') !== false) {
          $inputType = 'email';
        }
      ?>
      
      <div class="form-group">
        <label for="<?= h($col) ?>">
          <?= h($col) ?>
          <?php if (!$isNull): ?>
            <span style="color: red;">*</span>
          <?php endif; ?>
        </label>
        
        <?php if (strpos($type, 'text') !== false || strpos($type, 'longtext') !== false): ?>
          <textarea name="<?= h($col) ?>" id="<?= h($col) ?>" <?= !$isNull ? 'required' : '' ?>><?= h($value) ?></textarea>
        <?php else: ?>
          <input type="<?= $inputType ?>" 
                 name="<?= h($col) ?>" 
                 id="<?= h($col) ?>" 
                 value="<?= h($value) ?>"
                 <?= !$isNull ? 'required' : '' ?>
                 <?= $col === $pkCol ? 'readonly style="background-color: #f8f9fa;"' : '' ?>>
        <?php endif; ?>
        
        <div class="field-info">
          Type: <?= h($c['Type']) ?> | 
          Null: <?= h($c['Null']) ?> |
          Key: <?= h($c['Key']) ?> |
          Default: <?= h($c['Default']) ?>
        </div>
      </div>
    <?php endforeach; ?>
    
    <input type="hidden" name="original_pk" value="<?= h($pk) ?>">
    
    <button type="submit" class="btn btn-primary">Update Record</button>
    <a href="table_view.php?table=<?= urlencode($table) ?>" class="btn btn-secondary">Cancel</a>
  </form>
</div>

</body>
</html>