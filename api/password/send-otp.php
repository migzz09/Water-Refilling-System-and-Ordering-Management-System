<?php
/**
 * Send OTP for Password Change
 * Sends OTP to user's email for password change verification
 * Method: POST
 * Body: { "currentPassword": "..." }
 */
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/connect.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Load email configuration
$config = require __DIR__ . '/../../config/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    // Check authentication
    if (empty($_SESSION['customer_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $currentPassword = $input['currentPassword'] ?? '';

    if (empty($currentPassword)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is required']);
        exit;
    }

    // Get account and verify current password
    $stmt = $pdo->prepare('
        SELECT a.password, c.email, c.first_name 
        FROM accounts a 
        JOIN customers c ON a.customer_id = c.customer_id 
        WHERE a.customer_id = ?
    ');
    $stmt->execute([$_SESSION['customer_id']]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Account not found']);
        exit;
    }

    // Verify current password
    if (!password_verify($currentPassword, $account['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit;
    }

    if (empty($account['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No email address on file. Please update your email in profile settings.']);
        exit;
    }

    // Generate 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $otpExpires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Store OTP in accounts table
    $stmt = $pdo->prepare('UPDATE accounts SET otp = ?, otp_expires = ? WHERE customer_id = ?');
    $stmt->execute([$otp, $otpExpires, $_SESSION['customer_id']]);

    // Store OTP in session as backup
    $_SESSION['password_change_otp'] = $otp;
    $_SESSION['password_change_otp_expires'] = $otpExpires;

    // Send OTP via email
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['gmail_username'];
        $mail->Password   = $config['gmail_app_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0; // Disable debug output
        
        // For localhost - disable SSL verification
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom($config['gmail_username'], 'WaterWorld');
        $mail->addAddress($account['email'], $account['first_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Change Verification - WaterWorld';
        $mail->Body    = "
            <h2>Password Change Request</h2>
            <p>Hello {$account['first_name']},</p>
            <p>You have requested to change your password. Your verification code is:</p>
            <h1 style='color: #3B82F6; font-size: 32px; letter-spacing: 5px;'>{$otp}</h1>
            <p>This code will expire in 10 minutes.</p>
            <p>If you did not request this password change, please ignore this email and ensure your account is secure.</p>
            <br>
            <p>Best regards,<br>WaterWorld Team</p>
        ";

        $mail->send();

        echo json_encode([
            'success' => true,
            'message' => 'OTP sent to your email',
            'email' => substr($account['email'], 0, 3) . '***' . substr($account['email'], strpos($account['email'], '@'))
        ]);

    } catch (Exception $e) {
        error_log('Email sending failed: ' . $mail->ErrorInfo);
        
        // For development: show OTP in console if email fails
        error_log('OTP Code (for testing): ' . $otp);
        
        // Return success anyway since OTP is stored in database
        // In development, user can check console/logs for OTP
        echo json_encode([
            'success' => true,
            'message' => 'OTP generated. (Email service temporarily unavailable - check with admin)',
            'email' => $account['email'],
            'debug_otp' => $otp // Remove this in production!
        ]);
    }

} catch (Exception $e) {
    error_log('send-otp.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
