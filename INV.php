<?php
require 'bootstrap.php';
query("SELECT * FROM gst_accounting.invoices");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output as JSON
    header('Content-Type: application/json');
    echo json_encode($invoices);
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>

