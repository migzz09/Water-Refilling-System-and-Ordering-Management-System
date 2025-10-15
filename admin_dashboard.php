<?php
session_start();
require_once 'connect.php';

// Set Philippine time (Asia/Manila, UTC+8)
date_default_timezone_set('Asia/Manila');

// Fetch order statistics for today (October 15, 2025)
$today = date('Y-m-d', strtotime('2025-10-15'));
try {
    // Total Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $total_orders = $stmt->fetchColumn();

    // Pending Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE order_status_id = 1 AND DATE(order_date) = ?");
    $stmt->execute([$today]);
    $pending_orders = $stmt->fetchColumn();

    // Completed Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_orders FROM orders WHERE order_status_id = 3 AND DATE(order_date) = ?");
    $stmt->execute([$today]);
    $completed_orders = $stmt->fetchColumn();

    // Total Revenue
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $total_revenue = $stmt->fetchColumn();

    // Average Order Value
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(total_amount), 0) as avg_order_value FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $avg_order_value = $stmt->fetchColumn();

    // Pending Deliveries
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_deliveries
        FROM orders o
        LEFT JOIN deliveries d ON o.batch_id = d.batch_id
        WHERE DATE(o.order_date) = ? AND (d.delivery_status_id = 1 OR d.delivery_status_id IS NULL)
    ");
    $stmt->execute([$today]);
    $pending_deliveries = $stmt->fetchColumn();

    // Recent Orders
    $stmt = $pdo->prepare("
        SELECT o.reference_id, o.order_date, o.total_amount, os.status_name as order_status
        FROM orders o
        LEFT JOIN order_status os ON o.order_status_id = os.status_id
        WHERE DATE(o.order_date) = ?
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Batch Overview
    $stmt = $pdo->prepare("
        SELECT 
            b.vehicle_type, 
            b.batch_number, 
            COUNT(o.reference_id) as order_count,
            bs.status_name as batch_status
        FROM batches b
        LEFT JOIN orders o ON b.batch_id = o.batch_id AND DATE(o.order_date) = ?
        LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
        WHERE DATE(b.batch_date) = ?
        GROUP BY b.vehicle_type, b.batch_number, bs.status_name
        HAVING b.batch_number BETWEEN 1 AND 3
        ORDER BY b.vehicle_type, b.batch_number
    ");
    $stmt->execute([$today, $today]);
    $batch_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching order statistics: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WaterWorld</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #F7FAFC;
            color: #1A202C;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        .sidebar {
            width: 64px;
            background: #1A202C;
            color: #E2E8F0;
            position: fixed;
            height: 100vh;
            padding: 24px 0;
            transition: width 0.3s ease;
            z-index: 1000;
        }

        .sidebar:hover {
            width: 240px;
        }

        .sidebar .logo {
            font-size: 20px;
            font-weight: 600;
            color: #3B82F6;
            text-align: center;
            margin-bottom: 32px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar:hover .logo {
            opacity: 1;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar ul li {
            padding: 16px;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .sidebar ul li:hover {
            background: #2D3748;
        }

        .sidebar ul li.active {
            background: #3B82F6;
            color: #FFFFFF;
            position: relative;
        }

        .sidebar ul li a {
            color: inherit;
            text-decoration: none;
            font-size: 14px;
            opacity: 0;
            transition: opacity 0.3s ease;
            display: block;
            text-align: center;
        }

        .sidebar:hover ul li a {
            opacity: 1;
            text-align: left;
        }

        .content {
            margin-left: 64px;
            padding: 24px;
            width: calc(100% - 64px);
            background: #FFFFFF;
            min-height: 100vh;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        .sidebar:hover ~ .content {
            margin-left: 240px;
            width: calc(100% - 240px);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid #E2E8F0;
        }

        .header .title {
            font-size: 24px;
            font-weight: 600;
            color: #1A202C;
        }

        .header .date-time {
            font-size: 14px;
            color: #6B7280;
        }

        .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            background: #FFFFFF;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .metric-card h3 {
            font-size: 12px;
            font-weight: 500;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .metric-card p {
            font-size: 20px;
            font-weight: 600;
            color: #1A202C;
        }

        .metric-card[data-color="blue"] { border-left: 4px solid #3B82F6; }
        .metric-card[data-color="green"] { border-left: 4px solid #10B981; }
        .metric-card[data-color="purple"] { border-left: 4px solid #8B5CF6; }
        .metric-card[data-color="teal"] { border-left: 4px solid #14B8A6; }
        .metric-card[data-color="pink"] { border-left: 4px solid #EC4899; }
        .metric-card[data-color="gray"] { border-left: 4px solid #6B7280; }

        .quick-actions {
            margin-bottom: 24px;
        }

        .quick-actions button {
            background: #3B82F6;
            color: #FFFFFF;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .quick-actions button:hover {
            background: #2563EB;
            transform: translateY(-2px);
        }

        .batch-table, .recent-orders-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #FFFFFF;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .batch-table th,
        .batch-table td,
        .recent-orders-table th,
        .recent-orders-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
        }

        .batch-table th,
        .recent-orders-table th {
            background: #3B82F6;
            color: #FFFFFF;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .batch-table tr:hover,
        .recent-orders-table tr:hover {
            background: #F7FAFC;
        }

        .batch-table tr:last-child td,
        .recent-orders-table tr:last-child td {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .sidebar:hover {
                width: 100%;
            }
            .content {
                margin-left: 0;
                width: 100%;
                padding: 16px;
            }
            .sidebar:hover ~ .content {
                margin-left: 0;
                width: 100%;
            }
            .metrics {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">WaterWorld</div>
        <ul>
            <li class="active"><a href="#">Dashboard</a></li>
            <li><a href="admin.php">Manage Orders</a></li>
        </ul>
    </div>
    <div class="content">
        <div class="header">
            <div class="title">Dashboard</div>
            <div class="date-time">Date: <?php echo date('F d, Y', strtotime('2025-10-15')); ?> | Time: <?php echo date('h:i A T'); ?></div>
        </div>
        <div class="quick-actions">
            <button onclick="location.reload();">Refresh Data</button>
        </div>
        <div class="metrics">
            <div class="metric-card" data-color="blue">
                <h3>Total Orders</h3>
                <p><?php echo htmlspecialchars($total_orders); ?></p>
            </div>
            <div class="metric-card" data-color="green">
                <h3>Pending Orders</h3>
                <p><?php echo htmlspecialchars($pending_orders); ?></p>
            </div>
            <div class="metric-card" data-color="purple">
                <h3>Completed Orders</h3>
                <p><?php echo htmlspecialchars($completed_orders); ?></p>
            </div>
            <div class="metric-card" data-color="teal">
                <h3>Total Revenue</h3>
                <p><?php echo '$' . number_format($total_revenue, 2); ?></p>
            </div>
            <div class="metric-card" data-color="pink">
                <h3>Avg Order Value</h3>
                <p><?php echo '$' . number_format($avg_order_value, 2); ?></p>
            </div>
            <div class="metric-card" data-color="gray">
                <h3>Pending Deliveries</h3>
                <p><?php echo htmlspecialchars($pending_deliveries); ?></p>
            </div>
        </div>
        <div class="metric-card">
            <h3>Batch Overview</h3>
            <table class="batch-table">
                <tr>
                    <th>Vehicle Type</th>
                    <th>Batch #</th>
                    <th>Orders</th>
                    <th>Status</th>
                </tr>
                <?php
                $batches = [];
                foreach ($batch_details as $batch) {
                    $key = $batch['vehicle_type'] . '-' . $batch['batch_number'];
                    $batches[$key] = $batch;
                }
                for ($i = 1; $i <= 3; $i++) {
                    foreach (['Tricycle', 'Car'] as $vehicle_type) {
                        $key = $vehicle_type . '-' . $i;
                        $batch = $batches[$key] ?? ['vehicle_type' => $vehicle_type, 'batch_number' => $i, 'order_count' => 0, 'batch_status' => 'N/A'];
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($batch['vehicle_type']) . "</td>";
                        echo "<td>#" . htmlspecialchars($batch['batch_number']) . "</td>";
                        echo "<td>" . htmlspecialchars($batch['order_count']) . "</td>";
                        echo "<td>" . htmlspecialchars($batch['batch_status']) . "</td>";
                        echo "</tr>";
                    }
                }
                ?>
            </table>
        </div>
        <div class="metric-card">
            <h3>Recent Orders</h3>
            <table class="recent-orders-table">
                <tr>
                    <th>Order ID</th>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
                <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['reference_id']); ?></td>
                        <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                        <td><?php echo '$' . number_format($order['total_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($order['order_status']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>