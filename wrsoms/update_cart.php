<?php
session_start();
$data = json_decode(file_get_contents('php://input'), true);
$_SESSION['cart'] = $data['cart'];
echo json_encode(['success' => true]);
?>