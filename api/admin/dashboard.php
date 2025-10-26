<?php
/**
 * Admin Dashboard API
 * Method: GET
 * Returns dashboard statistics
 */
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

// Check if user is admin (add your admin check logic here)
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Get total orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
    $totalOrders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get pending orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders WHERE order_status_id = 1");
    $pendingOrders = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get total revenue
    $stmt = $pdo->query("SELECT SUM(total_amount) as revenue FROM orders WHERE order_status_id = 3");
    $totalRevenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

    // Get total customers
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM customers");
    $totalCustomers = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get recent orders
    $stmt = $pdo->query("
        SELECT o.reference_id, o.order_date, o.total_amount, os.status_name,
               CONCAT(c.first_name, ' ', c.last_name) as customer_name
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN order_status os ON o.order_status_id = os.status_id
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'stats' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'total_revenue' => $totalRevenue,
                'total_customers' => $totalCustomers
            ],
            'recent_orders' => $recentOrders
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error fetching dashboard data: ' . $e->getMessage()]);
}
