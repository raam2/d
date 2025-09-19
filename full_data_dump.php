<?php
// --- Configuration (edit as needed) ---
$db_user     = 'gstwork';
$db_password = 'gstwork@123';
$db_host     = '127.0.0.1';
$db_port     = '3306';
$db_name     = 'gst_accounting';
$dump_file   = tempnam(sys_get_temp_dir(), 'gst_dump_') . '.sql';

// --- mysqldump command ---
$cmd = sprintf(
    "mysqldump -u%s -p'%s' -h %s -P %d --routines --triggers --events %s > %s",
    escapeshellarg($db_user),
    str_replace("'", "'\\''", $db_password), // escape single quotes in password
    escapeshellarg($db_host),
    (int)$db_port,
    escapeshellarg($db_name),
    escapeshellarg($dump_file)
);

// --- Run the dump ---
exec($cmd, $output, $result_code);

if ($result_code !== 0 || !file_exists($dump_file)) {
    http_response_code(500);
    echo "Database dump failed.";
    exit;
}

// --- Send the file to the browser for download ---
header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="gst_accounting_full.sql"');
header('Content-Length: ' . filesize($dump_file));
readfile($dump_file);

// --- Clean up ---
unlink($dump_file);
exit;
?>
