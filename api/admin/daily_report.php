<?php
/**
 * Daily Report API Endpoint
 * Returns daily sales statistics, charts data, and recent orders
 */
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');


// Allow admin or staff with 'Sales Manager' role
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$isSalesManager = false;
if (isset($_SESSION['username'])) {
    $stmt = $pdo->prepare('SELECT staff_role FROM staff WHERE staff_user = ? LIMIT 1');
    $stmt->execute([$_SESSION['username']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && stripos($row['staff_role'], 'sales') !== false) {
        $isSalesManager = true;
    }
}
if (!$isAdmin && !$isSalesManager) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin or Sales Manager access required'
    ]);
    exit;
}

// Get date parameter or default to today
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid date format. Use YYYY-MM-DD'
    ]);
    exit;
}

try {
    // Calculate previous and next dates
    $prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
    $next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
    $is_today = ($selected_date === date('Y-m-d'));

    // Total revenue for selected date
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total_amount), 0) as total_revenue,
               COUNT(*) as orders_today
        FROM orders
        WHERE DATE(order_date) = ?
    ");
    $stmt->execute([$selected_date]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_revenue = $stats['total_revenue'];
    $orders_today = $stats['orders_today'];

    // Failed orders for selected date (order_status_id = 4 means 'Failed')
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as failed_orders
        FROM orders
        WHERE DATE(order_date) = ? AND order_status_id = 4
    ");
    $stmt->execute([$selected_date]);
    $failed_orders_today = $stmt->fetch(PDO::FETCH_ASSOC)['failed_orders'];

    // New customers registered on selected date
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as new_customers
        FROM customers
        WHERE DATE(date_created) = ?
    ");
    $stmt->execute([$selected_date]);
    $new_customers_today = $stmt->fetch(PDO::FETCH_ASSOC)['new_customers'];

    // Completed payments for selected date
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(o.total_amount), 0) as completed_payments
        FROM orders o
        JOIN payments p ON o.reference_id = p.reference_id
        WHERE DATE(o.order_date) = ?
        AND p.payment_status_id = 2
    ");
    $stmt->execute([$selected_date]);
    $completed_payments_today = $stmt->fetch(PDO::FETCH_ASSOC)['completed_payments'];

    // Revenue data for last 7 days (for line chart)
    $stmt = $pdo->prepare("
        SELECT DATE(order_date) as date,
               COALESCE(SUM(total_amount), 0) as revenue,
               COUNT(*) as order_count
        FROM orders
        WHERE order_date >= DATE_SUB(?, INTERVAL 6 DAY)
        AND order_date <= ?
        GROUP BY DATE(order_date)
        ORDER BY date
    ");
    $stmt->execute([$selected_date, $selected_date]);
    $last_7_days = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill in missing dates with zero values
    $labels = [];
    $revenue_data = [];
    $orders_data = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime($selected_date . " -$i day"));
        $labels[] = date('M j', strtotime($date));
        
        // Find matching data or use 0
        $found = false;
        foreach ($last_7_days as $row) {
            if ($row['date'] === $date) {
                $revenue_data[] = floatval($row['revenue']);
                $orders_data[] = intval($row['order_count']);
                $found = true;
                break;
            }
        }
        if (!$found) {
            $revenue_data[] = 0;
            $orders_data[] = 0;
        }
    }

    // Payments by method (last 30 days from selected date)
    $stmt = $pdo->prepare("
        SELECT pm.method_name,
               COALESCE(SUM(p.amount_paid), 0) as total
        FROM payment_methods pm
        LEFT JOIN payments p ON pm.payment_method_id = p.payment_method_id
            AND p.payment_date >= DATE_SUB(?, INTERVAL 30 DAY)
            AND p.payment_date <= ?
        GROUP BY pm.payment_method_id, pm.method_name
        HAVING total > 0
        ORDER BY total DESC
    ");
    $stmt->execute([$selected_date, $selected_date]);
    $payments_by_method = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent orders for selected date
    $stmt = $pdo->prepare("
        SELECT o.reference_id,
               o.order_date,
               o.total_amount,
               CONCAT(c.first_name, ' ', c.last_name) as customer_name
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        WHERE DATE(o.order_date) = ?
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $stmt->execute([$selected_date]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top selling containers (last 30 days from selected date)
    $stmt = $pdo->prepare("
        SELECT cont.container_type,
               SUM(od.quantity) as qty,
               SUM(od.subtotal) as revenue
        FROM order_details od
        JOIN containers cont ON od.container_id = cont.container_id
        JOIN orders o ON od.reference_id = o.reference_id
        WHERE o.order_date >= DATE_SUB(?, INTERVAL 30 DAY)
        AND o.order_date <= ?
        GROUP BY cont.container_id, cont.container_type
        ORDER BY revenue DESC
    ");
    $stmt->execute([$selected_date, $selected_date]);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get list of dates with orders (for date selector)
    $stmt = $pdo->query("
        SELECT DISTINCT DATE(order_date) as date
        FROM orders
        ORDER BY date DESC
        LIMIT 30
    ");
    $available_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'data' => [
            'selected_date' => $selected_date,
            'prev_date' => $prev_date,
            'next_date' => $next_date,
            'is_today' => $is_today,
            'stats' => [
                'total_revenue' => floatval($total_revenue),
                'orders_today' => intval($orders_today),
                'failed_orders_today' => intval($failed_orders_today),
                'new_customers_today' => intval($new_customers_today),
                'completed_payments_today' => floatval($completed_payments_today)
            ],
            'charts' => [
                'labels' => $labels,
                'revenue_data' => $revenue_data,
                'orders_data' => $orders_data,
                'payments_by_method' => $payments_by_method
            ],
            'recent_orders' => $recent_orders,
            'top_products' => $top_products,
            'available_dates' => $available_dates
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
