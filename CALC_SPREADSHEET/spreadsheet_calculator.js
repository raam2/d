// --- Evaluation engine ---
function evaluateCell(expr, getValue) {
  if (expr === null || expr === undefined) return '';
  expr = String(expr).trim();

  // Accept formulas with or without '='
  if (expr.startsWith('=')) expr = expr.slice(1);

  // Pure number shortcut
  if (/^\s*[-+]?\d+(?:\.\d+)?\s*$/.test(expr)) return Number(expr);

  // Parse cell ref like A1, AA12
  const parseCellRef = (ref) => {
    const m = ref.match(/^([A-Z]+)(\d+)$/i);
    if (!m) return null;
    let col = 0;
    const letters = m[1].toUpperCase();
    for (let i = 0; i < letters.length; i++) col = col * 26 + (letters.charCodeAt(i) - 64);
    const row = parseInt(m[2], 10) - 1;
    return { row, col: col - 1 };
  };

  // [op(...)] blocks: + - * /
  expr = expr.replace(/\[\s*([+\-*/])\s*\(\s*([^\[\]]+?)\s*\)\s*\]/g, (match, op, inner) => {
    const parts = inner.split(/\s*,\s*/);
    const values = [];
    for (const part of parts) {
      const rangeMatch = part.match(/^([A-Z]+\d+)\s*:\s*([A-Z]+\d+)$/);
      if (rangeMatch) {
        const s = parseCellRef(rangeMatch[1]);
        const e = parseCellRef(rangeMatch[2]);
        if (!s || !e) continue;
        const r1 = Math.min(s.row, e.row), r2 = Math.max(s.row, e.row);
        const c1 = Math.min(s.col, e.col), c2 = Math.max(s.col, e.col);
        for (let r = r1; r <= r2; r++) {
          for (let c = c1; c <= c2; c++) {
            let v = getValue(r, c);
            if (typeof v === 'string') v = evaluateCell(v, getValue);
            if (typeof v === 'number' && Number.isFinite(v)) values.push(v);
          }
        }
      } else {
        const ref = parseCellRef(part);
        if (!ref) continue;
        let v = getValue(ref.row, ref.col);
        if (typeof v === 'string') v = evaluateCell(v, getValue);
        if (typeof v === 'number' && Number.isFinite(v)) values.push(v);
      }
    }
    if (values.length === 0) return 'Invalid';
    if (op === '+') return values.reduce((a, b) => a + b, 0);
    if (op === '*') return values.reduce((a, b) => a * b, 1);
    if (op === '-') return values.reduce((a, b) => a - b);
    if (op === '/') return values.reduce((a, b) => a / b);
    return 'Invalid';
  });

  // Power operator
  expr = expr.replace(/(\w+|\d+(?:\.\d+)?)\s*\^\s*(\w+|\d+(?:\.\d+)?)/g, (m, a, b) => `Math.pow(${a},${b})`);

  // Replace cell refs with values (fallback 0)
  expr = expr.replace(/\b([A-Z]+)(\d+)\b/g, (m, col, row) => {
    let c = 0;
    for (let i = 0; i < col.length; i++) c = c * 26 + (col.charCodeAt(i) - 64);
    const r = parseInt(row, 10) - 1;
    const v = getValue(r, c - 1);
    return (typeof v === 'number' && Number.isFinite(v)) ? String(v) : '0';
  });

  // Evaluate arithmetic with precedence and parentheses
  try {
    const result = Function('return (' + expr + ')')();
    if (!Number.isFinite(result)) return 'Invalid';
    return result;
  } catch {
    return 'Invalid';
  }
}

// --- Recalc and bindings ---
function recalc() {
  const inputs = document.querySelectorAll('#sheet input');
  const resultsCache = new Map(); // <-- FIX: Cache to prevent infinite recursion

  function getValue(r, c) {
    const key = `${r},${c}`;
    if (resultsCache.has(key)) {
      return resultsCache.get(key);
    }

    const inp = document.querySelector(`#sheet input[data-row="${r}"][data-col="${c}"]`);
    const val = inp ? inp.value : '';
    
    // To prevent infinite loops (e.g., A1=B1, B1=A1), temporarily mark this cell as being calculated.
    resultsCache.set(key, ''); 

    const result = evaluateCell(val, getValue);
    resultsCache.set(key, result); // Store final result in cache
    return result;
  }

  inputs.forEach(inp => {
    const r = +inp.dataset.row;
    const c = +inp.dataset.col;
    const res = getValue(r, c);
    const div = inp.nextElementSibling;
    if (res === 'Invalid') {
      div.innerHTML = '<span style="color:#b00020">Invalid</span>';
    } else {
      div.textContent = res;
    }
  });
}

function bindInputs() {
  const inputs = document.querySelectorAll('#sheet input');
  inputs.forEach(inp => {
    inp.addEventListener('input', recalc);
    inp.addEventListener('keydown', e => {
      const r = +inp.dataset.row;
      const c = +inp.dataset.col;
      let target = null;
      if (e.key === 'ArrowDown') target = document.querySelector(`input[data-row="${r+1}"][data-col="${c}"]`);
      else if (e.key === 'ArrowUp') target = document.querySelector(`input[data-row="${r-1}"][data-col="${c}"]`);
      else if (e.key === 'ArrowRight') target = document.querySelector(`input[data-row="${r}"][data-col="${c+1}"]`);
      else if (e.key === 'ArrowLeft') target = document.querySelector(`input[data-row="${r}"][data-col="${c-1}"]`);
      if (target) { e.preventDefault(); target.focus(); }
    });
  });
}

// --- Persistence and copy/paste ---
function copyCellsToHidden(form) {
  const inputs = document.querySelectorAll('#sheet input');
  const data = {};
  inputs.forEach(inp => {
    data[`${inp.dataset.row}_${inp.dataset.col}`] = inp.value;
  });
  form.cellsData.value = JSON.stringify(data);
}

function copyRange() {
  const range = document.getElementById('copyRange').value.trim();
  const m = range.match(/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/);
  if (!m) return;
  const c1 = m[1], r1 = parseInt(m[2], 10), c2 = m[3], r2 = parseInt(m[4], 10);
  const colToIndex = s => [...s].reduce((a, ch) => a * 26 + (ch.charCodeAt(0) - 64), 0) - 1;
  const x1 = colToIndex(c1), x2 = colToIndex(c2);
  const y1 = r1 - 1, y2 = r2 - 1;

  let rows = [], maxCols = 0;
  for (let r = y1; r <= y2; r++) {
    const row = [];
    for (let c = x1; c <= x2; c++) {
      const inp = document.querySelector(`input[data-row="${r}"][data-col="${c}"]`);
      row.push(inp ? inp.value : '');
    }
    maxCols = Math.max(maxCols, row.length);
    rows.push(row);
  }
  document.getElementById('clipboardPreview').value = rows.map(r => r.join(',')).join('\n');
  document.getElementById('previewRows').textContent = rows.length;
  document.getElementById('previewCols').textContent = maxCols;
}

function pasteRange() {
  const at = document.getElementById('pasteAt').value.trim();
  const m = at.match(/^([A-Z]+)(\d+)$/);
  if (!m) return;

  const colToIndex = s => [...s].reduce((a, ch) => a * 26 + (ch.charCodeAt(0) - 64), 0) - 1;
  const x = colToIndex(m[1]), y = parseInt(m[2], 10) - 1;

  const lines = document.getElementById('clipboardPreview').value.split('\n');
  for (let r = 0; r < lines.length; r++) {
    const cells = lines[r].split(',');
    for (let c = 0; c < cells.length; c++) {
      const inp = document.querySelector(`input[data-row="${y+r}"][data-col="${x+c}"]`);
      if (inp) inp.value = cells[c];
    }
  }
  recalc();
}

// --- File I/O ---
document.addEventListener('DOMContentLoaded', () => {
  // Ensure elements exist before binding
  const fileInput = document.getElementById('fileInput');
  const sheetFileInput = document.getElementById('sheetFileInput');

  if (fileInput) {
    fileInput.addEventListener('change', e => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        let text = reader.result.trim();
        document.getElementById('clipboardPreview').value = text;
        const lines = text.split('\n');
        document.getElementById('previewRows').textContent = lines.length;
        document.getElementById('previewCols').textContent = Math.max(...lines.map(l => l.split(',').length));
      };
      reader.readAsText(file);
    });
  }

  if (sheetFileInput) {
    sheetFileInput.addEventListener('change', e => {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => {
        let text = reader.result.trim();
        const lines = text.split('\n');
        const maxCols = Math.max(...lines.map(l => l.split(',').length));
        const form = document.querySelector('form[method="get"]');
        document.querySelector('input[name="rows"]').value = lines.length;
        document.querySelector('input[name="cols"]').value = maxCols;
        const data = {};
        for (let r = 0; r < lines.length; r++) {
          const cells = lines[r].split(',');
          for (let c = 0; c < cells.length; c++) data[`${r}_${c}`] = cells[c];
        }
        document.getElementById('cellsData').value = JSON.stringify(data);
        form.submit();
      };
      reader.readAsText(file);
    });
  }

  // Initialize after DOM is ready
  bindInputs();
  recalc();
});

// Helper to correctly escape a cell for CSV format
function escapeCsvCell(cell) {
  if (cell == null) return '';
  cell = String(cell);
  // If the cell contains a comma, a double-quote, or a newline, enclose it in double-quotes.
  // Also, escape any double-quotes within the cell by doubling them.
  if (cell.includes(',') || cell.includes('"') || cell.includes('\n')) {
    return `"${cell.replace(/"/g, '""')}"`;
  }
  return cell;
}

// Save clipboard preview to file
function savePreview() {
  const content = document.getElementById('clipboardPreview').value;
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'clipboard_preview.csv';
  a.click();
}

// Save entire sheet to file (CSV)
function saveSheet() {
  const rows = +document.querySelector('input[name="rows"]').value;
  const cols = +document.querySelector('input[name="cols"]').value;
  const lines = [];
  for (let r = 0; r < rows; r++) {
    const row = [];
    for (let c = 0; c < cols; c++) {
      const inp = document.querySelector(`input[data-row="${r}"][data-col="${c}"]`);
      row.push(escapeCsvCell(inp ? inp.value : ''));
    }
    lines.push(row.join(','));
  }
  const content = lines.join('\n');
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'spreadsheet_data.csv';
  a.click();
}
