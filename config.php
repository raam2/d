<?php
// Application configuration.
// Adjust database credentials via environment variables or by editing the arrays below.

$ENV = getenv('APP_ENV') ?: 'local';

$config = [
    'local' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'user' => getenv('DB_USER') ?: 'u184420243_gst4',
        'pass' => getenv('DB_PASS') ?: 'Raam2*:1',
        'db'   => getenv('DB_NAME') ?: 'u184420243_jayanti_enter4',
        'charset' => 'utf8mb4',
    ],
    'production' => [
        'host' => getenv('DB_HOST') ?: '217.21.95.103',
        'port' => getenv('DB_PORT') ?: '3306',
        'user' => getenv('DB_USER') ?: 'u184420243_gst4',
        'pass' => getenv('DB_PASS') ?: 'Raam2:=195',
        'db'   => getenv('DB_NAME') ?: 'u184420243_jayanti_enter4',
        'charset' => 'utf8mb4',
    ],
];

if (!isset($config[$ENV])) {
    throw new RuntimeException("Unknown APP_ENV '${ENV}'. Set APP_ENV environment variable to one of: " . implode(', ', array_keys($config)));
}
