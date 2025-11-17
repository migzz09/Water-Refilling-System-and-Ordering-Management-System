<?php
session_start();
require_once 'connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp'] ?? '');
    $email = $_SESSION['registered_email'] ?? '';

    $errors = [];

    if (empty($entered_otp)) {
        $errors[] = "OTP is required.";
    }
    if (empty($email)) {
        $errors[] = "No email found in session. Please register again.";
    }

    if (empty($errors)) {
        try {
            // Debug: Check current time and database values
            $current_time = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                SELECT a.*, c.email 
                FROM accounts a
                JOIN customers c ON a.customer_id = c.customer_id
                WHERE c.email = ? AND a.otp = ? AND a.otp_expires > NOW()
            ");
            $stmt->execute([$email, $entered_otp]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($account) {
                // Verify the account
                $stmt = $pdo->prepare("UPDATE accounts SET is_verified = 1, otp = NULL, otp_expires = NULL WHERE account_id = ?");
                $stmt->execute([$account['account_id']]);

                // Clear session and redirect
                unset($_SESSION['registered_email']);
                header("Location: login.php?success=Account verified successfully. Please log in.");
                exit;
            } else {
                // Debug: Log detailed information
                $stmt = $pdo->prepare("SELECT a.otp, a.otp_expires, c.email FROM accounts a JOIN customers c ON a.customer_id = c.customer_id WHERE c.email = ?");
                $stmt->execute([$email]);
                $debug_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $debug_message = "OTP Verification Failed: Entered OTP: '$entered_otp', Stored OTP: " . ($debug_data['otp'] ?? 'null') . 
                                ", Expires: " . ($debug_data['otp_expires'] ?? 'null') . 
                                ", Current Time: $current_time, Email: " . ($debug_data['email'] ?? 'null');
                error_log($debug_message);
                $errors[] = "Invalid or expired OTP. Please try again or request a new one.";
            }
        } catch (PDOException $e) {
            $errors[] = "Error verifying OTP: " . $e->getMessage();
            error_log("PDO Exception: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - Water Refilling Station</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            width: 100%;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
        .success {
            color: green;
            margin-bottom: 10px;
            text-align: center;
        }
        .back-button {
            text-align: center;
            margin-top: 10px;
        }
        .back-button a {
            color: #007bff;
            text-decoration: none;
        }
        .back-button a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .container { padding: 15px; }
            input[type="submit"] { padding: 8px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Verify Your OTP</h1>
        <?php if (isset($_GET['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_GET['success']); ?></div>
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
        <form method="POST" action="verify_otp.php">
            <div class="form-group">
                <label for="otp">Enter OTP</label>
                <input type="text" name="otp" id="otp" required>
            </div>
            <input type="submit" value="Verify OTP">
        </form>
        <div class="back-button">
            <a href="register.php">Back to Register</a>
        </div>
    </div>
</body>
</html>