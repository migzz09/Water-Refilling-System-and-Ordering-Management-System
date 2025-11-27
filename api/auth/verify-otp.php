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

if (!isset($_SESSION['registered_email'])) {
    $errors[] = "No OTP session found. Please register again.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$email = $_SESSION['registered_email'];

try {
    // Find account matching the email and active OTP
    $stmt = $pdo->prepare('SELECT a.account_id, a.otp, a.otp_expires, c.customer_id FROM accounts a JOIN customers c ON a.customer_id = c.customer_id WHERE c.email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Registration session not found. Please register again.']);
        exit;
    }

    // Check expiry
    if (empty($row['otp']) || strtotime($row['otp_expires']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }

    if ($otp !== $row['otp']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid OTP code.']);
        exit;
    }

    // Mark account as verified and clear OTP
    $stmt = $pdo->prepare('UPDATE accounts SET is_verified = 1, otp = NULL, otp_expires = NULL WHERE account_id = ?');
    $stmt->execute([$row['account_id']]);

    // Clear session keys used for registration
    unset($_SESSION['registered_email']);
    unset($_SESSION['otp_last_sent']);

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
