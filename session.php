<?php
$oneYear = 365 * 24 * 60 * 60; // Define one year in seconds

session_set_cookie_params([
    'lifetime' => $oneYear,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['__init'])) {
    session_regenerate_id(true);
    $_SESSION['__init'] = 1;
}
?>

