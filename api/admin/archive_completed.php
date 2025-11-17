<?php
// Start output buffering IMMEDIATELY to capture any stray output
ob_start();

// Disable display errors but enable logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Function to send JSON and clean buffer (define FIRST before anything can fail)
function sendJson($data, $statusCode = 200) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_PRETTY_PRINT);
    ob_end_flush();
    exit;
}

// Set error handler to catch all errors and send JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    sendJson([
        'success' => false,
        'message' => 'PHP Error occurred',
        'error' => $errstr,
        'debug' => [
            'file' => basename($errfile),
            'line' => $errline,
            'errno' => $errno
        ]
    ], 500);
});

// Set exception handler
set_exception_handler(function($e) {
    sendJson([
        'success' => false,
        'message' => 'Exception occurred',
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ]
    ], 500);
});

try {
    session_start();
    require_once '../../config/connect.php';
    
    // Check if user is authenticated and is admin (same as manage_orders.php)
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        sendJson(['success' => false, 'message' => 'Unauthorized access. Admin privileges required.'], 403);
    }
    
    // For admins, get account_id from database using username
    $userId = null;
    if (isset($_SESSION['username'])) {
        $stmt = $pdo->prepare("SELECT account_id FROM accounts WHERE username = ? LIMIT 1");
        $stmt->execute([$_SESSION['username']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $userId = $result['account_id'];
        }
    }
    
    if (!$userId) {
        sendJson([
            'success' => false, 
            'message' => 'Could not determine admin user ID',
            'debug' => [
                'username' => $_SESSION['username'] ?? 'not set',
                'session_keys' => array_keys($_SESSION)
            ]
        ], 403);
    }
    
    $pdo->beginTransaction();
    
    $archiveType = $_POST['archive_type'] ?? 'manual';
    $dateFilter = $_POST['date_filter'] ?? date('Y-m-d');
    
} catch (Exception $e) {
    sendJson([
        'success' => false,
        'message' => 'Initialization failed',
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ], 500);
}

try {
    
    // Get all completed orders with full details
    // Note: deliveries are linked to batches, not individual orders
    // First, let's see ALL orders for the date regardless of completion status
    $debugStmt = $pdo->prepare("
        SELECT 
            o.reference_id,
            o.order_date,
            o.delivery_date,
            o.batch_id,
            b.batch_date,
            b.batch_status_id,
            bs.status_name as batch_status,
            d.delivery_status_id,
            ds.status_name as delivery_status
        FROM orders o
        LEFT JOIN batches b ON o.batch_id = b.batch_id
        LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
        LEFT JOIN deliveries d ON b.batch_id = d.batch_id
        LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
        WHERE DATE(o.order_date) = ?
    ");
    $debugStmt->execute([$dateFilter]);
    $allOrdersOnDate = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log for debugging
    error_log("Archive: Looking for date $dateFilter");
    error_log("Archive: Found " . count($allOrdersOnDate) . " total orders on date");
    error_log("Archive: Orders detail: " . json_encode($allOrdersOnDate));
    
    $stmt = $pdo->prepare("
        SELECT 
            o.reference_id,
            o.customer_id as user_id,
            o.order_date,
            o.delivery_date,
            o.total_amount,
            o.batch_id,
            CONCAT(c.first_name, ' ', c.last_name) as customer_name,
            c.customer_contact,
            CONCAT_WS(', ', c.street, c.barangay, c.city, c.province) as delivery_address,
            os.status_name as order_status,
            ds.status_name as delivery_status,
            b.batch_number,
            b.vehicle_type,
            d.actual_time as delivery_time,
            d.scheduled_time as pickup_time,
            pm.method_name as payment_method,
            d.delivery_status_id,
            b.batch_status_id
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN payments p ON o.reference_id = p.reference_id
        LEFT JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
        LEFT JOIN order_status os ON o.order_status_id = os.status_id
        LEFT JOIN batches b ON o.batch_id = b.batch_id
        LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
        LEFT JOIN deliveries d ON b.batch_id = d.batch_id
        LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
        WHERE (
            d.delivery_status_id = 3
            OR b.batch_status_id = 3
            OR LOWER(ds.status_name) LIKE '%completed%'
            OR LOWER(bs.status_name) LIKE '%completed%'
        )
        AND DATE(o.order_date) = ?
    ");
        $stmt->execute([$dateFilter]);
    $completedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Archive: Found " . count($completedOrders) . " completed orders");
    error_log("Archive: Completed orders: " . json_encode($completedOrders));
    
    // If no orders to archive, return success with count 0 and diagnostics to help debugging
    if (empty($completedOrders)) {
        // Gather diagnostics: orders scheduled for the date, their batch ids, batch statuses, and delivery statuses
        $diag = [];
        $diag['all_orders_on_date'] = $allOrdersOnDate;
        $diag['orders_on_date_count'] = count($allOrdersOnDate);
        $diag['orders_on_date_sample'] = array_slice(array_map(function($o){ return $o['reference_id']; }, $allOrdersOnDate), 0, 10);

        // batch statuses
        $batchIds = array_values(array_unique(array_filter(array_map(function($o){ return $o['batch_id'] ?? null; }, $allOrdersOnDate))));
        $diag['batch_ids'] = $batchIds;
        $diag['batches'] = [];
        $diag['deliveries'] = [];
        if (count($batchIds)) {
            $place = implode(',', array_fill(0, count($batchIds), '?'));
            $qb = $pdo->prepare("SELECT b.batch_id, b.batch_number, b.batch_status_id, bs.status_name AS batch_status FROM batches b LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id WHERE b.batch_id IN ($place)");
            $qb->execute($batchIds);
            $diag['batches'] = $qb->fetchAll(PDO::FETCH_ASSOC);

            // deliveries for those batches
            $qd = $pdo->prepare("SELECT d.batch_id, d.delivery_id, d.delivery_status_id, ds.status_name AS delivery_status FROM deliveries d LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id WHERE d.batch_id IN ($place)");
            $qd->execute($batchIds);
            $diag['deliveries'] = $qd->fetchAll(PDO::FETCH_ASSOC);
        }

        $pdo->commit();
        sendJson([
            'success' => false,
            'message' => 'No completed orders found for ' . $dateFilter,
            'data' => [
                'orders_archived' => 0,
                'date' => $dateFilter,
                'diagnostics' => $diag
            ]
        ]);
    }
    
    $archivedCount = 0;
    
    foreach ($completedOrders as $order) {
        // Get order items
        $stmt = $pdo->prepare("
            SELECT 
                od.quantity,
                od.subtotal,
                ct.container_type,
                wt.type_name as water_type_name,
                ot.type_name as order_type_name
            FROM order_details od
            LEFT JOIN containers ct ON od.container_id = ct.container_id
            LEFT JOIN water_types wt ON od.water_type_id = wt.water_type_id
            LEFT JOIN order_types ot ON od.order_type_id = ot.order_type_id
            WHERE od.reference_id = ?
        ");
        $stmt->execute([$order['reference_id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build complete order snapshot as JSON
        $orderData = [
            'reference_id' => $order['reference_id'],
            'order_date' => $order['order_date'],
            'payment_method' => $order['payment_method'] ?? 'COD',
            'order_status' => $order['order_status'],
            'delivery_status' => $order['delivery_status'],
            'customer' => [
                'name' => $order['customer_name'],
                'contact' => $order['customer_contact']
            ],
            'delivery' => [
                'address' => $order['delivery_address'],
                'pickup_time' => $order['pickup_time'],
                'delivery_time' => $order['delivery_time']
            ],
            'batch' => [
                'batch_id' => $order['batch_id'],
                'batch_number' => $order['batch_number'],
                'vehicle_type' => $order['vehicle_type']
            ],
            'items' => $items
        ];
        
        // Check if already archived (avoid duplicates)
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM archived_orders WHERE reference_id = ?");
        $checkStmt->execute([$order['reference_id']]);
        if ($checkStmt->fetchColumn() > 0) {
            // Already archived, skip
            continue;
        }
        
        // Insert simplified archive with JSON data
        // NOTE: We only INSERT into archived_orders, we DO NOT DELETE from orders table
        // The orders table is the source of truth for user transaction history, dashboard stats, and reports
        // Archive is for admin UI decluttering only
        $stmt = $pdo->prepare("
            INSERT INTO archived_orders 
            (reference_id, user_id, delivery_date, total_amount, order_data, archived_at, archived_by)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $order['reference_id'],
            $order['user_id'],
            $order['delivery_date'],
            $order['total_amount'],
            json_encode($orderData),
            $userId
        ]);
        
        error_log("Archive: Successfully inserted order " . $order['reference_id'] . " into archived_orders");
        $archivedCount++;
    }
    
    // DO NOT delete orders, order_details, batches, or deliveries
    // Archive is for admin record-keeping and UI filtering only
    // All original data must remain intact for user transaction history, dashboard, and reports
    
    // Log the archive operation
    $stmt = $pdo->prepare("
        INSERT INTO archive_log 
        (archive_type, orders_archived, archived_by, date_filter, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $archiveType,
        $archivedCount,
        $userId,
        $dateFilter,
        "Archived $archivedCount completed orders for $dateFilter"
    ]);
    
    error_log("Archive: About to commit transaction with $archivedCount orders archived");
    $pdo->commit();
    error_log("Archive: Transaction committed successfully");
    
    sendJson([
        'success' => true,
        'message' => "Successfully archived $archivedCount completed orders",
        'data' => [
            'orders_archived' => $archivedCount,
            'date' => $dateFilter
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        try {
            $pdo->rollBack();
        } catch (Exception $rollbackEx) {
            // Ignore rollback errors
        }
    }
    
    $errorMsg = $e->getMessage();
    $errorFile = basename($e->getFile());
    $errorLine = $e->getLine();
    
    error_log("Archive error: $errorMsg in $errorFile:$errorLine");
    error_log("Stack trace: " . $e->getTraceAsString());
    
    sendJson([
        'success' => false,
        'message' => 'Archive failed: ' . $errorMsg,
        'error' => $errorMsg,
        'debug' => [
            'file' => $errorFile,
            'line' => $errorLine,
            'trace' => explode("\n", $e->getTraceAsString())
        ]
    ], 500);
}
?>
