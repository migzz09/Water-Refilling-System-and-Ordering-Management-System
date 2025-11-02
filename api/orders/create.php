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

// Delivery payload may include address/deliveryDate
$delivery = $input['delivery'] ?? [];
$deliveryDate = $delivery['deliveryDate'] ?? null;
$deliveryCity = $delivery['city'] ?? null;

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
    $totalQuantity = 0;
    foreach ($items as $item) {
        $totalAmount += ($item['quantity'] ?? 0) * ($item['price'] ?? 0);
        $totalQuantity += ($item['quantity'] ?? 0);
    }

    // Determine city if not provided in payload: fallback to customer's stored city
    if (empty($deliveryCity)) {
        $stmt = $pdo->prepare("SELECT city FROM customers WHERE customer_id = ? LIMIT 1");
        $stmt->execute([$customerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $deliveryCity = $row['city'] ?? '';
    }

    // Function: assignBatch (ported from references/checkout_reference.php)
    function assignBatch($pdo, $city, $delivery_date, $quantity) {
        $vehicle_type = ($city === 'Taguig') ? 'Tricycle' : 'Car';
        $capacity = ($vehicle_type === 'Tricycle') ? 5 : 10;
        $batch_date = $delivery_date;

        if ($quantity > $capacity) {
            throw new Exception("Order quantity exceeds vehicle capacity for $vehicle_type (Max: $capacity containers). Please split your order into smaller quantities.");
        }

        // Find existing batch with enough space (prioritize lowest batch_number)
        $stmt = $pdo->prepare("\n            SELECT b.batch_id, b.batch_number, COALESCE(SUM(od.quantity), 0) AS total_quantity\n            FROM batches b\n            LEFT JOIN orders o ON b.batch_id = o.batch_id\n            LEFT JOIN order_details od ON o.reference_id = od.reference_id\n            WHERE DATE(b.batch_date) = ? AND b.vehicle_type = ?\n            GROUP BY b.batch_id, b.batch_number\n            HAVING total_quantity + ? <= ?\n            ORDER BY b.batch_number ASC, b.batch_id ASC\n            LIMIT 1\n        ");
        $stmt->execute([$batch_date, $vehicle_type, $quantity, $capacity]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($batch) {
            return [
                'batch_id' => $batch['batch_id'],
                'batch_number' => $batch['batch_number']
            ];
        }

        // No existing batch has space, check if can create new
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE DATE(batch_date) = ? AND vehicle_type = ?");
        $stmt->execute([$batch_date, $vehicle_type]);
        $batch_count = $stmt->fetchColumn();

        if ($batch_count >= 3) {
            throw new Exception("Cannot assign batch: Limit of 3 batches reached for $vehicle_type on $batch_date and all are full. Please choose a different delivery date.");
        }

        // Create new batch with sequential batch_number (1,2,3)
        $new_batch_number = $batch_count + 1;
        $stmt = $pdo->prepare("INSERT INTO batches (vehicle, vehicle_type, batch_number, batch_status_id, notes, batch_date) VALUES (?, ?, ?, 1, 'Auto-created batch', ?)");
        $vehicle_name = $vehicle_type . ' #' . rand(100, 999);
        $stmt->execute([$vehicle_name, $vehicle_type, $new_batch_number, $batch_date]);
        $new_batch_id = $pdo->lastInsertId();

        return [
            'batch_id' => $new_batch_id,
            'batch_number' => $new_batch_number
        ];
    }

    // Assign batch and insert order (include delivery_date and batch_id)
    $batch_id = null;
    $batch_number = 1;
    if ($deliveryDate) {
        $batchInfo = assignBatch($pdo, $deliveryCity, $deliveryDate, $totalQuantity);
        $batch_id = $batchInfo['batch_id'] ?? null;
        $batch_number = $batchInfo['batch_number'] ?? 1;
    }

    // Always create a checkout record for this order (single-item or multi-item)
    $checkout_id = null;
    $cstmt = $pdo->prepare("INSERT INTO checkouts (customer_id, created_at, notes) VALUES (?, NOW(), ?)");
    $cstmt->execute([$customerId, $notes]);
    $checkout_id = $pdo->lastInsertId();

    // Insert order with batch and delivery date
    // include checkout_id if present (orders table may have that column)
    $stmt = $pdo->prepare(
        "INSERT INTO orders (reference_id, customer_id, checkout_id, order_type_id, batch_id, order_date, delivery_date, order_status_id, total_amount) VALUES (?, ?, ?, ?, ?, NOW(), ?, 1, ?)"
    );
    $stmt->execute([$referenceId, $customerId, $checkout_id, $orderType, $batch_id, $deliveryDate, $totalAmount]);

    // Insert order details (include per-item water_type_id and order_type_id when provided)
    $stmt = $pdo->prepare(
        "INSERT INTO order_details (reference_id, batch_number, container_id, water_type_id, order_type_id, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    foreach ($items as $item) {
        $subtotal = ($item['quantity'] ?? 0) * ($item['price'] ?? 0);
        $waterTypeId = isset($item['water_type_id']) ? (int)$item['water_type_id'] : null;
        $itemOrderTypeId = isset($item['order_type_id']) ? (int)$item['order_type_id'] : (int)$orderType;
        $containerId = isset($item['container_id']) ? (int)$item['container_id'] : null;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
        $stmt->execute([$referenceId, $batch_number, $containerId, $waterTypeId, $itemOrderTypeId, $quantity, $subtotal]);
    }
    // For refill orders, create pickup and delivery entries tied to the batch
    $pickup_delivery_id = null;
    $delivery_delivery_id = null;
    if ((int)$orderType === 1) {
        // Try to read pickup_time/delivery_time from batch; fall back to defaults
        $pickupTime = '07:00:00';
        $deliveryTime = '10:00:00';
        if ($batch_id) {
            try {
                $tstmt = $pdo->prepare("SELECT pickup_time, delivery_time FROM batches WHERE batch_id = ? LIMIT 1");
                $tstmt->execute([$batch_id]);
                $trow = $tstmt->fetch(PDO::FETCH_ASSOC);
                if ($trow) {
                    if (!empty($trow['pickup_time'])) $pickupTime = $trow['pickup_time'];
                    if (!empty($trow['delivery_time'])) $deliveryTime = $trow['delivery_time'];
                }
            } catch (Exception $e) {
                // ignore and use defaults
            }
        }

        // build scheduled timestamps (use deliveryDate if provided)
        $scheduledDate = $deliveryDate ?: date('Y-m-d');
        $pickupScheduled = $scheduledDate . ' ' . $pickupTime;
        $deliveryScheduled = $scheduledDate . ' ' . $deliveryTime;

        // Insert pickup delivery record
        $dstmt = $pdo->prepare("INSERT INTO deliveries (batch_id, delivery_status_id, delivery_date, delivery_type, scheduled_time, notes) VALUES (?, 1, ?, 'pickup', ?, ?)");
        $dstmt->execute([$batch_id, $scheduledDate, $pickupScheduled, 'Auto-created pickup for order ' . $referenceId]);
        $pickup_delivery_id = $pdo->lastInsertId();

        // Insert delivery delivery record
        $dstmt = $pdo->prepare("INSERT INTO deliveries (batch_id, delivery_status_id, delivery_date, delivery_type, scheduled_time, notes) VALUES (?, 1, ?, 'delivery', ?, ?)");
        $dstmt->execute([$batch_id, $scheduledDate, $deliveryScheduled, 'Auto-created delivery for order ' . $referenceId]);
        $delivery_delivery_id = $pdo->lastInsertId();
    }

    // Insert payment record
    $stmt = $pdo->prepare("INSERT INTO payments (reference_id, payment_method_id, payment_status_id, amount_paid) VALUES (?, ?, 1, ?)");
    $stmt->execute([$referenceId, $paymentMethod, $totalAmount]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully',
        'data' => [
            'reference_id' => $referenceId,
            'total_amount' => $totalAmount
            , 'batch_id' => $batch_id,
            'batch_number' => $batch_number,
            'checkout_id' => $checkout_id
        ]
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Order creation failed: ' . $e->getMessage()]);
}
