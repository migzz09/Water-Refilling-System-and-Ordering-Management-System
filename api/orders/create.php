<?php
/**
 * Create Order API Endpoint
 * Method: POST
 * Body: Order data including items, delivery info, etc.
 */

// Suppress any PHP errors/warnings from being output as HTML
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Manila');
if (session_status() === PHP_SESSION_NONE) session_start();

// Add direct execution flag (only run request handling if this file is the entry point)
$__ORDERS_CREATE_DIRECT = (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));

// Move JSON header inside the direct execution branch (avoid overwriting HTML header when included)
if ($__ORDERS_CREATE_DIRECT) {
    // Set JSON header immediately to prevent any HTML output
    header('Content-Type: application/json');
}

// Replace previous DB include block with a robust bootstrap that REUSES existing connection
try {
    // CRITICAL: Check if PDO is already established by the parent script (payment_success.php)
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        $pdo = $GLOBALS['pdo'];
        error_log('create.php: Reusing existing PDO connection from $GLOBALS[pdo]');
    } else {
        // No existing connection; bootstrap from scratch
        $pdo = null;

        // Try the legacy connect.php first
        $connectPath = __DIR__ . '/../../config/connect.php';
        if (file_exists($connectPath)) {
            require_once $connectPath; // should define $pdo
            // Check global scope
            if (!isset($pdo) || $pdo === null) {
                $pdo = $GLOBALS['pdo'] ?? null;
            }
        }

        // If still no $pdo, try database.php variants
        if (!$pdo) {
            $dbPath1 = __DIR__ . '/../config/database.php';
            $dbPath2 = __DIR__ . '/../../config/database.php';
            if (file_exists($dbPath1)) require_once $dbPath1;
            elseif (file_exists($dbPath2)) require_once $dbPath2;

            if (class_exists('Database') && !$pdo) {
                $database = new Database();
                $pdo = $database->getConnection();
            }
        }

        if (!$pdo) {
            throw new Exception('DB connection not initialized in create.php');
        }
    }
} catch (Exception $e) {
    error_log('create.php: DB bootstrap failed: ' . $e->getMessage());
    if ($__ORDERS_CREATE_DIRECT) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    }
    return; // stop further processing when included
}

// Wrap all order logic in a function so PayMongo payment_success.php can call it
function processOrder($input, $customerId, $pdo) {
    if (!$customerId) {
        return ['success' => false, 'message' => 'Missing customer ID for order creation'];
    }

    // CRITICAL FIX: Ensure $pdo parameter is valid before using it
    if (!$pdo || !($pdo instanceof PDO)) {
        return ['success' => false, 'message' => 'Invalid database connection passed to processOrder'];
    }

    // Get the account_id for notifications
    $accountId = null;
    try {
        $accStmt = $pdo->prepare("SELECT account_id FROM accounts WHERE customer_id = ? LIMIT 1");
        $accStmt->execute([$customerId]);
        $accRow = $accStmt->fetch(PDO::FETCH_ASSOC);
        $accountId = $accRow['account_id'] ?? null;
    } catch (Exception $e) {
        // Continue without account_id - notification won't be created
    }

    // Extract order data
    $orderType = (int)($input['order_type'] ?? 1);
    $deliveryOption = (int)($input['delivery_option'] ?? 1);
    $paymentMethod = (int)($input['payment_method'] ?? 1);
    $items = $input['items'] ?? [];
    // Skip flags for paid external sessions
    $skipChecks = !empty($input['skip_business_time_checks']);

    $notes = trim($input['notes'] ?? '');

    // Delivery payload may include address/deliveryDate
    $delivery = $input['delivery'] ?? [];
    $deliveryDate = $delivery['deliveryDate'] ?? null;
    $deliveryCity = $delivery['city'] ?? null;

    $errors = [];

    if (empty($items)) {
        $errors[] = "At least one item is required.";
    }

    // Check business hours and cutoff time (only if not skipped)
    if (!$skipChecks) {
        // Check if business is open today
        $currentTime = date('H:i:s');
        $currentDay = date('l'); // Monday, Tuesday, etc.

        // Get today's business hours
        $stmt = $pdo->prepare("SELECT is_open, open_time, close_time FROM business_hours WHERE day_of_week = ? LIMIT 1");
        $stmt->execute([$currentDay]);
        $todayHours = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if business is open today
        if (!$todayHours || !$todayHours['is_open']) {
            $errors[] = "We're closed today. Orders cannot be placed.";
        }

        // Check if within business hours
        if ($todayHours && $todayHours['is_open']) {
            $currentTimeObj = strtotime($currentTime);
            $openTimeObj = strtotime($todayHours['open_time']);
            $closeTimeObj = strtotime($todayHours['close_time']);
            
            if ($currentTimeObj < $openTimeObj) {
                $errors[] = "We haven't opened yet. Please place your order after " . date('g:i A', $openTimeObj) . ".";
            } elseif ($currentTimeObj > $closeTimeObj) {
                $errors[] = "We're closed for today. We only accept same-day delivery orders during business hours.";
            }
        }

        // Check cutoff time
        $stmt = $pdo->prepare("SELECT is_enabled, cutoff_time FROM cutoff_time_setting LIMIT 1");
        $stmt->execute();
        $cutoffSetting = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cutoffSetting && $cutoffSetting['is_enabled']) {
            $cutoffTimeObj = strtotime($cutoffSetting['cutoff_time']);
            $currentTimeObj = strtotime($currentTime);
            if ($currentTimeObj > $cutoffTimeObj) {
                $cutoffTimeFormatted = date('g:i A', $cutoffTimeObj);
                $errors[] = "Sorry, today's order cutoff time ($cutoffTimeFormatted) has passed. We only accept same-day delivery orders.";
            }
        }
    }

    if (!empty($errors)) {
        return [
            'success' => false,
            'errors' => $errors
        ];
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
            $qty = (int)($item['quantity'] ?? 0);
            $raw = (float)($item['price'] ?? 0);
            $nm = strtolower(trim($item['name'] ?? ''));
            $otId = isset($item['order_type_id']) ? (int)$item['order_type_id'] : (int)($orderType ?? 1);
            $isSmallSlim = (preg_match('/^small slim container\b/i', $nm) === 1);
            $isBigMin250 = (preg_match('/^(slim container|round container)\b/i', $nm) === 1);
            if ($otId === 2 && $isSmallSlim && $raw < 100) $raw = 100.0;
            $requires250 = ($otId === 2 && $isBigMin250);
            $unit = $requires250 ? max($raw, 250) : $raw;
            $totalAmount += $qty * $unit;
            $totalQuantity += $qty;
        }

        // Determine city if not provided in payload: fallback to customer's stored city
        if (empty($deliveryCity)) {
            $stmt = $pdo->prepare("SELECT city FROM customers WHERE customer_id = ? LIMIT 1");
            $stmt->execute([$customerId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $deliveryCity = $row['city'] ?? '';
        }

        // NOTE: avoid redeclare errors if processOrder might be called multiple times in one request
        if (!function_exists('assignBatch')) {
            // Function: assignBatch (ported from references/checkout_reference.php)
            function assignBatch($pdo, $city, $delivery_date, $quantity) {
                $vehicle_type = ($city === 'Taguig') ? 'Tricycle' : 'Car';
                $capacity = ($vehicle_type === 'Tricycle') ? 5 : 10;
                $batch_date = $delivery_date;

                if ($quantity > $capacity) {
                    throw new Exception("Order quantity exceeds vehicle capacity for $vehicle_type (Max: $capacity containers). Please split your order into smaller quantities.");
                }

                // Find existing batch with enough space (prioritize lowest batch_number)
                $stmt = $pdo->prepare("
                SELECT b.batch_id, b.batch_number, COALESCE(SUM(od.quantity), 0) AS total_quantity
                FROM batches b
                LEFT JOIN orders o ON b.batch_id = o.batch_id
                LEFT JOIN order_details od ON o.reference_id = od.reference_id
                WHERE DATE(b.batch_date) = ? AND b.vehicle_type = ? AND b.batch_status_id = 1
                GROUP BY b.batch_id, b.batch_number
                HAVING total_quantity + ? <= ?
                ORDER BY b.batch_number ASC, b.batch_id ASC
                LIMIT 1
            ");
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
        $stmt = $pdo->prepare(
            "INSERT INTO orders (reference_id, customer_id, checkout_id, order_type_id, batch_id, order_date, delivery_date, order_status_id, total_amount) VALUES (?, ?, ?, ?, ?, NOW(), ?, 1, ?)"
        );
        $stmt->execute([$referenceId, $customerId, $checkout_id, $orderType, $batch_id, $deliveryDate, $totalAmount]);

        // Insert order details (include per-item water_type_id and order_type_id when provided)
        $stmt = $pdo->prepare(
            "INSERT INTO order_details (reference_id, batch_number, container_id, water_type_id, order_type_id, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($items as $item) {
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 0;
            $rawPrice = (float)($item['price'] ?? 0);
            $nameL = strtolower(trim($item['name'] ?? ''));
            $waterTypeId = isset($item['water_type_id']) ? (int)$item['water_type_id'] : null;
            $itemOrderTypeId = isset($item['order_type_id']) ? (int)$item['order_type_id'] : (int)$orderType;
            $containerId = isset($item['container_id']) ? (int)$item['container_id'] : null;

            $isSmallSlim = (preg_match('/^small slim container\b/i', $nameL) === 1);
            $isBigMin250 = (preg_match('/^(slim container|round container)\b/i', $nameL) === 1);
            if ($itemOrderTypeId === 2 && $isSmallSlim && $rawPrice < 100) $rawPrice = 100.0;
            $needsMin = ($itemOrderTypeId === 2 && $isBigMin250);
            $unitPrice = $needsMin ? max($rawPrice, 250) : $rawPrice;

            $subtotal = $quantity * $unitPrice;
            $stmt->execute([$referenceId, $batch_number, $containerId, $waterTypeId, $itemOrderTypeId, $quantity, $subtotal]);

            // Decrease inventory only for 'Purchase Container/s' order type (assume id 2)
            if ($itemOrderTypeId === 2 && $containerId) {
                $invStmt = $pdo->prepare("UPDATE inventory SET stock = stock - ? WHERE container_id = ?");
                $invStmt->execute([$quantity, $containerId]);
            }
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

        // Insert payment record (status 2 = paid when GCash already confirmed)
        $paymentStatusId = ($paymentMethod === 2 && $skipChecks) ? 2 : 1;
        $stmt = $pdo->prepare("INSERT INTO payments (reference_id, payment_method_id, payment_status_id, amount_paid) VALUES (?, ?, ?, ?)");
        $stmt->execute([$referenceId, $paymentMethod, $paymentStatusId, $totalAmount]);

        // Create notification for successful order placement (only if account_id is found)
        if ($accountId) {
            $notifMessage = "Your order #$referenceId has been placed successfully! Total: ₱" . number_format($totalAmount, 2);
            $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, reference_id, notification_type, is_read, created_at) VALUES (?, ?, ?, 'order_placed', 0, NOW())");
            $notifStmt->execute([$accountId, $notifMessage, $referenceId]);
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Order placed successfully',
            'data' => [
                'reference_id' => $referenceId,
                'total_amount' => $totalAmount,
                'batch_id' => $batch_id,
                'batch_number' => $batch_number,
                'checkout_id' => $checkout_id,
                'payment_method' => $paymentMethod,
                'payment_status_id' => $paymentStatusId
            ]
        ];
    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'message' => 'Order creation failed: ' . $e->getMessage()
        ];
    }
}

// Replace unconditional request handling with conditional direct execution
if ($__ORDERS_CREATE_DIRECT) {
    // If called via API POST, run processOrder and echo JSON
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Check authentication
        if (!isset($_SESSION['customer_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
            exit;
        }
        
        $customerId = $_SESSION['customer_id'];
        
        try {
            $result = processOrder($input, $customerId, $pdo);
            if (!empty($result['success'])) {
                echo json_encode($result);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
        }
        exit;
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
}
// When included: no automatic output; processOrder is available to caller.
?>
