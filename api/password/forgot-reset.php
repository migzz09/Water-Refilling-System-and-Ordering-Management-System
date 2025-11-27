<?php
/**
 * Forgot Password Reset
 * POST { email, otp, new_password }
 * Verifies OTP and resets password
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$otp = trim($input['otp'] ?? '');
$newPassword = $input['new_password'] ?? '';

if (!$email || !$otp || !$newPassword) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

$stmt = $pdo->prepare('SELECT a.customer_id, a.otp, a.otp_expires FROM accounts a JOIN customers c ON a.customer_id = c.customer_id WHERE c.email = ?');
$stmt->execute([$email]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Account not found']);
    exit;
}

if (!$account['otp'] || !$account['otp_expires'] || $account['otp'] !== $otp) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
    exit;
}
if (strtotime($account['otp_expires']) < time()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'OTP expired']);
    exit;
}

$hashed = password_hash($newPassword, PASSWORD_DEFAULT);
$stmt = $pdo->prepare('UPDATE accounts SET password = ?, otp = NULL, otp_expires = NULL WHERE customer_id = ?');
$stmt->execute([$hashed, $account['customer_id']]);

echo json_encode(['success' => true, 'message' => 'Password reset successful']);
