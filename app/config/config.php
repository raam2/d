<?php
return [
    'db_host' => '127.0.0.1',
    'db_port' => 3306,
    'db_name' => 'gst_accounting',
    'db_user' => 'gstwork',
    'db_pass' => 'gstwork@123',

    'app_name' => 'GST Accounting',
    'single_admin_mode' => true,
    'admin_fixed_user' => 'admin',
    'session_lifetime_seconds' => 15552000,

    'debug' => false,
    'error_log' => 'logs/php-error.log',

    'meta_cache_ttl' => 30
];
