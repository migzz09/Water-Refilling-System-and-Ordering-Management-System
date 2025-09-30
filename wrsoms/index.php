<?php
session_start();

// Diagnostic checks
$error_message = [];
$files_to_check = [
    'order_placement.php' => 'Place an Order',
    'order_tracking.php' => 'Track an Order',
    'batch_management.php' => 'View Batches',
    'delivery_management.php' => 'View Deliveries',
    'feedback_management.php' => 'View Feedback',
    'connect.php' => 'Database Connection'
];

foreach ($files_to_check as $file => $description) {
    if (!file_exists($file)) {
        $error_message[] = "Error: $file (required for '$description') not found in the current directory.";
    }
}

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
        }
        h1 {
            color: #333;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }
        .nav-button {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            margin: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .nav-button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            text-align: center;
            margin: 10px 0;
        }
        .debug {
            color: #555;
            text-align: center;
            margin: 10px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Water Refilling Station Management</h1>
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
            <p>Database name: pbl</p>
        </div>
        <a href="order_placement.php" class="nav-button">Place an Order</a>
        <a href="order_tracking.php" class="nav-button">Track an Order</a>
        <a href="batch_management.php" class="nav-button">View Batches</a>
        <a href="delivery_management.php" class="nav-button">View Deliveries</a>
        <a href="feedback_management.php" class="nav-button">View Feedback</a>
    </div>
    <script>
        // JavaScript to log link clicks for debugging
        document.querySelectorAll('.nav-button').forEach(link => {
            link.addEventListener('click', (e) => {
                console.log('Navigating to: ' + e.target.href);
            });
        });
    </script>
</body>
</html>