<?php
header('Content-Type: application/json');
require '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $pdo->query('SELECT id, name, type, balance FROM accounts ORDER BY name');
        echo json_encode($stmt->fetchAll());
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['name']) || empty($data['type'])) {
            http_response_code(400);
            echo json_encode(['message' => 'Account name and type are required.']);
            exit;
        }

        try {
            $sql = "INSERT INTO accounts (name, type) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['name'], $data['type']]);
            http_response_code(201);
            echo json_encode(['message' => 'Account created.']);
        } catch (PDOException $e) {
            http_response_code(500);
            // Check for duplicate entry
            if ($e->getCode() == 23000) {
                 echo json_encode(['message' => 'An account with this name already exists.']);
            } else {
                 echo json_encode(['message' => 'Database error: ' . $e->getMessage()]);
            }
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['message' => 'Method Not Allowed']);
        break;
}
?>
