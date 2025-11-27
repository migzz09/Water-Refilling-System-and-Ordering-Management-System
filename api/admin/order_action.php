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

// Admin authentication check -- allow staff drivers to perform delivery-only actions
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$isAuthorized = false;
if ($isAdmin) {
    $isAuthorized = true;
} else {
    // try detect staff role by username
    $username = $_SESSION['username'] ?? null;
    if ($username) {
        $sstmt = $pdo->prepare('SELECT staff_role FROM staff WHERE staff_user = ? LIMIT 1');
        $sstmt->execute([$username]);
        $srow = $sstmt->fetch(PDO::FETCH_ASSOC);
        if ($srow && !empty($srow['staff_role'])) {
            $staffRole = strtolower(trim($srow['staff_role']));
            if (strpos($staffRole, 'rider') !== false) $staffRole = 'driver';
            // allow drivers/riders to perform all actions (pickup and delivery)
            if ($staffRole === 'driver' && in_array($action, ['start_pickup', 'complete_pickup', 'start_delivery', 'complete_delivery'])) {
                $isAuthorized = true;
            }
        }
    }
}

if (!$isAuthorized) {
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
    // ensure PDO throws exceptions
    if ($pdo->getAttribute(PDO::ATTR_ERRMODE) !== PDO::ERRMODE_EXCEPTION) {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    $pdo->beginTransaction();

    // Verify order exists
    $stmt = $pdo->prepare("SELECT reference_id, order_status_id, batch_id, customer_id FROM orders WHERE reference_id = ? LIMIT 1");
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
                case 'start_pickup': $newStatus = 2; break; // In Progress
                case 'complete_pickup': $newStatus = 2; break;
                case 'start_delivery': $newStatus = 2; break;
                case 'complete_delivery': $newStatus = 3; break; // Completed
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

    // Attach status_name and account mapping for each updated row
    $updatedList = [];
    $notifications_created = 0;
    $notification_errors = [];

    if (count($targetRefs)) {
        $refs = array_map(function($r){ return $r['reference_id']; }, $targetRefs);
        // Prepare an IN clause safely
        $placeholders = implode(',', array_fill(0, count($refs), '?'));
        $q = $pdo->prepare("
            SELECT 
                o.reference_id, 
                o.order_status_id, 
                os.status_name AS order_status,
                o.customer_id,
                a.account_id
            FROM orders o
            LEFT JOIN order_status os ON o.order_status_id = os.status_id
            LEFT JOIN customers c ON o.customer_id = c.customer_id
            LEFT JOIN accounts a ON a.customer_id = c.customer_id
            WHERE o.reference_id IN ($placeholders)
        ");
        $q->execute($refs);
        $updatedList = $q->fetchAll(PDO::FETCH_ASSOC);

        // Insert notifications for each updated order (for linked accounts)
        if (!empty($updatedList)) {
            $ins = $pdo->prepare("INSERT INTO notifications (user_id, message, reference_id, notification_type, is_read, created_at) VALUES (:user_id, :message, :reference_id, :type, 0, NOW())");
            foreach ($updatedList as $row) {
                $acct = isset($row['account_id']) ? (int)$row['account_id'] : null;
                if ($acct && $acct > 0) {
                    $statusName = $row['order_status'] ?? '';
                    $msg = "Your order #{$row['reference_id']} has been {$statusName}";
                    try {
                        $ins->execute([
                            ':user_id' => $acct,
                            ':message' => $msg,
                            ':reference_id' => $row['reference_id'],
                            ':type' => 'order_status'
                        ]);
                        $notifications_created++;
                    } catch (Exception $e) {
                        $notification_errors[] = "ref {$row['reference_id']}: " . $e->getMessage();
                        error_log("Notification insert failed for ref {$row['reference_id']}: " . $e->getMessage());
                        // continue inserting others
                    }
                } else {
                    // No linked account found; note for debugging
                    $notification_errors[] = "ref {$row['reference_id']}: no linked account (account_id=null)";
                }
            }
        }
    }

    $pdo->commit();
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Order action performed',
        'data' => ['updated' => $updatedList],
        'notifications_created' => $notifications_created,
        'notification_errors' => $notification_errors
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

exit;
?>
