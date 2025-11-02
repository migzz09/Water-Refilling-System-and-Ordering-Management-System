<?php
// Debug endpoint to help diagnose why manage_orders returns empty results
ob_start();
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

try {
    // Total orders today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $totalOrders = (int)$stmt->fetchColumn();

    // Batches today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE DATE(batch_date) = ?");
    $stmt->execute([$today]);
    $totalBatches = (int)$stmt->fetchColumn();

    // Unassigned orders (view)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_management_view WHERE batch_id IS NULL AND DATE(order_date) = ?");
    $stmt->execute([$today]);
    $unassigned = (int)$stmt->fetchColumn();

    // Sample orders
    $stmt = $pdo->prepare("SELECT reference_id, order_date, delivery_date, batch_id, total_amount FROM orders WHERE DATE(order_date) = ? ORDER BY order_date DESC LIMIT 10");
    $stmt->execute([$today]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'data' => [
            'today' => $today,
            'total_orders_today' => $totalOrders,
            'total_batches_today' => $totalBatches,
            'unassigned_view_count' => $unassigned,
            'sample_orders' => $orders
        ]
    ]);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit;
