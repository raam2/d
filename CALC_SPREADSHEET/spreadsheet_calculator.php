<?php
// Persist previous sheet values across resizes via a hidden JSON field.
$oldData = [];
if (isset($_REQUEST['cellsData'])) {
    $decoded = json_decode($_REQUEST['cellsData'], true);
    if (is_array($decoded)) $oldData = $decoded;
}

// Grid size (defaults)
$rows = isset($_REQUEST['rows']) ? max(1, (int)$_REQUEST['rows']) : 8;
$cols = isset($_REQUEST['cols']) ? max(1, (int)$_REQUEST['cols']) : 8;

// Convert zero-based column index to spreadsheet label (A, B, ... Z, AA, AB, ...)
function colLabel($index) {
    $label = '';
    $index = (int)$index;
    while (true) {
        $label = chr(($index % 26) + 65) . $label;
        if ($index < 26) break;
        $index = (int)($index / 26) - 1;
    }
    return $label;
}

// --- NEW: Fulfill user requirements ---
$currentUser = 'raam2';
$utcTime = gmdate('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Spreadsheet Calculator</title>
<link rel="stylesheet" href="spreadsheet_calculator.css">
</head>
<body>

<!-- NEW: Display user info at the top -->
<div style="background-color: #2c2c2c; padding: 10px; margin-bottom: 15px; border-radius: 4px; font-family: monospace;">
  <div><strong>Time (UTC):</strong> <?php echo $utcTime; ?></div>
  <div><strong>User:</strong> <?php echo htmlspecialchars($currentUser); ?></div>
  <div><strong>GitHub Activity:</strong> <a href="https://github.com/raam2/d" target="_blank" style="color: #9cdcfe;">raam2/d</a></div>
</div>

<h2>Spreadsheet Calculator</h2>

<!-- Resize controls: preserves current values using hidden JSON -->
<form method="get" onsubmit="copyCellsToHidden(this)">
  <label>Rows:
    <input type="number" name="rows" value="<?php echo $rows; ?>" min="1">
  </label>
  <label>Cols:
    <input type="number" name="cols" value="<?php echo $cols; ?>" min="1">
  </label>
  <input type="hidden" id="cellsData" name="cellsData">
  <button type="submit">Resize</button>
</form>

<br>

<!-- Main sheet -->
<form method="post" id="sheetForm">
  <input type="hidden" name="rows" value="<?php echo $rows; ?>">
  <input type="hidden" name="cols" value="<?php echo $cols; ?>">

  <table id="sheet">
    <tr>
      <th></th>
      <?php for ($c = 0; $c < $cols; $c++): ?>
        <th><?php echo colLabel($c); ?></th>
      <?php endfor; ?>
    </tr>

    <?php for ($r = 0; $r < $rows; $r++): ?>
      <tr>
        <th><?php echo $r + 1; ?></th>
        <?php for ($c = 0; $c < $cols; $c++):
          $key = $r . "_" . $c;
          $val = isset($oldData[$key]) ? $oldData[$key] : '';
        ?>
          <td>
            <input type="text"
                   name="cell[<?php echo $r; ?>][<?php echo $c; ?>]"
                   data-row="<?php echo $r; ?>"
                   data-col="<?php echo $c; ?>"
                   value="<?php echo htmlspecialchars($val); ?>">
            <div class="result"></div>
          </td>
        <?php endfor; ?>
      </tr>
    <?php endfor; ?>
  </table>
</form>

<!-- Explicit, transparent copy/paste and import/export panel -->
<div id="copyPastePanel">
  <div>
    <label>Copy range:
      <input type="text" id="copyRange" placeholder="A1:B3">
    </label>
    <button type="button" onclick="copyRange()">Copy</button>
  </div>

  <div style="margin-top:6px;">
    <label>Paste at:
      <input type="text" id="pasteAt" placeholder="C5">
    </label>
    <button type="button" onclick="pasteRange()">Paste</button>
  </div>

  <div style="margin-top:8px;">
    <label>Clipboard preview:</label><br>
    <textarea id="clipboardPreview" rows="8"></textarea>
    <div>Rows: <span id="previewRows">0</span> | Cols: <span id="previewCols">0</span></div>
  </div>

  <div style="margin-top:6px;">
    <label>Load clipboard from file:</label><br>
    <input type="file" id="fileInput" accept=".csv,.tsv,.txt">
  </div>

  <div style="margin-top:6px;">
    <button type="button" onclick="savePreview()">Save preview</button>
    <button type="button" onclick="saveSheet()">Save entire sheet</button>
  </div>

  <div style="margin-top:6px;">
    <label>Import entire sheet:</label><br>
    <input type="file" id="sheetFileInput" accept=".csv,.tsv,.txt">
  </div>
</div>

<script src="spreadsheet_calculator.js"></script>
</body>
</html>
