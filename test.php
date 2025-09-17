<?php

require __DIR__.'/bootstrap.php';
// अब $pdo तैयार है; क्वेरी चलाएँ…

$database = new Database();
$pdo = $database->getConnection();
echo "Database connected successfully!";
?>


