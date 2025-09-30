<?php
session_start();
require_once 'Database.php';

// Database connection settings
$host = 'localhost';
$port = '4306';
$dbname = 'pbl';
$username = 'root';
$password = 'your_password'; // Replace with your phpMyAdmin password

$db = new Database($host, $port, $dbname, $username, $password);
$pdo = $db->getConnection();

// Fetch feedback
$feedbacks = $pdo->query("
    SELECT f.feedback_id, c.first_name, c.last_name, f.rating, f.feedback_text, f.feedback_date
    FROM customer_feedback f
    JOIN customers c ON f.customer_id = c.customer_id
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h1, h2 {
            color: #333;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: #fff;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .back-button {
            display: block;
            text-align: center;
            margin: 20px 0;
        }
        .back-button a {
            color: #007bff;
            text-decoration: none;
        }
        .back-button a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Feedback Management</h1>

        <!-- Customer Feedback Table -->
        <h2>Customer Feedback</h2>
        <table>
            <tr>
                <th>Feedback ID</th>
                <th>Customer</th>
                <th>Rating</th>
                <th>Feedback</th>
                <th>Date</th>
            </tr>
            <?php foreach ($feedbacks as $feedback): ?>
                <tr>
                    <td><?php echo $feedback['feedback_id']; ?></td>
                    <td><?php echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']); ?></td>
                    <td><?php echo $feedback['rating']; ?>/5</td>
                    <td><?php echo htmlspecialchars($feedback['feedback_text'] ?: 'None'); ?></td>
                    <td><?php echo $feedback['feedback_date']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="back-button">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>