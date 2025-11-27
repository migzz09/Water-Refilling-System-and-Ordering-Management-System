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
        $mail->Subject = 'Password Change Verification - Water World';
        $mail->Body    = '
            <div style="max-width:480px;margin:32px auto;padding:32px 24px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(34,197,94,0.10);font-family:Poppins,Inter,Arial,sans-serif;">
                <div style="text-align:center;margin-bottom:24px;">
                    <h2 style="color:#22c55e;margin:0 0 8px 0;font-weight:700;font-family:Poppins,Arial,sans-serif;">Water World</h2>
                </div>
                <h3 style="color:#14532d;text-align:center;margin-bottom:8px;font-family:Poppins,Arial,sans-serif;">Password Change Request</h3>
                <p style="text-align:center;margin:0 0 16px 0;color:#166534;font-family:Inter,Arial,sans-serif;">Hello <b>' . htmlspecialchars($account['first_name']) . '</b>,<br>You have requested to change your password. Your verification code is:</p>
                <div style="text-align:center;margin:24px 0;">
                    <span style="display:inline-block;background:#d1fae5;color:#15803d;font-size:2.5rem;letter-spacing:8px;padding:16px 32px;border-radius:12px;font-weight:700;font-family:Poppins,Arial,sans-serif;">' . $otp . '</span>
                </div>
                <p style="text-align:center;color:#166534;font-family:Inter,Arial,sans-serif;">This code will expire in 10 minutes.<br>If you did not request this password change, please ignore this email and ensure your account is secure.</p>
                <hr style="border:none;border-top:1px solid #bbf7d0;margin:32px 0;">
                <p style="text-align:center;color:#65a30d;font-size:13px;font-family:Inter,Arial,sans-serif;">Best regards,<br>Water World Team</p>
            </div>';

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
