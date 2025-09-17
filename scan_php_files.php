<?php
// scan_php_files.php — scans PHP files for common "not loading" pitfalls
declare(strict_types=1);
@ini_set('display_errors','1'); error_reporting(E_ALL);

$root = __DIR__;
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$rows = [];

function has_bom(string $file): bool {
    $h = fopen($file, 'rb'); if (!$h) return false;
    $b = fread($h, 3); fclose($h);
    return $b === "\xEF\xBB\xBF";
}
function read_head(string $file, int $len = 2048): string {
    $h = fopen($file, 'rb'); if (!$h) return '';
    $d = fread($h, $len); fclose($h);
    return $d !== false ? $d : '';
}

foreach ($rii as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php') continue;
    if (basename($path) === 'scan_php_files.php') continue;

    $head = read_head($path);
    $short_open = false;
    // Detect any '<?' that is not '<?php' or '<?='
    if (preg_match('/<\?(?!php|=)/i', $head)) $short_open = true;

    $needs_dir_db = (bool)preg_match('/require(?:_once)?\s*[\'"]db\.php[\'"]/i', $head);
    $needs_dir_session = (bool)preg_match('/require(?:_once)?\s*[\'"]session\.php[\'"]/i', $head);

    $rows[] = [
        'file' => str_replace($root.'/', '', $path),
        'readable' => is_readable($path) ? 'yes' : 'no',
        'size' => filesize($path),
        'has_bom' => has_bom($path) ? 'yes' : 'no',
        'uses_short_open_tag' => $short_open ? 'yes' : 'no',
        'require_db_relative' => $needs_dir_db ? 'yes' : 'no',
        'require_session_relative' => $needs_dir_session ? 'yes' : 'no',
    ];
}

usort($rows, fn($a,$b) => strcmp($a['file'], $b['file']));

header('Content-Type: text/html; charset=utf-8');
echo "<h3>PHP File Scan — potential load issues</h3>";
echo "<table border='1' cellpadding='6' cellspacing='0'>";
echo "<tr><th>File</th><th>Readable</th><th>Size</th><th>BOM</th><th>Short &lt;?</th><th>require 'db.php'</th><th>require 'session.php'</th></tr>";
foreach ($rows as $r) {
    $warn = ($r['uses_short_open_tag']==='yes' || $r['require_db_relative']==='yes' || $r['require_session_relative']==='yes' || $r['has_bom']==='yes');
    $bg = $warn ? " style='background:#fff3cd'" : "";
    echo "<tr$bg><td>".htmlspecialchars($r['file'])."</td><td>{$r['readable']}</td><td>{$r['size']}</td><td>{$r['has_bom']}</td><td>{$r['uses_short_open_tag']}</td><td>{$r['require_db_relative']}</td><td>{$r['require_session_relative']}</td></tr>";
}
echo "</table>";

echo "<p style='color:#666'>Legend: rows highlighted in yellow need attention — replace short open tags with &lt;?php, replace <code>require 'db.php'</code> with <code>require __DIR__.'/db.php'</code>, same for session.php, and remove BOM.</p>";
