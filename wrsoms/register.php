<?php
session_start();
require_once 'connect.php';
$config = require_once '../config/config.php'; // Adjust path (e.g., go up one directory)

// Include PHPMailer manually (adjust path based on your structure)
require_once 'phpmailer-master/src/Exception.php';
require_once 'phpmailer-master/src/PHPMailer.php';
require_once 'phpmailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Enable output buffering to handle SMTPDebug output
ob_start();

// NCR cities and barangays
$ncr_cities = [
    'Taguig' => [
        'Bagumbayan', 'Bambang', 'Calzada', 'Central Bicutan', 'Central Signal Village',
        'Fort Bonifacio', 'Hagonoy', 'Ibayo-Tipas', 'Katuparan', 'Ligid-Tipas',
        'Lower Bicutan', 'Maharlika Village', 'Napindan', 'New Lower Bicutan',
        'North Daang Hari', 'North Signal Village', 'Palingon', 'Pinagsama',
        'San Miguel', 'Santa Ana', 'South Daang Hari', 'South Signal Village',
        'Tanyag', 'Tuktukan', 'Upper Bicutan', 'Ususan', 'Wawa', 'Western Bicutan',
        'Comembo', 'Cembo', 'South Cembo', 'East Rembo', 'West Rembo', 'Pembo',
        'Pitogo', 'Post Proper Northside', 'Post Proper Southside', 'Rizal'
    ],
    'Quezon City' => [
        'Bagong Pag-asa', 'Batasan Hills', 'Commonwealth', 'Holy Spirit', 'Payatas'
    ],
    'Manila' => [
        'Tondo', 'Binondo', 'Ermita', 'Malate', 'Paco'
    ],
    'Makati' => [
        'Bangkal', 'Bel-Air', 'Magallanes', 'Pio del Pilar', 'San Lorenzo'
    ],
    'Pasig' => [
        'Bagong Ilog', 'Oranbo', 'San Antonio', 'Santa Lucia', 'Ugong'
    ],
    'Pateros' => [
        'Aguho', 'Martyrs', 'San Roque', 'Santa Ana'
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $customer_contact = trim($_POST['customer_contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');

    $errors = [];

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

    // Check for unique username and email, handle unverified accounts
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM accounts WHERE username = ? UNION 
            SELECT COUNT(*) FROM customers WHERE email = ?
        ");
        $stmt->execute([$username, $email]);
        $counts = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $unverified_account = null;
        if (in_array(1, $counts)) {
            // Check if the account is unverified
            $stmt = $pdo->prepare("SELECT account_id FROM accounts WHERE username = ? AND is_verified = 0");
            $stmt->execute([$username]);
            $unverified_account = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($unverified_account) {
            // Resend OTP for unverified account
            $otp = sprintf("%06d", mt_rand(100000, 999999));
            $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            error_log("Resending OTP: OTP: $otp, Expires: $otp_expires, Account ID: " . $unverified_account['account_id']);

            $stmt = $pdo->prepare("UPDATE accounts SET otp = ?, otp_expires = ? WHERE account_id = ?");
            $stmt->execute([$otp, $otp_expires, $unverified_account['account_id']]);

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'migzzuwu@gmail.com';
                $mail->Password = 'xqav cuon wpxs spcv';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                if (!$mail->smtpConnect()) {
                    $mail->Port = 465;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
                $mail->SMTPDebug = 2;

                $mail->setFrom('migzzuwu@gmail.com', 'WaterWorld Admin');
                $mail->addAddress($email, "$first_name $last_name");

                $mail->isHTML(true);
                $mail->Subject = 'New OTP for Registration';
                $mail->Body = "Hello $first_name,<br>A new OTP for registration is: <b>$otp</b><br>This OTP is valid for 10 minutes.";
                $mail->AltBody = "Hello $first_name,\nA new OTP for registration is: $otp\nThis OTP is valid for 10 minutes.";

                $mail->send();
                $_SESSION['registered_email'] = $email;
                ob_end_clean();
                header("Location: verify_otp.php?success=New OTP sent to your email. Please verify.");
                exit;
            } catch (Exception $e) {
                $errors[] = "Failed to resend OTP: " . $mail->ErrorInfo;
            }
        } elseif (in_array(1, $counts)) {
            $errors[] = "Username or email already exists and is verified. Please log in or use a different email/username.";
        }
    } catch (PDOException $e) {
        $errors[] = "Error checking username/email: " . $e->getMessage();
    }

    if (empty($errors) && !$unverified_account) {
        // Generate OTP for new registration
        $otp = sprintf("%06d", mt_rand(100000, 999999));
        $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        error_log("Registration: OTP: $otp, Expires: $otp_expires");

        try {
            $pdo->beginTransaction();

            // Insert into customers
            $stmt = $pdo->prepare("
                INSERT INTO customers (first_name, last_name, customer_contact, email, street, barangay, city, province, date_created)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Metro Manila', NOW())
            ");
            $stmt->execute([$first_name, $last_name, $customer_contact, $email, $street, $barangay, $city]);
            $customer_id = $pdo->lastInsertId();

            // Insert into accounts with OTP
            $stmt = $pdo->prepare("
                INSERT INTO accounts (customer_id, username, password, otp, otp_expires, is_verified)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$customer_id, $username, $password, $otp, $otp_expires]);

            $pdo->commit();

            // Send OTP via PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = $config['gmail_username'];
                $mail->Password = $config['gmail_app_password'];
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                if (!$mail->smtpConnect()) {
                    $mail->Port = 465;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                }
                $mail->SMTPDebug = 2;

                $mail->setFrom($config['gmail_username'], 'Water World Admin');
                $mail->addAddress($email, "$first_name $last_name");

                $mail->isHTML(true);
                $mail->Subject = 'Your OTP for Registration';
                $mail->Body = "Hello $first_name,<br>Your OTP for registration is: <b>$otp</b><br>This OTP is valid for 10 minutes.";
                $mail->AltBody = "Hello $first_name,\nYour OTP for registration is: $otp\nThis OTP is valid for 10 minutes.";

                $mail->send();
                $_SESSION['registered_email'] = $email;
                ob_end_clean();
                header("Location: verify_otp.php?success=OTP sent to your email. Please verify.");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to send OTP: " . $mail->ErrorInfo;
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Water Refilling Station</title>
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
        input[type="text"], input[type="password"], input[type="email"], select {
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
        <h1>Register</h1>
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
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
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
            <input type="submit" value="Register">
        </form>
        <div class="back-button">
            <a href="../index.php">Back to Home</a>
        </div>
    </div>

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
    </script>
</body>
</html>
