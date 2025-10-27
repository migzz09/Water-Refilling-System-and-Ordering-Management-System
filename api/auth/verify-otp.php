<?php
/**
 * Verify OTP API Endpoint
 * Method: POST
 * Body: { "otp": "123456" }
 */
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$otp = trim($input['otp'] ?? '');

$errors = [];

if (empty($otp)) {
    $errors[] = "OTP is required.";
}

if (!isset($_SESSION['registration_otp'])) {
    $errors[] = "No OTP session found. Please register again.";
}

if (isset($_SESSION['otp_expiry']) && time() > $_SESSION['otp_expiry']) {
    $errors[] = "OTP has expired. Please register again.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Verify OTP
if ($otp !== $_SESSION['registration_otp']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Invalid OTP code.']]);
    exit;
}

// OTP is valid, complete registration
try {
    $data = $_SESSION['registration_data'];
    
    // Insert customer with email
    $stmt = $pdo->prepare("
        INSERT INTO customers (first_name, last_name, customer_contact, email, street, barangay, city, province)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['firstName'],
        $data['lastName'],
        $data['contact'],
        $data['email'],
        $data['street'],
        $data['barangay'],
        $data['city'],
        $data['province']
    ]);
    $customerId = $pdo->lastInsertId();

    // Insert account
    $stmt = $pdo->prepare("
        INSERT INTO accounts (customer_id, username, password, is_verified)
        VALUES (?, ?, ?, 1)
    ");
    $stmt->execute([$customerId, $data['username'], $data['password']]);

    // Clear registration session data
    unset($_SESSION['registration_otp']);
    unset($_SESSION['registration_data']);
    unset($_SESSION['otp_expiry']);

    echo json_encode([
        'success' => true,
        'message' => 'Account verified successfully. You can now log in.'
    ]);
} catch (PDOException $e) {
    error_log("Verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Verification failed. Please try again.',
        'error' => $e->getMessage()
    ]);
}
