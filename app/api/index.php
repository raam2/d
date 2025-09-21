<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/src/models/Invoice.php';

$action = $_GET['action'] ?? '';

try {
    $db = Database::getConnection();
    switch ($action) {
        case 'get_latest_invoices':
            $invoice = new Invoice($db);
            $data = $invoice->getLatest(50);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'get_invoice_details':
            $invoice_id = $_GET['id'] ?? 0;
            if ($invoice_id > 0) {
                $invoice = new Invoice($db);
                $data = $invoice->getById($invoice_id);
                if ($data) {
                    echo json_encode(['success' => true, 'data' => $data]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'message' => 'Invoice not found.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid Invoice ID.']);
            }
            break;

        case 'create_invoice':
            $data = json_decode(file_get_contents('php://input'), true);
            $invoice = new Invoice($db);
            if ($invoice->create($data)) {
                echo json_encode(['success' => true, 'message' => 'Invoice created successfully!']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to create invoice.']);
            }
            break;

        case 'update_invoice':
            $data = json_decode(file_get_contents('php://input'), true);
            $invoice_id = $data['invoice_id'] ?? 0;
            if ($invoice_id > 0) {
                $invoice = new Invoice($db);
                if ($invoice->update($invoice_id, $data)) {
                    echo json_encode(['success' => true, 'message' => 'Invoice updated successfully!']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to update invoice.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid Invoice ID for update.']);
            }
            break;

        case 'delete_invoice':
            $data = json_decode(file_get_contents('php://input'), true);
            $invoice_id = $data['invoice_id'] ?? 0;
            if ($invoice_id > 0) {
                $invoice = new Invoice($db);
                if ($invoice->delete($invoice_id)) {
                    echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully!']);
                } else {
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Failed to delete invoice.']);
                }
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid Invoice ID for deletion.']);
            }
            break;

        case 'get_parties':
        case 'get_items':
            // THIS IS THE KEY CHANGE
            $table = ($action === 'get_parties') ? 'parties' : 'items';
            $column = 'name'; // Both tables use 'name' now.

            $query = $_GET['q'] ?? '';
            $page = max(1, (int)($_GET['page'] ?? 1));
            $perPage = 25;
            $offset = ($page - 1) * $perPage;

            $count_sql = "SELECT COUNT(id) FROM {$table} WHERE {$column} LIKE :query";
            $count_stmt = $db->prepare($count_sql);
            $count_stmt->execute([':query' => "%{$query}%"]);
            $total_count = $count_stmt->fetchColumn();

            $sql = "SELECT {$column} as text FROM {$table} WHERE {$column} LIKE :query ORDER BY {$column} LIMIT :limit OFFSET :offset";
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':query', "%{$query}%");
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['items' => $items, 'total_count' => $total_count, 'page' => $page, 'perPage' => $perPage]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'API action not found.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred: ' . $e->getMessage()]);
}
