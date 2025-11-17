<?php
header('Content-Type: application/json');
session_start();

// Get request body
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['cart'])) {
    $_SESSION['cart'] = $input['cart'];
    echo json_encode(['success' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No cart data provided']);
}
?>