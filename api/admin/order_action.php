<?php
// Admin per-order actions: start_pickup, complete_pickup, start_delivery, complete_delivery
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
$referenceId = $input['reference_id'] ?? '';

// Admin authentication check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin access required']);
    exit;
}

if (!$action || !$referenceId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'action and reference_id are required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Verify order exists
    $stmt = $pdo->prepare("SELECT reference_id, order_status_id, batch_id FROM orders WHERE reference_id = ? LIMIT 1");
    $stmt->execute([$referenceId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');

    // Determine whether the DB has a checkout_id column (backwards compatible)
    $hasCheckout = false;
    try {
        $colChk = $pdo->query("SHOW COLUMNS FROM orders LIKE 'checkout_id'")->fetch();
        if ($colChk) $hasCheckout = true;
    } catch (Exception $ignore) {
        // If the DB doesn't allow SHOW COLUMNS, just proceed without checkout grouping
        $hasCheckout = false;
    }

    // Decide target rows: either all orders with the same checkout_id, or the single reference
    $targetRefs = [];

    if ($hasCheckout) {
        $csel = $pdo->prepare("SELECT checkout_id FROM orders WHERE reference_id = ? LIMIT 1");
        $csel->execute([$referenceId]);
        $crow = $csel->fetch(PDO::FETCH_ASSOC);
        $checkoutId = $crow['checkout_id'] ?? null;
        if ($checkoutId) {
            // Update all orders in the same checkout
            // Map action to order_status_id
            switch ($action) {
                case 'start_pickup': $newStatus = 2; break; // Dispatched
                case 'complete_pickup': $newStatus = 2; break;
                case 'start_delivery': $newStatus = 2; break;
                case 'complete_delivery': $newStatus = 3; break; // Delivered
                default: throw new Exception('Unknown action');
            }
            $u = $pdo->prepare("UPDATE orders SET order_status_id = ? WHERE checkout_id = ?");
            $u->execute([$newStatus, $checkoutId]);

            // Return the list of affected reference_ids
            $r = $pdo->prepare("SELECT reference_id, order_status_id FROM orders WHERE checkout_id = ?");
            $r->execute([$checkoutId]);
            $targetRefs = $r->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    // If checkout grouping not used or checkout_id missing, fall back to single-order update
    if (!$targetRefs || count($targetRefs) === 0) {
        switch ($action) {
            case 'start_pickup': $newStatus = 2; break;
            case 'complete_pickup': $newStatus = 2; break;
            case 'start_delivery': $newStatus = 2; break;
            case 'complete_delivery': $newStatus = 3; break;
            default: throw new Exception('Unknown action');
        }
        $pdo->prepare("UPDATE orders SET order_status_id = ? WHERE reference_id = ?")->execute([$newStatus, $referenceId]);
        $sel = $pdo->prepare("SELECT reference_id, order_status_id FROM orders WHERE reference_id = ? LIMIT 1");
        $sel->execute([$referenceId]);
        $one = $sel->fetch(PDO::FETCH_ASSOC);
        $targetRefs = $one ? [$one] : [];
    }

    // Attach status_name via join for each updated row
    $updatedList = [];
    if (count($targetRefs)) {
        $refs = array_map(function($r){ return $r['reference_id']; }, $targetRefs);
        // Prepare an IN clause safely
        $placeholders = implode(',', array_fill(0, count($refs), '?'));
        $q = $pdo->prepare("SELECT o.reference_id, o.order_status_id, os.status_name AS order_status FROM orders o LEFT JOIN order_status os ON o.order_status_id = os.status_id WHERE o.reference_id IN ($placeholders)");
        $q->execute($refs);
        $updatedList = $q->fetchAll(PDO::FETCH_ASSOC);
    }

    $pdo->commit();
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Order action performed', 'data' => ['updated' => $updatedList]]);
} catch (Exception $e) {
    $pdo->rollBack();
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit;
