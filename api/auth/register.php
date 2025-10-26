<?php
/**
 * Register API Endpoint
 * Method: POST
 * Body: User registration data
 */
session_start();
require_once '../../config/connect.php';
require_once '../../config/config.php';
require_once '../../vendor/PHPMailer/src/Exception.php';
require_once '../../vendor/PHPMailer/src/PHPMailer.php';
require_once '../../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Extract and sanitize inputs
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');
$email = trim($input['email'] ?? '');
$contact = trim($input['contact'] ?? '');
$firstName = trim($input['first_name'] ?? '');
$lastName = trim($input['last_name'] ?? '');
$street = trim($input['street'] ?? '');
$barangay = trim($input['barangay'] ?? '');
$city = trim($input['city'] ?? '');
$province = 'Metro Manila';

$errors = [];

// Validation
if (empty($username) || strlen($username) < 3) {
    $errors[] = "Username must be at least 3 characters.";
}
if (empty($password) || strlen($password) < 6) {
    $errors[] = "Password must be at least 6 characters.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Valid email is required.";
}
if (empty($contact) || !preg_match('/^[0-9]{11}$/', $contact)) {
    $errors[] = "Contact must be 11 digits.";
}
if (empty($firstName) || empty($lastName)) {
    $errors[] = "First name and last name are required.";
}
if (empty($street) || empty($barangay) || empty($city)) {
    $errors[] = "Complete address is required.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT customer_id FROM accounts WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => ['Username or email already exists.']]);
        exit;
    }

    // Generate OTP
    $otp = sprintf('%06d', mt_rand(0, 999999));
    $_SESSION['registration_otp'] = $otp;
    $_SESSION['registration_data'] = compact('username', 'password', 'email', 'contact', 'firstName', 'lastName', 'street', 'barangay', 'city', 'province');
    $_SESSION['otp_expiry'] = time() + 300; // 5 minutes

    // Send OTP via email
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(SMTP_USER, 'WaterWorld Water Station');
    $mail->addAddress($email, "$firstName $lastName");
    $mail->Subject = 'Your OTP Code';
    $mail->Body = "Your OTP code is: $otp\n\nThis code will expire in 5 minutes.";
    $mail->send();

    echo json_encode([
        'success' => true,
        'message' => 'Registration initiated. Please check your email for OTP.',
        'data' => ['email' => $email]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()]);
}
