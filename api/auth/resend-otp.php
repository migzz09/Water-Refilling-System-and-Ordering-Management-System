<?php
/**
 * Resend OTP API Endpoint
 * Method: POST
 * Body: { "email": "user@example.com" }
 */
session_start();
require_once '../../config/connect.php';
require_once '../../vendor/PHPMailer/src/Exception.php';
require_once '../../vendor/PHPMailer/src/PHPMailer.php';
require_once '../../vendor/PHPMailer/src/SMTP.php';

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

$errors = [];

if (empty($email)) {
    $errors[] = "Email is required.";
}

if (!isset($_SESSION['registration_data'])) {
    $errors[] = "No registration session found. Please register again.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    // Verify email matches the session
    $data = $_SESSION['registration_data'];
    if ($data['email'] !== $email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email mismatch. Please register again.']);
        exit;
    }

    // Generate new OTP
    $otp = sprintf('%06d', mt_rand(0, 999999));
    $_SESSION['registration_otp'] = $otp;
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes

    // Send OTP via email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $config['gmail_username'];
    $mail->Password = $config['gmail_app_password'];
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom($config['gmail_username'], 'WaterWorld Water Station');
    $mail->addAddress($email, "{$data['firstName']} {$data['lastName']}");
    $mail->Subject = 'Your New OTP Code - WaterWorld';
    $mail->Body = "Hello {$data['firstName']},\n\nYour new OTP code is: $otp\n\nThis code will expire in 5 minutes.\n\nThank you,\nWaterWorld Water Station";
    $mail->send();

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