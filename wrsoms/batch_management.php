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

// Fetch batches
$batches = $pdo->query("
    SELECT b.batch_id, b.vehicle, b.vehicle_type, bs.status_name,
           GROUP_CONCAT(e.first_name, ' ', e.last_name, ' (', COALESCE(e.role, 'None'), ')') as employees
    FROM batches b
    JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
    LEFT JOIN batch_employees be ON b.batch_id = be.batch_id
    LEFT JOIN employees e ON be.employee_id = e.employee_id
    GROUP BY b.batch_id
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Management</title>
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
        <h1>Batch Management</h1>

        <!-- Batches Table -->
        <h2>Batches</h2>
        <table>
            <tr>
                <th>Batch ID</th>
                <th>Vehicle</th>
                <th>Vehicle Type</th>
                <th>Status</th>
                <th>Employees</th>
            </tr>
            <?php foreach ($batches as $batch): ?>
                <tr>
                    <td><?php echo $batch['batch_id']; ?></td>
                    <td><?php echo htmlspecialchars($batch['vehicle']); ?></td>
                    <td><?php echo htmlspecialchars($batch['vehicle_type']); ?></td>
                    <td><?php echo htmlspecialchars($batch['status_name']); ?></td>
                    <td><?php echo htmlspecialchars($batch['employees'] ?: 'None'); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <div class="back-button">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>