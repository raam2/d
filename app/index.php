<?php
require 'bootstrap.php';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title><?=htmlspecialchars($CONFIG['app_name'])?></title>
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="app-header">
  <h1><?=htmlspecialchars($CONFIG['app_name'])?></h1>
  <nav>
    <a href="#" data-view="dashboard">Dashboard</a>
    <a href="#" data-view="invoices">Invoices (API)</a>
    <a href="#" data-view="journal">Journal</a>
    <a href="#" data-view="reports">Reports</a>
    <a href="api/test.html" target="_blank">API Test</a>
  </nav>
</header>
<main id="app-main">
  <p>Use the navigation. Data loads via API endpoints under <code>app/api/router.php</code>.</p>
</main>
<script src="assets/js/app.js"></script>
</body>
</html>
