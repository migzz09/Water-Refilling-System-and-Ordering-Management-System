<?php
/**
 * Request Account Deletion
 * Sends deletion verification email to user
 * Method: POST
 * Body: {}
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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

    // Get user account info
    $stmt = $pdo->prepare('
        SELECT a.customer_id, c.email, c.first_name 
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

    if (empty($account['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No email address on file. Please update your email in profile settings.']);
        exit;
    }

    // Generate deletion token (32 character random string)
    $deleteToken = bin2hex(random_bytes(16));
    $tokenExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Store deletion token in database
    $stmt = $pdo->prepare('
        UPDATE accounts 
        SET deletion_token = ?, deletion_expires = ? 
        WHERE customer_id = ?
    ');
    $stmt->execute([$deleteToken, $tokenExpires, $_SESSION['customer_id']]);

    // Create deletion confirmation link
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    
    // Detect the project base path using the same logic as JavaScript's api-helper.js
    $requestPath = $_SERVER['REQUEST_URI'] ?? '';
    $marker = 'Water-Refilling-System-and-Ordering-Management-System';
    $markerPos = strpos($requestPath, $marker);
    
    if ($markerPos !== false) {
        // Found the marker, extract path up to and including it
        $basePath = substr($requestPath, 0, $markerPos + strlen($marker));
    } else {
        // Fallback to default path
        $basePath = '/wrsoms/Water-Refilling-System-and-Ordering-Management-System';
    }
    
    $deletionLink = $baseUrl . $basePath . '/pages/confirm-deletion.html?token=' . $deleteToken;

    // Log the generated link immediately
    error_log('=== DELETION LINK GENERATED ===');
    error_log('Base URL: ' . $baseUrl);
    error_log('Base Path: ' . $basePath);
    error_log('Full Link: ' . $deletionLink);
    error_log('Token: ' . $deleteToken);
    error_log('========================');

    // Send deletion confirmation email
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
        $mail->SMTPDebug  = 0;
        
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
        $mail->Subject = 'Account Deletion Confirmation - WaterWorld';
        $mail->Body    = "
            <h2>Account Deletion Request</h2>
            <p>Hello {$account['first_name']},</p>
            <p>You have requested to delete your WaterWorld account. This action is <strong>permanent</strong> and cannot be undone.</p>
            <p><strong>You will lose:</strong></p>
            <ul>
                <li>Your profile and personal information</li>
                <li>All order history and tracking data</li>
                <li>Delivery addresses and preferences</li>
                <li>Access to your account</li>
            </ul>
            <p>To confirm account deletion, click the link below (valid for 24 hours):</p>
            <p><a href='{$deletionLink}' style='background: #EF4444; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Confirm Account Deletion</a></p>
            <p>Or copy this link:<br><code>{$deletionLink}</code></p>
            <p>If you did not request this deletion, please ignore this email.</p>
            <br>
            <p>Best regards,<br>WaterWorld Team</p>
        ";

        $mail->send();

        echo json_encode([
            'success' => true,
            'message' => 'Verification email sent. Check your inbox.',
            'email' => substr($account['email'], 0, 3) . '***' . substr($account['email'], strpos($account['email'], '@'))
        ]);

    } catch (Exception $e) {
        error_log('Email sending failed: ' . $mail->ErrorInfo);
        error_log('Deletion Token (for testing): ' . $deleteToken);
        error_log('Full deletion link: ' . $deletionLink);
        error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
        error_log('Detected base path: ' . $basePath);
        
        echo json_encode([
            'success' => true,
            'message' => 'Deletion request processed. (Email service temporarily unavailable)',
            'email' => $account['email'],
            'debug_link' => $deletionLink
        ]);
    }

} catch (Exception $e) {
    error_log('request-deletion.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
