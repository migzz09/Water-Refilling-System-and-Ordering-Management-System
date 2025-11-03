<?php
// Start output buffering to prevent accidental output (warnings/notices) from breaking JSON
ob_start();
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

// Basic auth check - adapt to your admin session logic as needed
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Use Manila timezone for daily queries
date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

try {
    // Total Orders (today)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $total_orders = (int)$stmt->fetchColumn();

    // Pending Orders (today)
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE order_status_id = 1 AND DATE(order_date) = ?");
    $stmt->execute([$today]);
    $pending_orders = (int)$stmt->fetchColumn();

    // Completed Orders (today)
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_orders FROM orders WHERE order_status_id = 3 AND DATE(order_date) = ?");
    $stmt->execute([$today]);
    $completed_orders = (int)$stmt->fetchColumn();

    // Total Revenue (today)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) as total_revenue FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $total_revenue = (float)$stmt->fetchColumn();

    // Avg order value (today)
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(total_amount),0) as avg_order_value FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $avg_order_value = (float)$stmt->fetchColumn();

    // Pending deliveries (today)
    $stmt = $pdo->prepare("\n        SELECT COUNT(DISTINCT o.reference_id) as pending_deliveries\n        FROM orders o\n        LEFT JOIN deliveries d ON o.batch_id = d.batch_id\n        WHERE DATE(o.order_date) = ? AND (d.delivery_status_id = 1 OR d.delivery_status_id IS NULL)\n    ");
    $stmt->execute([$today]);
    $pending_deliveries = (int)$stmt->fetchColumn();

    // Recent orders (today latest 10)
    $stmt = $pdo->prepare("\n        SELECT o.reference_id, o.order_date, o.total_amount, os.status_name as order_status\n        FROM orders o\n        LEFT JOIN order_status os ON o.order_status_id = os.status_id\n        WHERE DATE(o.order_date) = ?\n        ORDER BY o.order_date DESC\n        LIMIT 10\n    ");
    $stmt->execute([$today]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Batch overview for today
    $stmt = $pdo->prepare("\n        SELECT \n            b.vehicle_type, \n            b.batch_number, \n            COUNT(o.reference_id) as order_count,\n            bs.status_name as batch_status\n        FROM batches b\n        LEFT JOIN orders o ON b.batch_id = o.batch_id AND DATE(o.order_date) = ?\n        LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id\n        WHERE DATE(b.batch_date) = ?\n        GROUP BY b.vehicle_type, b.batch_number, bs.status_name\n        HAVING b.batch_number BETWEEN 1 AND 3\n        ORDER BY b.vehicle_type, b.batch_number\n    ");
    $stmt->execute([$today, $today]);
    $batch_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Clear any accidental output and respond with clean JSON
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => [
                'total_orders' => $total_orders,
                'pending_orders' => $pending_orders,
                'completed_orders' => $completed_orders,
                'total_revenue' => $total_revenue,
                'avg_order_value' => $avg_order_value,
                'pending_deliveries' => $pending_deliveries
            ],
            'recent_orders' => $recent_orders,
            'batch_details' => $batch_details
        ]
    ]);
} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching dashboard data: ' . $e->getMessage()]);
}

// Ensure nothing else is appended
exit;


