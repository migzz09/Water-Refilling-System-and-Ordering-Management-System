<?php
header('Content-Type: application/json');
session_start();

// Ensure addresses array exists in session
if (!isset($_SESSION['addresses'])) {
    $_SESSION['addresses'] = [];
}

echo json_encode(['addresses' => array_values($_SESSION['addresses'])]);
?>
