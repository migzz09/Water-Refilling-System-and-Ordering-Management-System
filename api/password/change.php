<?php
/**
 * Change Password API
 * Updates user password with OTP verification
 * Method: POST
 * Body: { "otp": "...", "newPassword": "..." }
 */
header('Content-Type: application/json');
session_start();

try {
    // Check authentication
    if (empty($_SESSION['customer_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    require_once __DIR__ . '/../../config/connect.php';

    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $otp = $input['otp'] ?? '';
    $newPassword = $input['newPassword'] ?? '';

    $errors = [];

    // Validate inputs
    if (empty($otp)) {
        $errors[] = 'OTP is required';
    }
    if (empty($newPassword)) {
        $errors[] = 'New password is required';
    }

    // Validate new password requirements
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        if (!preg_match('/[A-Z]/', $newPassword)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        if (!preg_match('/[a-z]/', $newPassword)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        if (!preg_match('/[0-9]/', $newPassword)) {
            $errors[] = 'Password must contain at least one number';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $newPassword)) {
            $errors[] = 'Password must contain at least one special character';
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }

    // Get account and verify OTP
    $stmt = $pdo->prepare('SELECT otp, otp_expires FROM accounts WHERE customer_id = ?');
    $stmt->execute([$_SESSION['customer_id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit;
    }

    // Verify OTP
    if (empty($account['otp']) || empty($account['otp_expires'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new OTP.']);
        exit;
    }

    if ($account['otp'] !== $otp) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
        exit;
    }

    if (strtotime($account['otp_expires']) < time()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }


    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update password, clear OTP, and set password_changed_at timestamp
    $stmt = $pdo->prepare('UPDATE accounts SET password = ?, password_changed_at = NOW(), otp = NULL, otp_expires = NULL WHERE customer_id = ?');
    $stmt->execute([$hashedPassword, $_SESSION['customer_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Password updated successfully'
    ]);

} catch (Exception $e) {
    error_log('change.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>

