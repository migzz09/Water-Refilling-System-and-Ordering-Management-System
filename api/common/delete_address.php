<?php
header('Content-Type: application/json');
session_start();

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$address_id = isset($input['address_id']) ? (int)$input['address_id'] : 0;

if ($address_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Invalid address id']]);
    exit;
}

if (!isset($_SESSION['addresses'][$address_id])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['Address not found']]);
    exit;
}

unset($_SESSION['addresses'][$address_id]);

echo json_encode(['success' => true]);
?>
