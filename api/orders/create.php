<?php
/**
 * Create Order API Endpoint
 * Method: POST
 * Body: Order data including items, delivery info, etc.
 */
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$customerId = $_SESSION['customer_id'];

// Extract order data
$orderType = (int)($input['order_type'] ?? 1);
$deliveryOption = (int)($input['delivery_option'] ?? 1);
$paymentMethod = (int)($input['payment_method'] ?? 1);
$items = $input['items'] ?? [];
$notes = trim($input['notes'] ?? '');

$errors = [];

if (empty($items)) {
    $errors[] = "At least one item is required.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

try {
    $pdo->beginTransaction();

    // Generate a unique 6-character numeric reference ID to match `orders.reference_id` varchar(6)
    do {
        $referenceId = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE reference_id = ?");
        $check->execute([$referenceId]);
        $exists = $check->fetchColumn();
    } while ($exists);

    // Calculate total
    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += ($item['quantity'] ?? 0) * ($item['price'] ?? 0);
    }

    // Insert order
        // Insert order
        // Insert only columns present in this schema: no 'notes' column
        $stmt = $pdo->prepare(
            "INSERT INTO orders (reference_id, customer_id, order_type_id, order_status_id, order_date, total_amount) VALUES (?, ?, ?, 1, NOW(), ?)"
        );
        $stmt->execute([$referenceId, $customerId, $orderType, $totalAmount]);

    // Insert order details
    $stmt = $pdo->prepare("
        INSERT INTO order_details (reference_id, container_id, quantity, subtotal)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($items as $item) {
        $subtotal = ($item['quantity'] ?? 0) * ($item['price'] ?? 0);
        $stmt->execute([$referenceId, $item['container_id'], $item['quantity'], $subtotal]);
    }

    // Insert payment record
    $stmt = $pdo->prepare("
        INSERT INTO payments (reference_id, payment_method_id, payment_status_id, amount_paid)
        VALUES (?, ?, 1, ?)
    ");
    $stmt->execute([$referenceId, $paymentMethod, $totalAmount]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'data' => [
            'reference_id' => $referenceId,
            'total_amount' => $totalAmount
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Order creation failed: ' . $e->getMessage()]);
}
