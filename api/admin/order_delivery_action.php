<?php
// filepath: c:\xampp\htdocs\wrsoms\api\admin\order_delivery_action.php
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
$amount = $input['amount'] ?? null;
$reason = $input['reason'] ?? null;

// Authentication check
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$isAuthorized = false;
$staffName = 'Unknown';

if ($isAdmin) {
    $isAuthorized = true;
    $username = $_SESSION['username'] ?? null;
    if ($username) {
        try {
            $stmt = $pdo->prepare('SELECT first_name, last_name FROM staff WHERE staff_user = ? LIMIT 1');
            $stmt->execute([$username]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($staff) {
                $staffName = trim(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''));
            } else {
                $staffName = $username;
            }
        } catch (Exception $e) {
            $staffName = $username;
        }
    }
} else {
    $username = $_SESSION['username'] ?? null;
    if ($username) {
        try {
            $stmt = $pdo->prepare('SELECT staff_id, first_name, last_name, staff_role FROM staff WHERE staff_user = ? LIMIT 1');
            $stmt->execute([$username]);
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($staff) {
                $staffRole = strtolower(trim($staff['staff_role']));
                if (strpos($staffRole, 'rider') !== false || strpos($staffRole, 'driver') !== false) {
                    $isAuthorized = true;
                    $staffName = trim(($staff['first_name'] ?? '') . ' ' . ($staff['last_name'] ?? ''));
                }
            }
        } catch (Exception $e) {
            error_log("Staff check failed: " . $e->getMessage());
        }
    }
}

if (!$isAuthorized) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!$action || !$referenceId) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'action and reference_id are required']);
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // Get order details
    $stmt = $pdo->prepare("SELECT reference_id, order_status_id, total_amount FROM orders WHERE reference_id = ? LIMIT 1");
    $stmt->execute([$referenceId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found');

    // Get payment method
    $paymentStmt = $pdo->prepare("SELECT pm.method_name FROM payments p 
                                   LEFT JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id 
                                   WHERE p.reference_id = ? LIMIT 1");
    $paymentStmt->execute([$referenceId]);
    $paymentData = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    $paymentMethod = strtoupper($paymentData['method_name'] ?? '');

    // CRITICAL FIX: Per-order actions only affect THIS specific order
    // Do NOT update checkout_id orders or batch-level delivery rows
    $targetRefs = [$order];

    switch ($action) {
        case 'complete':
            // COD validation: require exact amount match
            if ($paymentMethod === 'COD') {
                if ($amount === null) {
                    throw new Exception('Amount is required for COD orders');
                }
                $expectedAmount = (float)$order['total_amount'];
                $receivedAmount = (float)$amount;
                if (abs($expectedAmount - $receivedAmount) > 0.01) {
                    throw new Exception('Amount must match order total exactly: ₱' . number_format($expectedAmount, 2));
                }
                // Mark payment as paid (status_id = 2) - ONLY for this order
                $updatePaymentStmt = $pdo->prepare("UPDATE payments SET payment_status_id = 2, payment_date = NOW() WHERE reference_id = ?");
                $updatePaymentStmt->execute([$referenceId]);
            }
            
            // Update ONLY this specific order to Completed (status_id = 3)
            $updateStmt = $pdo->prepare("UPDATE orders 
                SET order_status_id = 3, 
                    delivery_personnel_name = ?, 
                    delivery_completed_at = NOW() 
                WHERE reference_id = ?");
            $updateStmt->execute([$staffName, $referenceId]);
            
            // DO NOT update batch-level delivery rows - batch workflow remains separate
            break;

        case 'fail':
            if (!$reason || trim($reason) === '') {
                throw new Exception('Reason is required for failed deliveries');
            }
            
            // Update ONLY this specific order to Failed (status_id = 4)
            $updateStmt = $pdo->prepare("UPDATE orders 
                SET order_status_id = 4, 
                    delivery_personnel_name = ?, 
                    failed_reason = ?, 
                    delivery_failed_at = NOW() 
                WHERE reference_id = ?");
            $updateStmt->execute([$staffName, $reason, $referenceId]);
            
            // DO NOT update batch-level delivery rows - batch workflow remains separate
            break;
            
        default:
            throw new Exception('Unknown action: ' . $action);
    }

    // Create notification for this specific order only
    $notifications_created = 0;
    $notification_errors = [];
    
    // Get account_id linked to this order
    $accStmt = $pdo->prepare("
        SELECT a.account_id 
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN accounts a ON a.customer_id = c.customer_id
        WHERE o.reference_id = ?
        LIMIT 1
    ");
    $accStmt->execute([$referenceId]);
    $accRow = $accStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($accRow && $accRow['account_id']) {
        $statusMsg = $action === 'complete' ? 'delivered' : 'failed';
        $msg = "Your order #{$referenceId} has been {$statusMsg}";
        
        try {
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, reference_id, notification_type, is_read, created_at) VALUES (?, ?, ?, 'order_status', 0, NOW())");
            $notifStmt->execute([$accRow['account_id'], $msg, $referenceId]);
            $notifications_created++;
        } catch (Exception $e) {
            $notification_errors[] = "ref {$referenceId}: " . $e->getMessage();
            error_log("Notification insert failed for ref {$referenceId}: " . $e->getMessage());
        }
    } else {
        $notification_errors[] = "ref {$referenceId}: no linked account";
    }

    $pdo->commit();
    ob_end_clean();
    
    // Build response data
    $responseData = [
        'updated' => 1,
        'delivery_personnel' => $staffName,
        'action' => $action,
        'reference_id' => $referenceId
    ];
    
    // If COD and completed, include payment status
    if ($action === 'complete' && $paymentMethod === 'COD') {
        $responseData['payment_status'] = 'Paid';
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Per-order delivery action completed successfully',
        'data' => $responseData,
        'notifications_created' => $notifications_created,
        'notification_errors' => $notification_errors
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_end_clean();
    http_response_code(500);
    error_log("Order delivery action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

exit;
?>