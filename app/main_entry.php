<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/db.php';

// -----------------------------------------------------------------------------
// Helper functions
// -----------------------------------------------------------------------------

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatBoolean($value): string
{
    if ($value === null) {
        return '—';
    }
    return ((int)$value) === 1 ? 'Yes' : 'No';
}

function renderTable(array $rows, array $columns, string $emptyText): string
{
    if (empty($rows)) {
        return '<p class="muted">' . h($emptyText ?: 'No records found.') . '</p>';
    }

    $thead = '<tr>';
    foreach ($columns as $col) {
        $thead .= '<th>' . h($col['label'] ?? $col['field']) . '</th>';
    }
    $thead .= '</tr>';

    $tbody = '';
    foreach ($rows as $row) {
        $tbody .= '<tr>';
        foreach ($columns as $col) {
            $field = $col['field'] ?? '';
            $format = $col['format'] ?? null;
            $raw = $row[$field] ?? '';
            if ($format === 'boolean') {
                $cell = formatBoolean($raw);
            } else {
                $cell = (string)$raw;
            }
            $tbody .= '<td>' . h($cell) . '</td>';
        }
        $tbody .= '</tr>';
    }

    return '<table><thead>' . $thead . '</thead><tbody>' . $tbody . '</tbody></table>';
}

function renderStat(array $rows, array $columns, string $emptyText): string
{
    if (empty($rows)) {
        return '<p class="muted">' . h($emptyText ?: 'No data yet.') . '</p>';
    }

    $cards = '';
    foreach ($rows as $row) {
        $titleField = $columns[0]['field'] ?? array_key_first($row);
        $valueField = $columns[1]['field'] ?? null;
        $title = $row[$titleField] ?? '';
        $value = $valueField ? ($row[$valueField] ?? '') : '';
        $cards .= '<div class="stat-card"><div class="stat-title">' . h((string)$title) . '</div><div class="stat-value">' . h((string)$value) . '</div></div>';
    }

    return '<div class="stat-grid">' . $cards . '</div>';
}

function renderForm(array $component, array $meta, array $oldInput = []): string
{
    $fields = $meta['fields'] ?? [];
    $method = strtoupper($meta['method'] ?? 'POST');
    $html = '<form method="' . ($method === 'GET' ? 'get' : 'post') . '" class="form-card">';
    $html .= '<input type="hidden" name="__page" value="' . h($component['page_slug']) . '">';
    $html .= '<input type="hidden" name="__component" value="' . h($component['name']) . '">';

    foreach ($fields as $field) {
        $name = $field['name'];
        $label = $field['label'] ?? ucfirst(str_replace('_', ' ', $name));
        $type = $field['type'] ?? 'text';
        $default = $field['default'] ?? '';
        $value = $oldInput[$name] ?? ($_POST[$name] ?? $default);
        $required = !empty($field['required']) ? ' required' : '';
        $placeholder = isset($field['placeholder']) ? ' placeholder="' . h($field['placeholder']) . '"' : '';
        $maxlength = isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '';
        $pattern = isset($field['pattern']) ? ' pattern="' . h($field['pattern']) . '"' : '';
        $step = isset($field['step']) ? ' step="' . h((string)$field['step']) . '"' : '';

        $html .= '<label class="field">';
        $html .= '<span>' . h($label) . '</span>';

        if ($type === 'textarea') {
            $html .= '<textarea name="' . h($name) . '"' . $required . $placeholder . $maxlength . $pattern . '>' . h((string)$value) . '</textarea>';
        } elseif ($type === 'select') {
            $html .= '<select name="' . h($name) . '"' . $required . '>';
            foreach ($field['options'] ?? [] as $option) {
                $optValue = (string)($option['value'] ?? '');
                $selected = ($optValue === (string)$value) ? ' selected' : '';
                $labelOpt = $option['label'] ?? $optValue;
                $html .= '<option value="' . h($optValue) . '"' . $selected . '>' . h((string)$labelOpt) . '</option>';
            }
            $html .= '</select>';
        } else {
            $html .= '<input type="' . h($type) . '" name="' . h($name) . '" value="' . h((string)$value) . '"' . $required . $placeholder . $maxlength . $pattern . $step . ' />';
        }

        $html .= '</label>';
    }

    $submitLabel = $meta['submitLabel'] ?? 'Save';
    $html .= '<button class="btn" type="submit">' . h($submitLabel) . '</button>';
    $html .= '</form>';

    return $html;
}

function renderAction(array $component, array $meta): string
{
    $html = '<form method="post" class="inline-form" onsubmit="return confirm(\'' . h($meta['confirm'] ?? 'Are you sure?') . '\');">';
    $html .= '<input type="hidden" name="__page" value="' . h($component['page_slug']) . '">';
    $html .= '<input type="hidden" name="__component" value="' . h($component['name']) . '">';

    foreach ($meta['params'] ?? [] as $param) {
        $html .= '<input type="text" name="' . h($param) . '" placeholder="' . h(ucfirst(str_replace('_', ' ', $param))) . '" required />';
    }

    $label = $meta['label'] ?? 'Run Action';
    $class = !empty($meta['danger']) ? 'btn btn-danger' : 'btn';
    $html .= '<button class="' . $class . '" type="submit">' . h($label) . '</button>';
    $html .= '</form>';

    return $html;
}

// -----------------------------------------------------------------------------
// Request handling
// -----------------------------------------------------------------------------

$pageSlug = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['__page'] ?? ($_GET['p'] ?? 'dashboard'))
    : ($_GET['p'] ?? 'dashboard');

$page = fetchOne('SELECT * FROM app_pages WHERE slug = ?', [$pageSlug]);
if (!$page) {
    $page = [
        'slug' => 'home',
        'title' => 'Home',
        'template' => '<div class="card"><h1>Home</h1><p class="muted">Define pages inside app_pages to render content.</p></div>',
        'page_type' => 'workspace',
    ];
}

$components = fetchAll('SELECT * FROM app_components WHERE page_slug = ? ORDER BY ord, id', [$page['slug']]);

$notice = $_GET['notice'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = $_POST['__component'] ?? '';
    foreach ($components as $component) {
        if ($component['name'] !== $target) {
            continue;
        }
        $meta = json_decode($component['meta_json'] ?? '{}', true) ?: [];
        try {
            if ($component['comp_type'] === 'form') {
                $fields = $meta['fields'] ?? [];
                $params = [];
                foreach ($fields as $field) {
                    $name = $field['name'];
                    $value = $_POST[$name] ?? null;
                    if ($value === '' && ($field['type'] ?? '') === 'number') {
                        $value = null;
                    }
                    $params[":" . $name] = $value;
                }
                q($component['sql_text'], $params);
                $notice = $meta['success'] ?? 'Saved successfully.';
            } elseif ($component['comp_type'] === 'action') {
                $params = [];
                foreach ($meta['params'] ?? [] as $param) {
                    $params[":" . $param] = $_POST[$param] ?? null;
                }
                q($component['sql_text'], $params);
                $notice = $meta['success'] ?? 'Action completed.';
            }
        } catch (Throwable $t) {
            $error = 'Database error: ' . $t->getMessage();
        }
        break;
    }
    if ($notice && !$error) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?p=' . urlencode($page['slug']) . '&notice=' . urlencode($notice));
        exit;
    }
}

$navPages = fetchAll('SELECT slug, title FROM app_pages ORDER BY title');

$componentOutput = [];
foreach ($components as $component) {
    $meta = json_decode($component['meta_json'] ?? '{}', true) ?: [];
    try {
        if ($component['comp_type'] === 'list') {
            $rows = fetchAll($component['sql_text']);
            $layout = $meta['layout'] ?? 'table';
            $columns = $meta['columns'] ?? [];
            $empty = $meta['emptyText'] ?? 'Nothing to display.';
            $componentOutput[$component['name']] = $layout === 'stat'
                ? renderStat($rows, $columns, $empty)
                : renderTable($rows, $columns, $empty);
        } elseif ($component['comp_type'] === 'form') {
            $componentOutput[$component['name']] = renderForm($component, $meta);
        } elseif ($component['comp_type'] === 'action') {
            $componentOutput[$component['name']] = renderAction($component, $meta);
        }
    } catch (Throwable $t) {
        $componentOutput[$component['name']] = '<div class="error">' . h($t->getMessage()) . '</div>';
    }
}

$template = $page['template'];
$template = str_replace('{{title}}', h($page['title']), $template);
$template = str_replace('{{slug}}', h($page['slug']), $template);

$template = preg_replace_callback('/{{component:([a-zA-Z0-9_-]+)}}/', function ($matches) use ($componentOutput) {
    $name = $matches[1];
    return $componentOutput[$name] ?? '<div class="muted">Component ' . h($name) . ' not found.</div>';
}, $template);

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title><?=h($page['title'])?> · Accounting Workspace</title>
    <style>
        :root { color-scheme: dark; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { margin: 0; background: #0b0c10; color: #e5e7eb; }
        header { background: #111827; border-bottom: 1px solid #1f2937; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; }
        header h1 { font-size: 1.1rem; margin: 0; }
        a { color: #60a5fa; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .layout { display: flex; min-height: calc(100vh - 54px); }
        .sidebar { width: 240px; background: #0f172a; border-right: 1px solid #1f2937; padding: 16px; }
        .sidebar h2 { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 10px; }
        .sidebar a { display: block; padding: 8px 10px; border-radius: 4px; color: #e5e7eb; margin-bottom: 4px; }
        .sidebar a.active { background: #1d4ed8; }
        .main { flex: 1; padding: 20px; overflow: auto; }
        .card { background: #0f172a; border: 1px solid #1f2937; border-radius: 8px; padding: 20px; margin-bottom: 18px; }
        .form-card { display: grid; gap: 12px; }
        .field { display: flex; flex-direction: column; gap: 4px; font-size: 0.92rem; }
        input, select, textarea { background: #0b1120; border: 1px solid #1f2937; border-radius: 6px; padding: 8px; color: inherit; }
        .btn { padding: 8px 14px; border-radius: 6px; border: 1px solid #2563eb; background: #1d4ed8; color: #fff; cursor: pointer; }
        .btn:hover { background: #1e40af; }
        .btn-danger { border-color: #dc2626; background: #b91c1c; }
        .btn-danger:hover { background: #991b1b; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { border: 1px solid #1f2937; padding: 8px 10px; text-align: left; }
        th { background: #111827; font-weight: 600; }
        .muted { color: #94a3b8; font-size: 0.9rem; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .stat-card { background: #0b1120; border: 1px solid #1f2937; border-radius: 8px; padding: 16px; }
        .stat-title { font-size: 0.85rem; color: #94a3b8; }
        .stat-value { font-size: 1.4rem; font-weight: 600; margin-top: 6px; }
        .notice { background: #1e3a8a; border: 1px solid #1d4ed8; color: #93c5fd; padding: 12px; border-radius: 6px; margin-bottom: 18px; }
        .error { background: #7f1d1d; border: 1px solid #ef4444; color: #fee2e2; padding: 12px; border-radius: 6px; margin-bottom: 18px; }
        @media (max-width: 880px) {
            .layout { flex-direction: column; }
            .sidebar { width: auto; border-right: none; border-bottom: 1px solid #1f2937; }
        }
    </style>
</head>
<body>
<header>
    <h1>Accounting Workspace</h1>
    <nav><a href="<?=h($_SERVER['PHP_SELF'])?>">Home</a></nav>
</header>
<div class="layout">
    <aside class="sidebar">
        <h2>Pages</h2>
        <?php foreach ($navPages as $nav): ?>
            <a class="<?=($nav['slug'] === $page['slug']) ? 'active' : ''?>" href="?p=<?=h($nav['slug'])?>"><?=h($nav['title'])?></a>
        <?php endforeach; ?>
    </aside>
    <main class="main">
        <?php if ($notice): ?>
            <div class="notice"><?=h($notice)?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?=h($error)?></div>
        <?php endif; ?>
        <div class="card">
            <?= $template ?>
        </div>
    </main>
</div>
</body>
</html>
