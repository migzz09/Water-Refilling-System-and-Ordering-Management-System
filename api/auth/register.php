<?php
/**
 * Register API Endpoint (JSON)
 * Accepts JSON POST from frontend and returns JSON responses.
 */
session_start();

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Debug: write shutdown errors to temp file and to PHP error log so we can inspect fatal errors
$debugLog = sys_get_temp_dir() . '/wrsoms_register_debug.log';
register_shutdown_function(function() use ($debugLog) {
    $err = error_get_last();
    if ($err) {
        $text = "[SHUTDOWN] " . date('c') . " - " . json_encode($err) . PHP_EOL;
        // attempt both local temp file and php error log
        @file_put_contents($debugLog, $text, FILE_APPEND | LOCK_EX);
        error_log($text);
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Internal server error. See server logs.']);
        }
    }
});

// Convert PHP errors to JSON responses
function apiErrorHandler($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Internal server error: $errstr"]);
    exit;
}
set_error_handler('apiErrorHandler');

// helper
function jsonResponse($code, $payload) {
    http_response_code((int)$code);
    echo json_encode($payload);
    exit;
}

try {
    require_once __DIR__ . '/../../config/connect.php';

    // load composer autoload (PHPMailer and others)
    $autoload = __DIR__ . '/../../vendor/autoload.php';
    if (!file_exists($autoload)) {
        throw new Exception('Composer autoload not found. Please run composer install.', 500);
    }
    require_once $autoload;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(405, ['success' => false, 'message' => 'Method not allowed']);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(400, ['success' => false, 'message' => 'Invalid JSON data provided']);
    }

    // Map input
    $username   = trim($input['username'] ?? '');
    $password   = $input['password'] ?? '';
    $email      = trim($input['email'] ?? '');
    $first_name = trim($input['first_name'] ?? '');
    $last_name  = trim($input['last_name'] ?? '');
    $contact    = trim($input['contact'] ?? '');
    $street     = trim($input['street'] ?? '');
    $barangay   = trim($input['barangay'] ?? '');
    $city       = trim($input['city'] ?? '');

    // Basic validation
    $errors = [];
    if ($username === '') $errors[] = 'Username is required.';
    if ($password === '') $errors[] = 'Password is required.';
    if ($first_name === '') $errors[] = 'First name is required.';
    if ($last_name === '') $errors[] = 'Last name is required.';
    if ($contact === '' || !preg_match('/^09\\d{9}$/', $contact)) $errors[] = 'Valid contact number (09XXXXXXXXX) is required.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if ($street === '') $errors[] = 'Street is required.';
    if ($city === '') $errors[] = 'City is required.';
    if ($barangay === '') $errors[] = 'Barangay is required.';

    if (!empty($errors)) {
        jsonResponse(400, ['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    }

    // Check existing username/email
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM accounts WHERE username = ?');
    $stmt->execute([$username]);
    $usernameExists = (int)$stmt->fetchColumn() > 0;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE email = ?');
    $stmt->execute([$email]);
    $emailExists = (int)$stmt->fetchColumn() > 0;

    // Check existing contact number and if it's linked to a verified account
    $stmt = $pdo->prepare('SELECT c.customer_id, a.is_verified FROM customers c LEFT JOIN accounts a ON c.account_id = a.account_id WHERE c.customer_contact = ?');
    $stmt->execute([$contact]);
    $contactRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($contactRow && ($contactRow['is_verified'] ?? 0)) {
        jsonResponse(400, ['success' => false, 'message' => 'Contact number already registered.']);
    }

    // If username/email exist and verified, return error. If unverified username exists, we'll resend OTP.
    $unverifiedAccount = null;
    if ($usernameExists || $emailExists) {
        // Try to find unverified account by username or email
        $stmt = $pdo->prepare('SELECT a.account_id, a.username, a.otp, a.otp_expires, c.email, c.first_name, a.is_verified FROM accounts a JOIN customers c ON a.customer_id = c.customer_id WHERE (a.username = ? OR c.email = ?)');
        $stmt->execute([$username, $email]);
        $unverifiedAccount = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($unverifiedAccount && ($unverifiedAccount['is_verified'] ?? 0)) {
            jsonResponse(400, ['success' => false, 'message' => 'Username or email already exists.']);
        }
        if ($unverifiedAccount && !empty($unverifiedAccount)) {
            // proceed to resend OTP for existing unverified account
        } else {
            // if exists but not returned above, it's taken
            jsonResponse(400, ['success' => false, 'message' => 'Username or email already exists.']);
        }
    }

    // Create or update account with OTP
    $pdo->beginTransaction();
    try {
        $otp = sprintf('%06d', mt_rand(100000, 999999));
        $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        if ($unverifiedAccount && !empty($unverifiedAccount)) {
            // update OTP for existing unverified account
            $stmt = $pdo->prepare('UPDATE accounts SET otp = ?, otp_expires = ? WHERE account_id = ?');
            $stmt->execute([$otp, $otp_expires, $unverifiedAccount['account_id']]);
        } else {
            // insert into customers
            $stmt = $pdo->prepare('INSERT INTO customers (first_name, last_name, customer_contact, email, street, barangay, city, province, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$first_name, $last_name, $contact, $email, $street, $barangay, $city, 'Metro Manila']);
            $customer_id = $pdo->lastInsertId();

            // hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('INSERT INTO accounts (customer_id, username, password, otp, otp_expires, is_verified) VALUES (?, ?, ?, ?, ?, 0)');
            $stmt->execute([$customer_id, $username, $hashed_password, $otp, $otp_expires]);
            $account_id = $pdo->lastInsertId();

            // Update customer table with account_id to create bidirectional link
            $stmt = $pdo->prepare('UPDATE customers SET account_id = ? WHERE customer_id = ?');
            $stmt->execute([$account_id, $customer_id]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    // Persist the registered address into session addresses so the checkout
    // page can immediately display the address even if the user hasn't logged in.
    // Use customer_id if available.
    if (isset($customer_id)) {
        if (!isset($_SESSION['addresses'])) {
            $_SESSION['addresses'] = [];
        }
        $_SESSION['addresses'][$customer_id] = [
            'id' => (int)$customer_id,
            'first_name' => $first_name,
            'middle_name' => '',
            'last_name' => $last_name,
            'customer_contact' => $contact,
            'street' => $street,
            'barangay' => $barangay,
            'city' => $city,
            'province' => 'Metro Manila'
        ];
    }

    // Load app config (Gmail creds) if present
    $configPath = __DIR__ . '/../../config/config.php';
    $gmailUser = null; $gmailPass = null;
    if (file_exists($configPath)) {
        $cfg = require $configPath;
        $gmailUser = $cfg['gmail_username'] ?? null;
        $gmailPass = $cfg['gmail_app_password'] ?? null;
    }

    // Send OTP email (if mail config present)
    if ($gmailUser && $gmailPass) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = $gmailUser;
            $mail->Password = $gmailPass;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->setFrom($gmailUser, 'Water World Admin');
            $mail->addAddress($email, "$first_name $last_name");
            $mail->isHTML(true);
            $mail->Subject = 'Your OTP for Registration';
            $mail->Body = '
                <div style="max-width:480px;margin:32px auto;padding:32px 24px;background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(34,197,94,0.10);font-family:Poppins,Inter,Arial,sans-serif;">
                    <div style="text-align:center;margin-bottom:24px;">
                        <h2 style="color:#22c55e;margin:0 0 8px 0;font-weight:700;font-family:Poppins,Arial,sans-serif;">Water World</h2>
                    </div>
                    <h3 style="color:#14532d;text-align:center;margin-bottom:8px;font-family:Poppins,Arial,sans-serif;">Email Verification</h3>
                    <p style="text-align:center;margin:0 0 16px 0;color:#166534;font-family:Inter,Arial,sans-serif;">Hello <b>' . htmlspecialchars($first_name) . '</b>,<br>Your One-Time Password (OTP) for registration is:</p>
                    <div style="text-align:center;margin:24px 0;">
                        <span style="display:inline-block;background:#d1fae5;color:#15803d;font-size:2.5rem;letter-spacing:8px;padding:16px 32px;border-radius:12px;font-weight:700;font-family:Poppins,Arial,sans-serif;">' . $otp . '</span>
                    </div>
                    <p style="text-align:center;color:#166534;font-family:Inter,Arial,sans-serif;">This OTP is valid for <b>10 minutes</b>.<br>If you did not request this, please ignore this email.</p>
                    <hr style="border:none;border-top:1px solid #bbf7d0;margin:32px 0;">
                    <p style="text-align:center;color:#65a30d;font-size:13px;font-family:Inter,Arial,sans-serif;">&copy; ' . date('Y') . ' Water World. All rights reserved.</p>
                </div>';
            $mail->AltBody = "Hello $first_name,\nYour OTP for registration is: $otp\nThis OTP is valid for 10 minutes.";

            $mail->send();
            // Debug: log mailer error info even if no exception
            if (!empty($mail->ErrorInfo)) {
                error_log('PHPMailer ErrorInfo: ' . $mail->ErrorInfo);
            }
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            // Log and continue - return success but note email failure
            error_log('PHPMailer error: ' . $e->getMessage());
            error_log('PHPMailer ErrorInfo (exception): ' . ($mail->ErrorInfo ?? 'N/A'));
            jsonResponse(500, ['success' => false, 'message' => 'Failed to send OTP email.']);
        }
    }

    // Store session email for OTP verification UI
    $_SESSION['registered_email'] = $email;
    $_SESSION['otp_last_sent'] = time();

    jsonResponse(200, ['success' => true, 'message' => 'Registration successful. OTP sent to email.']);

} catch (PDOException $e) {
    error_log('DB error in register.php: ' . $e->getMessage());
    jsonResponse(500, ['success' => false, 'message' => 'Database error.']);
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    $msg = $e->getMessage();
    jsonResponse($code, ['success' => false, 'message' => $msg]);
}

// No file-scope aliases here; classes referenced with fully-qualified names to avoid parsing issues