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
    $mail->Subject = 'Your New OTP Code - WaterWorld';
    $mail->Body = "Hello {$row['first_name']},<br><br>Your new OTP code is: <b>$otp</b><br><br>This code will expire in 10 minutes.<br><br>Thank you,<br>WaterWorld Water Station";
    $mail->AltBody = "Hello {$row['first_name']},\n\nYour new OTP code is: $otp\n\nThis code will expire in 10 minutes.\n\nThank you,\nWaterWorld Water Station";
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