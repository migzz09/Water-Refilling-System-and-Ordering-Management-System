<?php
header('Content-Type: application/json');
session_start();

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Return current cart state
echo json_encode(['cart' => $_SESSION['cart']]);
?>