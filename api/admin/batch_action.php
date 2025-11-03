<?php
// Admin batch actions: start_pickup, complete_pickup, start_delivery, complete_delivery
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
$action = $input['action'] ?? '';
$batchId = isset($input['batch_id']) ? (int)$input['batch_id'] : 0;

// Admin authentication check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
    exit;
}

if (!$batchId || !$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'batch_id and action are required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Validate batch exists
    $bst = $pdo->prepare("SELECT batch_id FROM batches WHERE batch_id = ? LIMIT 1");
    $bst->execute([$batchId]);
    if (!$bst->fetchColumn()) {
        throw new Exception('Batch not found');
    }

    // Helper: update deliveries by type
    $updateDelivery = function($type, $statusId, $setActual=false) use ($pdo, $batchId) {
        if ($setActual) {
            $stmt = $pdo->prepare("UPDATE deliveries SET delivery_status_id = ?, actual_time = NOW() WHERE batch_id = ? AND delivery_type = ?");
            $stmt->execute([$statusId, $batchId, $type]);
        } else {
            $stmt = $pdo->prepare("UPDATE deliveries SET delivery_status_id = ? WHERE batch_id = ? AND delivery_type = ?");
            $stmt->execute([$statusId, $batchId, $type]);
        }
    };

    // Helper: update orders status for all orders in batch (used when starting/completing delivery)
    $updateOrdersStatus = function($statusId) use ($pdo, $batchId) {
        $stmt = $pdo->prepare("UPDATE orders SET order_status_id = ? WHERE batch_id = ?");
        $stmt->execute([$statusId, $batchId]);
    };

    switch ($action) {
        case 'start_pickup':
            // mark pickup rows as Dispatched (2)
            $updateDelivery('pickup', 2, false);
            // set batch status to Dispatched (2)
            $pdo->prepare("UPDATE batches SET batch_status_id = 2 WHERE batch_id = ?")->execute([$batchId]);
            break;
        case 'complete_pickup':
            // mark pickup as Delivered (3) and set actual_time
            $updateDelivery('pickup', 3, true);
            break;
        case 'start_delivery':
            // mark delivery rows as Dispatched (2)
            $updateDelivery('delivery', 2, false);
            // update orders to Dispatched
            $updateOrdersStatus(2);
            // set batch status to Dispatched
            $pdo->prepare("UPDATE batches SET batch_status_id = 2 WHERE batch_id = ?")->execute([$batchId]);
            break;
        case 'complete_delivery':
            // mark delivery as Delivered (3) and set actual_time
            $updateDelivery('delivery', 3, true);
            // update orders to Delivered
            $updateOrdersStatus(3);
            // set batch status to Completed (3)
            $pdo->prepare("UPDATE batches SET batch_status_id = 3 WHERE batch_id = ?")->execute([$batchId]);
            break;
        default:
            throw new Exception('Unknown action');
    }
    $pdo->commit();

    // After commit, fetch updated batch_status and deliveries to return structured info for the UI
    $bst = $pdo->prepare("SELECT bs.status_name as batch_status FROM batches b LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id WHERE b.batch_id = ? LIMIT 1");
    $bst->execute([$batchId]);
    $batchStatusRow = $bst->fetch(PDO::FETCH_ASSOC);
    $batchStatusName = $batchStatusRow['batch_status'] ?? null;

    $stmt4 = $pdo->prepare("SELECT d.delivery_type, ds.status_name as delivery_status, d.scheduled_time, d.actual_time
        FROM deliveries d
        LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
        WHERE d.batch_id = ?");
    $stmt4->execute([$batchId]);
    $deliveryRows = $stmt4->fetchAll(PDO::FETCH_ASSOC);

    $pickupStatus = null;
    $deliveryStatus = null;
    foreach ($deliveryRows as $dr) {
        if (!empty($dr['delivery_type']) && strtolower($dr['delivery_type']) === 'pickup') {
            $pickupStatus = $dr['delivery_status'] ?? $pickupStatus;
        } elseif (!empty($dr['delivery_type']) && strtolower($dr['delivery_type']) === 'delivery') {
            $deliveryStatus = $dr['delivery_status'] ?? $deliveryStatus;
        }
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Action performed: ' . $action, 'data' => ['batch_id' => $batchId, 'batch_status' => $batchStatusName, 'pickup_status' => $pickupStatus, 'delivery_status' => $deliveryStatus]]);
} catch (Exception $e) {
    $pdo->rollBack();
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit;
