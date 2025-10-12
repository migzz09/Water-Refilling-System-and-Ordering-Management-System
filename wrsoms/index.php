<?php
session_start();
require_once 'connect.php';

// Diagnostic checks
$error_message = [];
$files_to_check = [
    'order_placement.php' => 'Place an Order',
    'order_tracking.php' => 'Track an Order',
    'connect.php' => 'Database Connection',
    'login.php' => 'Customer Login',
    'register.php' => 'Customer Registration',
    'logout.php' => 'Customer Logout'
];

foreach ($files_to_check as $file => $description) {
    if (!file_exists($file)) {
        $error_message[] = "Error: $file (required for '$description') not found in the current directory.";
    }
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['customer_id']) && isset($_SESSION['username']);

// Get current directory and server details for debugging
$current_directory = __DIR__;
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Refilling Station Management</title>
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
            max-width: 900px;
            width: 90%;
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 15px;
        }
        h1 {
            color: #333;
            text-align: center;
            font-size: 24px;
            margin: 0 0 20px 0;
        }
        .nav-button {
            background-color: #007bff;
            color: #fff;
            padding: 12px 24px;
            margin: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 16px;
            flex: 1 1 200px;
            max-width: 250px;
            text-align: center;
        }
        .nav-button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            text-align: center;
            margin: 10px 0;
            font-size: 14px;
        }
        .debug {
            color: #555;
            text-align: center;
            margin: 10px 0;
            font-size: 12px;
        }
        .welcome {
            color: #333;
            text-align: center;
            font-size: 16px;
        }
        @media (max-width: 768px) {
            .container {
                padding: 10px;
                gap: 10px;
            }
            h1 {
                font-size: 20px;
            }
            .nav-button {
                padding: 10px 20px;
                font-size: 14px;
                margin: 5px;
                flex: 1 1 100%;
                max-width: 100%;
            }
            .error, .debug, .welcome {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Water Refilling Station Management</h1>
        <?php if ($is_logged_in): ?>
            <div class="welcome">
                <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</p>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="error">
                <ul>
                    <?php foreach ($error_message as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <div class="debug">
            <p>Current directory: <?php echo htmlspecialchars($current_directory); ?></p>
            <p>Base URL: <?php echo htmlspecialchars($base_url); ?></p>
            <p>Database name: wrsoms</p>
        </div>
        <div>
            <?php if ($is_logged_in): ?>
                <a href="order_placement.php" class="nav-button">Place an Order</a>
                <a href="order_tracking.php" class="nav-button">Track an Order</a>
                <a href="logout.php" class="nav-button">Logout</a>
            <?php else: ?>
                <a href="login.php" class="nav-button">Login</a>
                <a href="register.php" class="nav-button">Register</a>
                <a href="order_tracking.php" class="nav-button">Track an Order</a>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.querySelectorAll('.nav-button').forEach(link => {
            link.addEventListener('click', (e) => {
                console.log('Navigating to: ' + e.target.href);
            });
        });
    </script>
</body>
</html>
