<?php // config.php
$ENV = getenv('APP_ENV') ?: 'local'; // 'local' or 'hostinger'

$config = [
  'local' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'user' => 'root',
    'pass' => 'your_local_password',
    'db'   => 'gst_accounting',
    'charset' => 'utf8mb4'
  ],
  'hostinger' => [
    'host' => 'srv684.hstgr.io',
    'port' => 3306,
    'user' => 'u184420243_gst',
    'pass' => 'Raam2:=195',
    'db'   => 'u184420243_jayanti_enterp',
    'charset' => 'utf8mb4'
  ]
];
