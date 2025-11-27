<?php
/**
 * Forgot Password OTP Request
 * POST { email }
 * Sends OTP to user's email for password reset
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../vendor/autoload.php';
$config = require __DIR__ . '/../../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

$stmt = $pdo->prepare('SELECT customer_id, first_name FROM customers WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No account found with that email']);
    exit;
}

$otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
$otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$stmt = $pdo->prepare('UPDATE accounts SET otp = ?, otp_expires = ? WHERE customer_id = ?');
$stmt->execute([$otp, $otpExpires, $user['customer_id']]);

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['gmail_username'];
    $mail->Password   = $config['gmail_app_password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    $mail->setFrom($config['gmail_username'], 'Water World');
    $mail->addAddress($email, $user['first_name']);
    $mail->isHTML(true);
    $mail->Subject = 'Password Reset OTP - Water World';
    $mail->Body    = '<div style="max-width:480px;margin:32px auto;padding:32px 24px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(34,197,94,0.10);font-family:Poppins,Inter,Arial,sans-serif;"><div style="text-align:center;margin-bottom:24px;"><h2 style="color:#22c55e;margin:0 0 8px 0;font-weight:700;font-family:Poppins,Arial,sans-serif;">Water World</h2></div><h3 style="color:#14532d;text-align:center;margin-bottom:8px;font-family:Poppins,Arial,sans-serif;">Password Reset Request</h3><p style="text-align:center;margin:0 0 16px 0;color:#166534;font-family:Inter,Arial,sans-serif;">Hello <b>' . htmlspecialchars($user['first_name']) . '</b>,<br>Your password reset OTP is:</p><div style="text-align:center;margin:24px 0;"><span style="display:inline-block;background:#d1fae5;color:#15803d;font-size:2.5rem;letter-spacing:8px;padding:16px 32px;border-radius:12px;font-weight:700;font-family:Poppins,Arial,sans-serif;">' . $otp . '</span></div><p style="text-align:center;color:#166534;font-family:Inter,Arial,sans-serif;">This code will expire in 10 minutes.<br>If you did not request this, please ignore this email.</p><hr style="border:none;border-top:1px solid #bbf7d0;margin:32px 0;"><p style="text-align:center;color:#65a30d;font-size:13px;font-family:Inter,Arial,sans-serif;">Best regards,<br>Water World Team</p></div>';
    $mail->send();
    echo json_encode(['success' => true, 'message' => 'OTP sent to your email']);
} catch (Exception $e) {
    error_log('Email sending failed: ' . $mail->ErrorInfo);
    echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
}
