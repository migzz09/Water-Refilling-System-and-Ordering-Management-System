<?php
session_start();
require_once 'connect.php'; // Ensure this file defines $pdo
require_once 'phpmailer-master/src/Exception.php';
require_once 'phpmailer-master/src/PHPMailer.php';
require_once 'phpmailer-master/src/SMTP.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("Session started: session_id=" . session_id() . ", registered_email=" . ($_SESSION['registered_email'] ?? 'not set'));

// Load configuration
if (!file_exists('../config/config.php')) {
    die("Configuration file missing. Please ensure '../config/config.php' exists.");
}
$config = require_once '../config/config.php'; // Must define $config['gmail_username'] and $config['gmail_app_password']

// Debug OTP logging
if (isset($_POST['debug_otp'])) {
    error_log("Client-side OTP: " . $_POST['debug_otp']);
    exit();
}

// NCR cities and barangays
$ncr_cities = [
    'Taguig' => [
        'Bagumbayan', 'Bambang', 'Calzada', 'Central Bicutan', 'Central Signal Village', 'Fort Bonifacio',
        'Hagonoy', 'Ibayo-Tipas', 'Katuparan', 'Ligid-Tipas', 'Lower Bicutan', 'Maharlika Village',
        'Napindan', 'New Lower Bicutan', 'North Daang Hari', 'North Signal Village', 'Palingon',
        'Pinagsama', 'San Miguel', 'Santa Ana', 'South Daang Hari', 'South Signal Village', 'Tanyag',
        'Tuktukan', 'Upper Bicutan', 'Ususan', 'Wawa', 'Western Bicutan', 'Comembo', 'Cembo',
        'South Cembo', 'East Rembo', 'West Rembo', 'Pembo', 'Pitogo', 'Post Proper Northside',
        'Post Proper Southside', 'Rizal'
    ],
    'Quezon City' => ['Bagong Pag-asa', 'Batasan Hills', 'Commonwealth', 'Holy Spirit', 'Payatas'],
    'Manila' => ['Tondo', 'Binondo', 'Ermita', 'Malate', 'Paco'],
    'Makati' => ['Bangkal', 'Bel-Air', 'Magallanes', 'Pio del Pilar', 'San Lorenzo'],
    'Pasig' => ['Bagong Ilog', 'Oranbo', 'San Antonio', 'Santa Lucia', 'Ugong'],
    'Pateros' => ['Aguho', 'Martyrs', 'San Roque', 'Santa Ana']
];

$errors = [];
$otp_errors = [];
$success_message = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $customer_contact = trim($_POST['customer_contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');

    // Input validation
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($customer_contact) || !preg_match('/^09\d{9}$/', $customer_contact)) {
        $errors[] = "Valid contact number (e.g., 09XXXXXXXXX) is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required.";
    }
    if (empty($street)) $errors[] = "Street is required.";
    if (empty($barangay) || !in_array($barangay, $ncr_cities[$city] ?? [])) {
        $errors[] = "Valid barangay is required.";
    }
    if (empty($city) || !array_key_exists($city, $ncr_cities)) {
        $errors[] = "Valid NCR city is required.";
    }

    if (empty($errors)) {
        try {
            // Check for existing username or email
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM accounts WHERE username = ? UNION 
                SELECT COUNT(*) FROM customers WHERE email = ?
            ");
            $stmt->execute([$username, $email]);
            $counts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $unverified_account = null;
            if (in_array(1, $counts)) {
                $stmt = $pdo->prepare("SELECT account_id FROM accounts WHERE username = ? AND is_verified = 0");
                $stmt->execute([$username]);
                $unverified_account = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            if ($unverified_account) {
                // Update existing unverified account with new OTP
                $stmt = $pdo->prepare("UPDATE accounts SET otp = ?, otp_expires = ? WHERE account_id = ?");
                $stmt->execute([$otp, $otp_expires, $unverified_account['account_id']]);
                $action = "resending";
                error_log("Updating unverified account: username=$username, account_id={$unverified_account['account_id']}, new_otp=$otp");
            } elseif (in_array(1, $counts)) {
                $errors[] = "Username or email already exists and is verified. Please log in or use a different email/username.";
            } else {
                // Insert new customer and account
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO customers (first_name, last_name, customer_contact, email, street, barangay, city, province, date_created)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'Metro Manila', NOW())
                ");
                $stmt->execute([$first_name, $last_name, $customer_contact, $email, $street, $barangay, $city]);
                $customer_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("
                    INSERT INTO accounts (customer_id, username, password, otp, otp_expires, is_verified)
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([$customer_id, $username, $password, $otp, $otp_expires]);
                $pdo->commit();
                $action = "registering";
                error_log("New account created: username=$username, email=$email, customer_id=$customer_id, otp=$otp");
            }

            if (empty($errors)) {
                // Send OTP email
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $config['gmail_username'];
                $mail->Password = $config['gmail_app_password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];

                if (!$mail->smtpConnect()) {
                    $mail->Port = 465;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
                $mail->setFrom($config['gmail_username'], 'WaterWorld Admin');
                $mail->addAddress($email, "$first_name $last_name");
                $mail->isHTML(true);
                $mail->Subject = ($action === "resending" ? 'New OTP for Registration' : 'Your OTP for Registration');
                $mail->Body = "Hello $first_name,<br>Your OTP for $action is: <b>$otp</b><br>This OTP is valid for 10 minutes.";
                $mail->AltBody = "Hello $first_name,\nYour OTP for $action is: $otp\nThis OTP is valid for 10 minutes.";

                if ($mail->send()) {
                    $_SESSION['registered_email'] = $email;
                    $_SESSION['otp_last_sent'] = time();
                    $success_message = "OTP sent to your email. Please verify.";
                    error_log("OTP sent successfully to $email for $action");
                } else {
                    $errors[] = "Failed to send OTP: " . $mail->ErrorInfo;
                    error_log("PHPMailer Error: Failed to send OTP to $email. Error: " . $mail->ErrorInfo);
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Registration failed: " . $e->getMessage();
            error_log("PDO Exception in registration: " . $e->getMessage());
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Failed to send OTP: " . $e->getMessage();
            error_log("PHPMailer Exception: " . $e->getMessage());
        }
    }
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_submit'])) {
    $entered_otp = trim($_POST['otp'] ?? '');
    $email = $_SESSION['registered_email'] ?? '';

    error_log("OTP Submission: email=$email, entered_otp=$entered_otp, session_exists=" . (isset($_SESSION['registered_email']) ? 'yes' : 'no'));

    if (empty($entered_otp)) {
        $otp_errors[] = "OTP is required.";
        error_log("OTP Error: OTP field is empty");
    }
    if (empty($email)) {
        $otp_errors[] = "No email found in session. Please register again.";
        error_log("OTP Error: Session email missing");
    }

    if (empty($otp_errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT a.account_id, a.customer_id, a.username, a.otp, a.otp_expires, c.email 
                FROM accounts a
                JOIN customers c ON a.customer_id = c.customer_id
                WHERE c.email = ?
            ");
            $stmt->execute([$email]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            error_log("OTP Query: found=" . ($account ? 'yes' : 'no') . 
                      ", account_id=" . ($account['account_id'] ?? 'N/A') . 
                      ", username=" . ($account['username'] ?? 'N/A') . 
                      ", stored_otp=" . ($account['otp'] ?? 'null') . 
                      ", otp_expires=" . ($account['otp_expires'] ?? 'null'));

            if ($account) {
                $is_otp_valid = $account['otp'] === $entered_otp;
                $is_not_expired = strtotime($account['otp_expires']) > time();
                error_log("OTP Validation: otp_match=$is_otp_valid, not_expired=$is_not_expired");

                if ($is_otp_valid && $is_not_expired) {
                    $pdo->beginTransaction();
                    // Set otp_expires to current time instead of NULL to avoid constraint violation
                    $stmt = $pdo->prepare("
                        UPDATE accounts 
                        SET is_verified = 1, otp = NULL, otp_expires = NOW() 
                        WHERE account_id = ? AND is_verified = 0
                    ");
                    $result = $stmt->execute([$account['account_id']]);
                    $rows_affected = $stmt->rowCount();
                    error_log("Update Query: success=" . ($result ? 'yes' : 'no') . 
                              ", rows_affected=$rows_affected, account_id={$account['account_id']}");

                    if ($result && $rows_affected > 0) {
                        $pdo->commit();
                        error_log("Account verified: email=$email, account_id={$account['account_id']}, username={$account['username']}");
                        unset($_SESSION['registered_email']);
                        unset($_SESSION['otp_last_sent']);
                        header("Location: index.php?success=Account verified successfully. Please log in.");
                        exit();
                    } else {
                        $pdo->rollBack();
                        $otp_errors[] = "Failed to verify account. No changes made.";
                        error_log("OTP Error: Update failed, no rows affected for account_id={$account['account_id']}");
                    }
                } else {
                    $otp_errors[] = "Invalid or expired OTP. Please try again or request a new one.";
                    error_log("OTP Error: Invalid or expired OTP. entered_otp=$entered_otp, stored_otp=" . ($account['otp'] ?? 'null'));
                }
            } else {
                $otp_errors[] = "No account found for this email.";
                error_log("OTP Error: No account found for email=$email");
            }
        } catch (PDOException $e) {
            $otp_errors[] = "Database error during OTP verification: " . $e->getMessage();
            error_log("OTP PDO Exception: " . $e->getMessage());
        }
    }
}

// Handle Resend OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    $email = $_SESSION['registered_email'] ?? '';
    error_log("Resend OTP requested: email=$email, session_id=" . session_id());

    if (empty($email)) {
        $otp_errors[] = "No email found in session. Please register again.";
        error_log("Resend OTP Error: Session email missing");
    } else {
        $last_sent = $_SESSION['otp_last_sent'] ?? 0;
        $current_time = time();
        if ($current_time - $last_sent < 60) {
            $otp_errors[] = "Please wait " . (60 - ($current_time - $last_sent)) . " seconds before resending OTP.";
            error_log("Resend OTP Error: Time limit not reached, wait=" . (60 - ($current_time - $last_sent)));
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT a.account_id, c.first_name 
                    FROM accounts a 
                    JOIN customers c ON a.customer_id = c.customer_id 
                    WHERE c.email = ? AND a.is_verified = 0
                ");
                $stmt->execute([$email]);
                $account = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($account) {
                    $otp = sprintf("%06d", mt_rand(100000, 999999));
                    $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                    $stmt = $pdo->prepare("
                        UPDATE accounts 
                        SET otp = ?, otp_expires = ? 
                        WHERE account_id = ?
                    ");
                    $stmt->execute([$otp, $otp_expires, $account['account_id']]);
                    error_log("New OTP generated: otp=$otp, account_id={$account['account_id']}, email=$email");

                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $config['gmail_username'];
                    $mail->Password = $config['gmail_app_password'];
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->SMTPOptions = [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        ]
                    ];

                    if (!$mail->smtpConnect()) {
                        $mail->Port = 465;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    }

                    $mail->setFrom($config['gmail_username'], 'WaterWorld Admin');
                    $mail->addAddress($email, $account['first_name']);
                    $mail->isHTML(true);
                    $mail->Subject = 'New OTP for Registration';
                    $mail->Body = "Hello {$account['first_name']},<br>Your new OTP for registration is: <b>$otp</b><br>This OTP is valid for 10 minutes.";
                    $mail->AltBody = "Hello {$account['first_name']},\nYour new OTP for registration is: $otp\nThis OTP is valid for 10 minutes.";

                    if ($mail->send()) {
                        $_SESSION['registered_email'] = $email;
                        $_SESSION['otp_last_sent'] = time();
                        $success_message = "New OTP sent to your email.";
                        error_log("New OTP sent successfully to $email");
                    } else {
                        $otp_errors[] = "Failed to send OTP: " . $mail->ErrorInfo;
                        error_log("PHPMailer Error: Failed to send OTP to $email. Error: " . $mail->ErrorInfo);
                    }
                } else {
                    $otp_errors[] = "No unverified account found for this email.";
                    error_log("Resend OTP Error: No unverified account found for email=$email");
                }
            } catch (PDOException $e) {
                $otp_errors[] = "Database error during OTP resend: " . $e->getMessage();
                error_log("Resend OTP PDO Exception: " . $e->getMessage());
            } catch (Exception $e) {
                $otp_errors[] = "Failed to resend OTP: " . $e->getMessage();
                error_log("Resend OTP PHPMailer Exception: " . $e->getMessage());
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - WaterWorld Water Station</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background-color: #f9fbfc;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }
        header {
            background: #ffffffcc;
            backdrop-filter: blur(10px);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e5e5;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #008CBA;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        nav ul li a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            position: relative;
            padding-bottom: 4px;
            transition: color 0.3s;
        }
        nav ul li a::after {
            content: "";
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: #008CBA;
            transition: width 0.3s;
        }
        nav ul li a:hover {
            color: #008CBA;
        }
        nav ul li a:hover::after {
            width: 100%;
        }
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: linear-gradient(rgba(0, 140, 186, 0.6), rgba(0, 140, 186, 0.6)),
                        url('images/clear_blue_water.png') no-repeat center/cover;
            color: white;
            padding: 4rem 5%;
            position: relative;
        }
        .auth-section {
            background: rgba(255, 255, 255, 0.9);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            max-width: 1100px;
            width: 100%;
            margin: 2rem auto;
            position: relative;
            z-index: 20;
        }
        .auth-section h2 {
            color: #008CBA;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            text-align: center;
        }
        .form-columns {
            display: flex;
            gap: 2.5rem;
            flex-wrap: wrap;
        }
        .form-column {
            flex: 1;
            min-width: 450px;
        }
        .form-group {
            margin-bottom: 1.2rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="email"],
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .form-group input[type="text"]:focus,
        .form-group input[type="password"]:focus,
        .form-group input[type="email"]:focus,
        .form-group select:focus {
            border-color: #008CBA;
            box-shadow: 0 0 5px rgba(0, 140, 186, 0.3);
            outline: none;
        }
        .password-wrapper {
            position: relative;
        }
        .password-wrapper input[type="password"],
        .password-wrapper input[type="text"] {
            padding-right: 2.5rem;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 20px;
            height: 20px;
            fill: #555;
        }
        .form-group input[type="submit"] {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(90deg, #008CBA, #00aaff);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform 0.3s, background 0.3s;
        }
        .form-group input[type="submit"]:hover {
            transform: translateY(-2px);
            background: linear-gradient(90deg, #0077b3, #0099e6);
        }
        .auth-section .error {
            color: #d32f2f;
            background: #ffebee;
            padding: 0.8rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .auth-section .success {
            color: #2e7d32;
            background: #e8f5e9;
            padding: 0.8rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .otp-section {
            background: rgba(255, 255, 255, 0.9);
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            min-height: 450px;
            width: 100%;
            margin: 1rem auto;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 30;
            display: none;
        }
        .otp-section.active {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .otp-close {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 24px;
            height: 24px;
            cursor: pointer;
            fill: none;
            stroke: #555;
            transition: stroke 0.3s;
        }
        .otp-close:hover {
            stroke: #008CBA;
        }
        .otp-columns {
            display: flex;
            gap: 2.5rem;
            flex-wrap: wrap;
            align-items: center;
        }
        .otp-column {
            flex: 1;
            min-width: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .otp-icon {
            max-width: 100px;
            margin: 0 auto 1.5rem;
            display: block;
        }
        .otp-section h2 {
            color: #008CBA;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
            text-align: center;
        }
        .otp-message {
            color: #555;
            font-size: 1rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .otp-inputs {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .otp-inputs input {
            width: 40px;
            height: 40px;
            text-align: center;
            font-size: 1.2rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .otp-inputs input:focus {
            border-color: #008CBA;
            box-shadow: 0 0 5px rgba(0, 140, 186, 0.3);
            outline: none;
        }
        .otp-timer {
            color: #555;
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        .otp-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .otp-buttons input[type="submit"],
        .otp-buttons button {
            width: 150px;
            padding: 0.9rem;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.3s, background 0.3s;
        }
        .otp-buttons input[type="submit"] {
            background: linear-gradient(90deg, #008CBA, #00aaff);
            color: #fff;
        }
        .otp-buttons input[type="submit"]:hover {
            transform: translateY(-2px);
            background: linear-gradient(90deg, #0077b3, #0099e6);
        }
        .otp-buttons button {
            background: linear-gradient(90deg, #4CAF50, #66BB6A);
            color: #fff;
        }
        .otp-buttons button:hover {
            transform: translateY(-2px);
            background: linear-gradient(90deg, #43A047, #5cb85c);
        }
        .otp-buttons button:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        footer {
            background: #008CBA;
            color: white;
            text-align: center;
            padding: 2rem 5%;
            margin-top: 2rem;
            position: relative;
            z-index: 10;
        }
        footer .socials {
            margin: 1rem 0;
        }
        footer .socials a {
            margin: 0 10px;
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            transition: color 0.3s;
        }
        footer .socials a:hover {
            color: #cceeff;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body>
<header style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 5%;">
    <div class="logo" style="display: flex; align-items: center;">
        <img src="images/ww_logo.png" alt="WaterWorld Logo" style="height: 2.5rem; margin-right: 0.75rem;">
        WaterWorld
    </div>
    <nav style="display: flex; align-items: center;">
        <ul style="display: flex; list-style: none; gap: 1.5rem; align-items: center; margin: 0; padding: 0;">
            <li><a href="index.php" style="text-decoration: none; color: #333; font-weight: 500;">Home</a></li>
            <li><a href="order_tracking.php" style="text-decoration: none; color: #333; font-weight: 500;">Track</a></li>
        </ul>
    </nav>
</header>

<section class="hero">
    <div class="auth-section">
        <h2>Register</h2>
        <?php if ($success_message): ?>
            <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" action="register.php" name="register_form">
            <div class="form-columns">
                <div class="form-column">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="password" required>
                            <svg class="password-toggle" onclick="togglePassword()" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" name="first_name" id="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" name="last_name" id="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="customer_contact">Contact Number (e.g., 09XXXXXXXXX)</label>
                        <input type="text" name="customer_contact" id="customer_contact" value="<?php echo isset($_POST['customer_contact']) ? htmlspecialchars($_POST['customer_contact']) : ''; ?>" required>
                    </div>
                </div>
                <div class="form-column">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" name="email" id="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="street">Street</label>
                        <input type="text" name="street" id="street" value="<?php echo isset($_POST['street']) ? htmlspecialchars($_POST['street']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="city">City</label>
                        <select name="city" id="city" required onchange="updateBarangays()">
                            <option value="">Select City</option>
                            <?php foreach ($ncr_cities as $city => $barangays): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>" <?php echo isset($_POST['city']) && $_POST['city'] === $city ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay</label>
                        <select name="barangay" id="barangay" required>
                            <option value="">Select Barangay</option>
                            <?php if (isset($_POST['city']) && array_key_exists($_POST['city'], $ncr_cities)): ?>
                                <?php foreach ($ncr_cities[$_POST['city']] as $barangay): ?>
                                    <option value="<?php echo htmlspecialchars($barangay); ?>" <?php echo isset($_POST['barangay']) && $_POST['barangay'] === $barangay ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($barangay); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="submit" name="register_submit" value="Register">
                    </div>
                </div>
            </div>
        </form>

        <!-- OTP Verification Popup -->
        <div id="otp-form" class="otp-section">
            <svg class="otp-close" onclick="closeOTPForm()" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            <div class="otp-columns">
                <div class="otp-column">
                    <img src="images/verify_otp.png" alt="OTP Icon" class="otp-icon">
                    <h2>Verify Your OTP</h2>
                    <p class="otp-message">OTP sent to <?php echo isset($_SESSION['registered_email']) ? htmlspecialchars($_SESSION['registered_email']) : 'your email'; ?>.</p>
                    <?php if ($success_message): ?>
                        <div class="success"><?php echo htmlspecialchars($success_message); ?></div>
                    <?php endif; ?>
                </div>
                <div class="otp-column">
                    <form method="POST" action="register.php">
                        <div class="form-group">
                            <label for="otp">Enter OTP</label>
                            <div class="otp-inputs">
                                <input type="text" maxlength="1" pattern="[0-9]" class="otp-digit" required oninput="moveToNext(this, 0)">
                                <input type="text" maxlength="1" pattern="[0-9]" class="otp-digit" required oninput="moveToNext(this, 1)" onkeydown="moveToPrev(this, 0)">
                                <input type="text" maxlength="1" pattern="[0-9]" class="otp-digit" required oninput="moveToNext(this, 2)" onkeydown="moveToPrev(this, 1)">
                                <input type="text" maxlength="1" pattern="[0-9]" class="otp-digit" required oninput="moveToNext(this, 3)" onkeydown="moveToPrev(this, 2)">
                                <input type="text" maxlength="1" pattern="[0-9]" class="otp-digit" required oninput="moveToNext(this, 4)" onkeydown="moveToPrev(this, 3)">
                                <input type="text" maxlength="1" pattern="[0-9]" class="otp-digit" required oninput="moveToNext(this, 5)" onkeydown="moveToPrev(this, 4)">
                                <input type="hidden" name="otp" id="otp-hidden">
                            </div>
                        </div>
                        <div class="otp-timer" id="otp-timer">Resend OTP in 60s</div>
                        <?php if (!empty($otp_errors)): ?>
                            <div class="error">
                                <ul>
                                    <?php foreach ($otp_errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <div class="otp-buttons">
                            <button type="button" id="resend-otp" disabled>Resend OTP</button>
                            <input type="submit" name="otp_submit" value="Verify OTP">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<footer>
    <p>&copy; 2025 WaterWorld Water Station. All rights reserved.</p>
    <div class="socials" style="display: flex; gap: 20px; justify-content: center;">
        <a href="https://web.facebook.com/yourwaterworld" aria-label="Facebook" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M9 8h-3v4h3v12h5v-12h3.642l.358-4h-4v-1.667c0-.955.192-1.333 1.115-1.333h2.885v-5h-3.808c-3.596 0-5.192 1.583-5.192 4.615v3.385z"/>
            </svg>
        </a>
        <a href="https://www.google.com/maps/place/Water+World/@14.5602872,121.0613366,17z/data=!3m1!4b1!4m6!3m5!1s0x3397c863ae316b73:0xada9b9c65212f757!8m2!3d14.560282!4d121.0639115!16s%2Fg%2F11hcw7cmvx?entry=ttu&g_ep=EgoyMDI1MTAxNC4wIKXMDSoASAFQAw%3D%3D" aria-label="Google Maps" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20.5 3l-.16.03L15 5.1 9 3 3.36 4.9c-.21.07-.36.25-.36.48V20.5c0 .28.22.5.5.5l.16-.03L9 18.9l6 2.1 5.64-1.9c.21-.07.36-.25-.36-.48V3.5c0-.28-.22-.5-.5-.5zM15 19l-6-2.11V5l6 2.11V19z"/>
            </svg>
        </a>
        <a href="https://mail.google.com/mail/?view=cm&fs=1&to=yourwaterworld@gmail.com&su=Contact%20WaterWorld" aria-label="Email WaterWorld" target="_blank">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
            </svg>
        </a>
    </div>
</footer>

<script>
function updateBarangays() {
    const citySelect = document.getElementById('city');
    const barangaySelect = document.getElementById('barangay');
    const cities = <?php echo json_encode($ncr_cities); ?>;
    const selectedCity = citySelect.value;

    barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
    if (selectedCity && cities[selectedCity]) {
        cities[selectedCity].forEach(barangay => {
            const option = document.createElement('option');
            option.value = barangay;
            option.textContent = barangay;
            barangaySelect.appendChild(option);
        });
    }
}

function togglePassword() {
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.querySelector('.password-toggle');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        passwordToggle.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
            <line x1="3" y1="3" x2="21" y2="21" stroke="currentColor" stroke-width="2"/>
        `;
    } else {
        passwordInput.type = 'password';
        passwordToggle.innerHTML = `
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
        `;
    }
}

function showOTPForm() {
    console.log("Showing OTP form");
    document.getElementById('otp-form').classList.add('active');
    startOTPTimer();
}

function closeOTPForm() {
    console.log("Closing OTP form");
    document.getElementById('otp-form').classList.remove('active');
}

function moveToNext(input, index) {
    if (input.value.length === 1 && index < 5) {
        document.getElementsByClassName('otp-digit')[index + 1].focus();
    }
    combineOTP();
}

function moveToPrev(input, index) {
    if (event.key === 'Backspace' && input.value === '' && index > 0) {
        document.getElementsByClassName('otp-digit')[index - 1].focus();
    }
    combineOTP();
}

function combineOTP() {
    const digits = document.getElementsByClassName('otp-digit');
    let otp = '';
    for (let i = 0; i < digits.length; i++) {
        if (!digits[i].value.match(/[0-9]/)) {
            digits[i].value = '';
        }
        otp += digits[i].value;
    }
    console.log('Combined OTP:', otp, 'Length:', otp.length);
    document.getElementById('otp-hidden').value = otp;
    fetch('register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'debug_otp=' + encodeURIComponent(otp)
    });
}

function startOTPTimer() {
    let timeLeft = 60;
    const timerElement = document.getElementById('otp-timer');
    const resendButton = document.getElementById('resend-otp');
    resendButton.disabled = true;

    const timer = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timer);
            timerElement.textContent = 'You can now resend OTP';
            resendButton.disabled = false;
        } else {
            timerElement.textContent = `Resend OTP in ${timeLeft}s`;
            timeLeft--;
        }
    }, 1000);

    resendButton.onclick = () => {
        if (!resendButton.disabled) {
            console.log("Resending OTP");
            fetch('register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'resend_otp=true'
            })
            .then(response => response.text())
            .then(data => {
                console.log('Resend OTP response:', data);
                window.location.reload();
            })
            .catch(error => {
                console.error('Resend OTP error:', error);
                document.getElementById('otp-timer').textContent = 'Failed to resend OTP. Please try again.';
            });
        }
    };
}

<?php if ($success_message): ?>
    window.addEventListener('load', () => {
        console.log("Page loaded, showing OTP form due to success message");
        showOTPForm();
    });
<?php endif; ?>
</script>
</body>
</html>