<?php
/**
 * Resend OTP API Endpoint
 * Method: POST
 * Body: { "email": "user@example.com" }
 */
session_start();
require_once '../../config/connect.php';

// Use Composer autoload so PHPMailer is loaded via PSR-4/autoloading
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Composer autoload not found. Please run composer install.']);
    exit;
}
require_once $autoload;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load email configuration
$config = require '../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

// Prefer session-registered email set by register.php
$sessionEmail = $_SESSION['registered_email'] ?? '';
if ($email === '' && $sessionEmail === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No registration session found. Please register again.']);
    exit;
}

if ($email === '') $email = $sessionEmail;

try {
    // Find the account by customer email
    $stmt = $pdo->prepare('SELECT a.account_id, c.first_name, c.last_name FROM accounts a JOIN customers c ON a.customer_id = c.customer_id WHERE c.email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Registration session not found. Please register again.']);
        exit;
    }

    // Rate limit: don't allow resend within 30 seconds
    $lastSent = $_SESSION['otp_last_sent'] ?? 0;
    if (time() - $lastSent < 30) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'OTP recently sent. Please wait before requesting another.']);
        exit;
    }

    // Generate new OTP and persist to DB
    $otp = sprintf('%06d', mt_rand(100000, 999999));
    $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $stmt = $pdo->prepare('UPDATE accounts a JOIN customers c ON a.customer_id = c.customer_id SET a.otp = ?, a.otp_expires = ? WHERE c.email = ?');
    $stmt->execute([$otp, $otp_expires, $email]);

    // Send OTP via email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $config['gmail_username'];
    $mail->Password = $config['gmail_app_password'];
    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom($config['gmail_username'], 'WaterWorld Water Station');
    $mail->addAddress($email, $row['first_name'] . ' ' . $row['last_name']);
    $mail->isHTML(true);
    $mail->Subject = 'Your New OTP Code - Water World';
    $mail->Body = '
        <div style="max-width:480px;margin:32px auto;padding:32px 24px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(34,197,94,0.10);font-family:Poppins,Inter,Arial,sans-serif;">
            <div style="text-align:center;margin-bottom:24px;">
                <h2 style="color:#22c55e;margin:0 0 8px 0;font-weight:700;font-family:Poppins,Arial,sans-serif;">Water World</h2>
            </div>
            <h3 style="color:#14532d;text-align:center;margin-bottom:8px;font-family:Poppins,Arial,sans-serif;">Email Verification</h3>
            <p style="text-align:center;margin:0 0 16px 0;color:#166534;font-family:Inter,Arial,sans-serif;">Hello <b>' . htmlspecialchars($row['first_name']) . '</b>,<br>Your One-Time Password (OTP) is:</p>
            <div style="text-align:center;margin:24px 0;">
                <span style="display:inline-block;background:#d1fae5;color:#15803d;font-size:2.5rem;letter-spacing:8px;padding:16px 32px;border-radius:12px;font-weight:700;font-family:Poppins,Arial,sans-serif;">' . $otp . '</span>
            </div>
            <p style="text-align:center;color:#166534;font-family:Inter,Arial,sans-serif;">This OTP is valid for <b>10 minutes</b>.<br>If you did not request this, please ignore this email.</p>
            <hr style="border:none;border-top:1px solid #bbf7d0;margin:32px 0;">
            <p style="text-align:center;color:#65a30d;font-size:13px;font-family:Inter,Arial,sans-serif;">&copy; ' . date('Y') . ' Water World. All rights reserved.</p>
        </div>';
    $mail->AltBody = "Hello {$row['first_name']},\nYour new OTP code is: $otp\nThis code will expire in 10 minutes.\nThank you, Water World.";
    $mail->send();

    // Update session last sent timestamp
    $_SESSION['otp_last_sent'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'OTP resent successfully. Please check your email.'
    ]);
} catch (Exception $e) {
    error_log("Resend OTP error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to resend OTP. Please try again.',
        'error' => $e->getMessage()
    ]);
}