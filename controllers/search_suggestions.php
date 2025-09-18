<?php
require __DIR__.'/bootstrap.php';
// अब $pdo तैयार है; क्वेरी चलाएँ…
header('Content-Type: application/json');
$db = (new Database())->getConnection();

$type = $_GET['type'] ?? '';

try {
  if ($type === 'product') {
      $stmt = $db->prepare("SELECT label FROM v_product_suggestions ORDER BY priority, label LIMIT 20");
  } elseif ($type === 'customer') {
      $stmt = $db->prepare("SELECT label FROM v_customer_suggestions ORDER BY label LIMIT 20");
  } else {
      echo json_encode([]); exit;
  }
  $stmt->execute();
  $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
  echo json_encode($results);
} catch (Throwable $e) {
  echo json_encode([]); // यदि view न बने हों तो ख़ाली रिटर्न करें
}
