<?php
session_start();

// Allow login requests to pass through without authentication.
if (isset($_GET['action']) && $_GET['action'] === 'login') {
    return;
}

// For all other API requests, ensure the user is authenticated.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Authentication required. Please log in.']);
    exit;
}