<?php
session_start();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/database.php';
require_once '../src/models/Invoice.php';
require_once '../src/models/User.php';

$database = new Database();
$db = $database->getConnection();

$invoice = new Invoice($db);
$user = new User($db);

// --- Public endpoint: Login ---
if (isset($_GET['action']) && $_GET['action'] == 'login') {
    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->username) && !empty($data->password)) {
        $user_id = $user->login($data->username, $data->password);
        if ($user_id) {
            $_SESSION['user_id'] = $user_id;
            http_response_code(200);
            echo json_encode(array("message" => "Login successful."));
        } else {
            http_response_code(401);
            echo json_encode(array("message" => "Login failed. Invalid credentials."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Unable to login. Username and password are required."));
    }
    exit(); // Stop further execution
}

// --- Public endpoint: Logout ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    http_response_code(200);
    echo json_encode(array("message" => "Logout successful."));
    exit();
}


// --- Protected Endpoints ---
require_once 'auth.php'; // All endpoints below this line are protected

// Basic router based on request method
$method = $_SERVER['REQUEST_METHOD'];
// A simple way to get the last part of the URL path as the id
$path_parts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$id = is_numeric(end($path_parts)) ? end($path_parts) : null;


switch ($method) {
    case 'GET':
        if ($id) {
            $invoice->id = $id;
            $stmt = $invoice->readOne();
            if ($stmt->rowCount() > 0) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                http_response_code(200);
                echo json_encode($row);
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "Invoice not found."));
            }
        } else {
            $stmt = $invoice->read();
            $num = $stmt->rowCount();

            if ($num > 0) {
                $invoices_arr = array();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);
                    $invoice_item = array(
                        "id" => $id,
                        "customer_name" => $customer_name,
                        "amount" => $amount,
                        "status" => $status,
                        "created_at" => $created_at
                    );
                    array_push($invoices_arr, $invoice_item);
                }
                http_response_code(200);
                echo json_encode($invoices_arr);
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "No invoices found."));
            }
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->customer_name) && !empty($data->amount) && !empty($data->status)) {
            $invoice->customer_name = $data->customer_name;
            $invoice->amount = $data->amount;
            $invoice->status = $data->status;

            if ($invoice->create()) {
                http_response_code(201);
                echo json_encode(array("message" => "Invoice was created."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to create invoice."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to create invoice. Data is incomplete."));
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));

        if ($id && !empty($data->customer_name) && !empty($data->amount) && !empty($data->status)) {
            $invoice->id = $id;
            $invoice->customer_name = $data->customer_name;
            $invoice->amount = $data->amount;
            $invoice->status = $data->status;

            if ($invoice->update()) {
                http_response_code(200);
                echo json_encode(array("message" => "Invoice was updated."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to update invoice."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to update invoice. ID or data is incomplete."));
        }
        break;

    case 'DELETE':
        if ($id) {
            $invoice->id = $id;
            if ($invoice->delete()) {
                http_response_code(200);
                echo json_encode(array("message" => "Invoice was deleted."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to delete invoice."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to delete invoice. No ID provided."));
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(array("message" => "Method not allowed."));
        break;
}
