<?php
// Assign a single order (reference_id) to the next batch number for its vehicle type/date.
ob_start();
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$reference = $input['reference_id'] ?? '';

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!$reference) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'reference_id is required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get order and its current batch
    $stmt = $pdo->prepare("SELECT o.reference_id, o.batch_id, o.delivery_date, od.quantity, b.vehicle_type, b.batch_number FROM orders o LEFT JOIN order_details od ON o.reference_id = od.reference_id LEFT JOIN batches b ON o.batch_id = b.batch_id WHERE o.reference_id = ? LIMIT 1");
    $stmt->execute([$reference]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) throw new Exception('Order not found');

    $orderQty = (int)($order['quantity'] ?? 0);
    $batchDate = $order['delivery_date'] ?? date('Y-m-d');
    $vehicleType = $order['vehicle_type'] ?? null;
    $currentBatchNumber = $order['batch_number'] ?? 0;

    // target batch number is current + 1 (or 1 if none)
    $targetNumber = ($currentBatchNumber > 0) ? $currentBatchNumber + 1 : 1;
    if ($targetNumber > 3) throw new Exception('Cannot assign to next batch: target batch number exceeds limit (3)');

    // Check if a batch exists for this date/vehicle_type with targetNumber
    $bst = $pdo->prepare("SELECT batch_id FROM batches WHERE DATE(batch_date) = ? AND vehicle_type = ? AND batch_number = ? LIMIT 1");
    $bst->execute([$batchDate, $vehicleType, $targetNumber]);
    $targetBatch = $bst->fetch(PDO::FETCH_ASSOC);

    // Determine capacity for vehicle_type
    $capacity = ($vehicleType === 'Tricycle') ? 5 : 10;

    if ($targetBatch) {
        $targetBatchId = $targetBatch['batch_id'];
        // Check total quantity in target batch
        $qstmt = $pdo->prepare("SELECT COALESCE(SUM(od.quantity),0) as total_qty FROM orders o LEFT JOIN order_details od ON o.reference_id = od.reference_id WHERE o.batch_id = ?");
        $qstmt->execute([$targetBatchId]);
        $totalQty = (int)$qstmt->fetchColumn();
        if ($totalQty + $orderQty > $capacity) throw new Exception('Not enough capacity in target batch');
    } else {
        // Need to create a new batch
        // Ensure we don't exceed 3 batches for that date & vehicle
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE DATE(batch_date) = ? AND vehicle_type = ?");
        $countStmt->execute([$batchDate, $vehicleType]);
        $count = (int)$countStmt->fetchColumn();
        if ($count >= 3) throw new Exception('Cannot create new batch: limit of 3 reached');

        // create batch
        $vehicleName = $vehicleType . ' #' . rand(100,999);
        $insert = $pdo->prepare("INSERT INTO batches (vehicle, vehicle_type, batch_number, batch_status_id, notes, batch_date) VALUES (?, ?, ?, 1, 'Created by admin assign', ?)");
        $insert->execute([$vehicleName, $vehicleType, $targetNumber, $batchDate]);
        $targetBatchId = $pdo->lastInsertId();
    }

    // Update the order to the new batch
    $ust = $pdo->prepare("UPDATE orders SET batch_id = ? WHERE reference_id = ?");
    $ust->execute([$targetBatchId, $reference]);

    // Update order_details.batch_number for this reference
    $odst = $pdo->prepare("UPDATE order_details SET batch_number = ? WHERE reference_id = ?");
    $odst->execute([$targetNumber, $reference]);

    // For refill orders, create pickup/delivery deliveries tied to the target batch
    $tstmt = $pdo->prepare("SELECT order_type_id FROM orders WHERE reference_id = ? LIMIT 1");
    $tstmt->execute([$reference]);
    $otype = (int)$tstmt->fetchColumn();
    if ($otype === 1) {
        $scheduledDate = $batchDate;
        $pickupScheduled = $scheduledDate . ' 07:00:00';
        $deliveryScheduled = $scheduledDate . ' 10:00:00';
        $dins = $pdo->prepare("INSERT INTO deliveries (batch_id, delivery_status_id, delivery_date, delivery_type, scheduled_time, notes) VALUES (?, 1, ?, 'pickup', ?, ?)");
        $dins->execute([$targetBatchId, $scheduledDate, $pickupScheduled, 'Pickup (reassigned) for ' . $reference]);
        $dins = $pdo->prepare("INSERT INTO deliveries (batch_id, delivery_status_id, delivery_date, delivery_type, scheduled_time, notes) VALUES (?, 1, ?, 'delivery', ?, ?)");
        $dins->execute([$targetBatchId, $scheduledDate, $deliveryScheduled, 'Delivery (reassigned) for ' . $reference]);
    }

    $pdo->commit();
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Order reassigned to batch', 'data' => ['batch_id' => $targetBatchId, 'batch_number' => $targetNumber]]);
} catch (Exception $e) {
    $pdo->rollBack();
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit;
