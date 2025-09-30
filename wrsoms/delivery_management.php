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

// Fetch deliveries
$deliveries = $pdo->query("
    SELECT d.delivery_id, b.vehicle, b.vehicle_type, ds.status_name, d.delivery_date, d.notes
    FROM deliveries d
    JOIN batches b ON d.batch_id = b.batch_id
    JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Management</title>
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
        <h1>Delivery Management</h1>

        <!-- Deliveries Table -->
        <h2>Deliveries</h2>
        <table>
            <tr>
                <th>Delivery ID</th>
                <th>Vehicle</th>
                <th>Vehicle Type</th>
                <th>Status</th>
                <th>Delivery Date</th>
                <th>Notes</th>
            </tr>
            <?php foreach ($deliveries as $delivery): ?>
                <tr>
                    <td><?php echo $delivery['delivery_id']; ?></td>
                    <td><?php echo htmlspecialchars($delivery['vehicle']); ?></td>
                    <td><?php echo htmlspecialchars($delivery['vehicle_type']); ?></td>
                    <td><?php echo htmlspecialchars($delivery['status_name']); ?></td>
                    <td><?php echo $delivery['delivery_date']; ?></td>
                    <td><?php echo htmlspecialchars($delivery['notes'] ?: 'None'); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="back-button">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>