<?php
header('Content-Type: application/json');
session_start();

// Return basic profile/address information for the currently authenticated user
try {
    if (empty($_SESSION['customer_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated', 'profile' => null]);
        exit;
    }

    require_once __DIR__ . '/../../config/connect.php';

    $stmt = $pdo->prepare('SELECT customer_id, first_name, middle_name, last_name, customer_contact, street, barangay, city, province, email FROM customers WHERE customer_id = ? LIMIT 1');
    $stmt->execute([$_SESSION['customer_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Profile not found', 'profile' => null]);
        exit;
    }

    // Normalize response shape
    $profile = [
        'customer_id' => $row['customer_id'],
        'first_name' => $row['first_name'],
        'middle_name' => $row['middle_name'],
        'last_name' => $row['last_name'],
        'customer_contact' => $row['customer_contact'],
        'street' => $row['street'],
        'barangay' => $row['barangay'],
        'city' => $row['city'],
        'province' => $row['province'],
        'email' => $row['email']
    ];

    echo json_encode(['success' => true, 'profile' => $profile]);
} catch (Exception $e) {
    error_log('profile.php error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error', 'profile' => null]);
}

?>
