<?php
// Start output buffering to prevent accidental output (warnings/notices) from breaking JSON
ob_start();
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

// Admin authentication check - must have is_admin flag set
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
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

    // Batch overview - show batches with today's PENDING orders only
    // Exclude completed/delivered orders
    $stmt = $pdo->prepare("
        SELECT 
            b.vehicle_type, 
            b.batch_number, 
            COUNT(DISTINCT o.reference_id) as order_count,
            COALESCE(SUM(od.quantity), 0) as total_containers,
            bs.status_name as batch_status,
            b.batch_id,
            DATE(b.batch_date) as batch_date
        FROM batches b
        INNER JOIN orders o ON b.batch_id = o.batch_id 
            AND DATE(o.order_date) = ? 
            AND o.order_status_id IN (1, 2)
        LEFT JOIN order_details od ON o.reference_id = od.reference_id
        LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
        GROUP BY b.batch_id, b.vehicle_type, b.batch_number, bs.status_name, DATE(b.batch_date)
        ORDER BY b.vehicle_type, b.batch_number
    ");
    $stmt->execute([$today]);
    $batch_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: log what batches exist
    error_log("Today's date for query: " . $today);
    error_log("Batch details count: " . count($batch_details));
    if (count($batch_details) > 0) {
        error_log("Sample batch: " . json_encode($batch_details[0]));
    }
    
    // Get count of unassigned PENDING orders for today (orders without batch or batch = 0)
    // Exclude completed orders (status_id 3 = Delivered)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unassigned_count
        FROM orders
        WHERE DATE(order_date) = ? 
        AND (batch_id IS NULL OR batch_id = 0)
        AND order_status_id IN (1, 2)
    ");
    $stmt->execute([$today]);
    $unassigned_orders = (int)$stmt->fetchColumn();
    
    // Get all today's orders with their batch info for debugging
    $stmt = $pdo->prepare("
        SELECT 
            o.reference_id,
            o.batch_id,
            o.order_date,
            o.delivery_date,
            b.batch_date,
            b.vehicle_type,
            b.batch_number
        FROM orders o
        LEFT JOIN batches b ON o.batch_id = b.batch_id
        WHERE DATE(o.order_date) = ?
    ");
    $stmt->execute([$today]);
    $todays_orders_debug = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Unassigned orders count: " . $unassigned_orders);
    error_log("Today's orders debug: " . json_encode($todays_orders_debug));

    // Order history (all time, latest 20)
    $stmt = $pdo->prepare("
        SELECT 
            o.reference_id, 
            o.order_date, 
            o.total_amount, 
            os.status_name as order_status,
            c.first_name,
            c.last_name,
            b.vehicle_type,
            b.batch_number
        FROM orders o
        LEFT JOIN order_status os ON o.order_status_id = os.status_id
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN batches b ON o.batch_id = b.batch_id
        ORDER BY o.order_date DESC
        LIMIT 20
    ");
    $stmt->execute();
    $order_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                'pending_deliveries' => $pending_deliveries,
                'unassigned_orders' => $unassigned_orders
            ],
            'recent_orders' => $recent_orders,
            'batch_details' => $batch_details,
            'order_history' => $order_history
        ]
    ]);
} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching dashboard data: ' . $e->getMessage()]);
}

// Ensure nothing else is appended
exit;


