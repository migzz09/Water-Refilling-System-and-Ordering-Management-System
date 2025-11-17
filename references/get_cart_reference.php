<?php
session_start();
echo json_encode(['cart' => $_SESSION['cart'] ?? []]);
?>