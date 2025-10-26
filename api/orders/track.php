<?php
/**
 * Track Order API Endpoint
 * Method: GET
 * Query: ?reference_id=WW20231027
 */
require_once '../../config/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$referenceId = $_GET['reference_id'] ?? '';

if (empty($referenceId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Reference ID is required']);
    exit;
}

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, c.first_name, c.last_name, c.customer_contact, c.street, c.barangay, c.city, c.province,
               ot.type_name AS order_type, os.status_name AS order_status
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN order_types ot ON o.order_type_id = ot.order_type_id
        JOIN order_status os ON o.order_status_id = os.status_id
        WHERE o.reference_id = ?
    ");
    $stmt->execute([$referenceId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        exit;
    }

    // Get order items
    $stmt = $pdo->prepare("
        SELECT od.quantity, od.subtotal, con.container_type
        FROM order_details od
        JOIN containers con ON od.container_id = con.container_id
        WHERE od.reference_id = ?
    ");
    $stmt->execute([$referenceId]);
    $order['details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get batch info if available
    if ($order['batch_id']) {
        $stmt = $pdo->prepare("
            SELECT b.vehicle, b.vehicle_type, b.notes AS batch_notes, bs.status_name AS batch_status,
                   GROUP_CONCAT(CONCAT(e.first_name, ' ', e.last_name) SEPARATOR ', ') AS employees
            FROM batches b
            JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
            LEFT JOIN batch_employees be ON b.batch_id = be.batch_id
            LEFT JOIN employees e ON be.employee_id = e.employee_id
            WHERE b.batch_id = ?
            GROUP BY b.batch_id
        ");
        $stmt->execute([$order['batch_id']]);
        $order['batch'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get delivery info
        $stmt = $pdo->prepare("
            SELECT d.delivery_date, d.notes AS delivery_notes, ds.status_name AS delivery_status
            FROM deliveries d
            JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
            WHERE d.batch_id = ?
        ");
        $stmt->execute([$order['batch_id']]);
        $order['delivery'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Get payment info
    $stmt = $pdo->prepare("
        SELECT p.amount_paid, p.transaction_reference, pm.method_name, ps.status_name AS payment_status
        FROM payments p
        JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
        JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id
        WHERE p.reference_id = ?
    ");
    $stmt->execute([$referenceId]);
    $order['payment'] = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $order,
        'message' => 'Order found'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error tracking order: ' . $e->getMessage()]);
}
