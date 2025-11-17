<?php
session_start();

header('Content-Type: application/json');

echo json_encode([
    'session_id' => session_id(),
    'session_data' => $_SESSION,
    'has_customer_id' => isset($_SESSION['customer_id']),
    'has_user_id' => isset($_SESSION['user_id']),
    'has_is_admin' => isset($_SESSION['is_admin']),
    'is_admin_value' => $_SESSION['is_admin'] ?? null
], JSON_PRETTY_PRINT);
?>
