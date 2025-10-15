<?php // config.php
// Application configuration.
// Adjust database credentials via environment variables or by editing the arrays below.

$ENV = getenv('APP_ENV') ?: 'local';
$config = [
    'local' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'user' => getenv('DB_USER') ?: 'appuser',
        'pass' => getenv('DB_PASS') ?: 'apppass123',
        'db'   => getenv('DB_NAME') ?: 'accounting_db',
        'charset' => 'utf8mb4',
    ],
    'production' => [
        'host' => getenv('DB_HOST') ?: '217.xx.xx.103',
        'port' => getenv('DB_PORT') ?: '3306',
        'user' => getenv('DB_USER') ?: 'xxxxxx',
        'pass' => getenv('DB_PASS') ?: 'xxxxxxxxxx',
        'db'   => getenv('DB_NAME') ?: 'xxxxxxxxx',
        'charset' => 'utf8mb4',
    ],
];

if (!isset($config[$ENV])) {
    throw new RuntimeException("Unknown APP_ENV '{$ENV}'. Set APP_ENV environment variable to one of: " . implode(', ', array_keys($config)));
}
