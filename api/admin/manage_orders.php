<?php
// Returns batches for today and orders grouped by batch (clean single implementation)
ob_start();
session_start();
// Include DB connection safely to avoid fatal errors that would break JSON responses
$connPath = __DIR__ . '/../../config/connect.php';
if (!file_exists($connPath)) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server configuration error: missing connect.php']);
    exit;
}
include_once $connPath;
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Server configuration error: database connection not available']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('Asia/Manila');
$today = date('Y-m-d');

// Configurable time window (days) via query parameter ?days=N
$days = 2; // default: today + tomorrow
if (isset($_GET['days'])) {
    $d = (int)$_GET['days'];
    if ($d >= 1 && $d <= 30) $days = $d;
}

$endDate = date('Y-m-d', strtotime("$today +" . ($days - 1) . " days"));

try {
    // Determine batches to show for the configured window:
    // - batches scheduled between today and endDate
    // - batches referenced by orders placed between today and endDate
    // - batches referenced by orders with delivery_date between today and endDate
    $stmt = $pdo->prepare("SELECT b.batch_id, b.batch_number, b.vehicle_type, b.vehicle, b.pickup_time, b.delivery_time, b.batch_status_id, bs.status_name as batch_status
        FROM batches b
        LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
        WHERE (DATE(b.batch_date) BETWEEN ? AND ?)
           OR b.batch_id IN (
               SELECT DISTINCT batch_id FROM orders WHERE batch_id IS NOT NULL AND (DATE(order_date) BETWEEN ? AND ? OR (delivery_date IS NOT NULL AND DATE(delivery_date) BETWEEN ? AND ?))
           )
        ORDER BY b.batch_number ASC, b.vehicle_type ASC");
    $stmt->execute([$today, $endDate, $today, $endDate, $today, $endDate]);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($batches as $b) {
        $batchId = $b['batch_id'];

        // Fetch orders for this batch using the admin_management_view for rich fields
        $stmt2 = $pdo->prepare("SELECT amv.reference_id, amv.order_date, amv.delivery_date, amv.total_amount, amv.order_status, amv.payment_status, amv.delivery_status, amv.customer_name, amv.customer_contact, amv.street, amv.barangay, amv.city, amv.province, amv.quantity, amv.container_type, amv.subtotal, amv.assigned_employees
            FROM admin_management_view amv
            WHERE amv.batch_id = ?
            ORDER BY amv.order_date DESC");
        $stmt2->execute([$batchId]);
        $ordersRaw = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        // Deduplicate orders by reference_id (admin_management_view may return multiple rows per order due to joins)
        $orders = [];
        $seen = [];
        $refs = [];
        foreach ($ordersRaw as $or) {
            $ref = $or['reference_id'] ?? null;
            if (!$ref) continue;
            if (isset($seen[$ref])) continue; // keep first occurrence
            $seen[$ref] = true;
            $orders[] = $or;
            $refs[] = $ref;
        }

        // Fetch order items/details for the orders in this batch and attach to each order
        $itemsMap = [];
        if (count($refs)) {
            $place = implode(',', array_fill(0, count($refs), '?'));
            $q = $pdo->prepare("SELECT od.reference_id, od.container_id, od.quantity, od.subtotal, cont.container_type, cont.price
                FROM order_details od
                LEFT JOIN containers cont ON od.container_id = cont.container_id
                WHERE od.reference_id IN ($place)");
            $q->execute($refs);
            $rows = $q->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $rid = $r['reference_id'];
                if (!isset($itemsMap[$rid])) $itemsMap[$rid] = [];
                $itemsMap[$rid][] = $r;
            }
        }
        // attach
        foreach ($orders as &$o) {
            $rid = $o['reference_id'];
            $o['items'] = $itemsMap[$rid] ?? [];
        }
        unset($o);

        // Fetch payment method for each order (use latest payment if multiple)
        if (count($refs)) {
            $place = implode(',', array_fill(0, count($refs), '?'));
            $qp = $pdo->prepare("SELECT p.reference_id, pm.method_name as payment_method, p.payment_date
                FROM payments p
                LEFT JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
                WHERE p.reference_id IN ($place)
                ORDER BY p.payment_date DESC");
            $qp->execute($refs);
            $payRows = $qp->fetchAll(PDO::FETCH_ASSOC);
            $seenPay = [];
            foreach ($payRows as $pr) {
                $r = $pr['reference_id'];
                if (!isset($seenPay[$r])) {
                    $seenPay[$r] = true;
                    // attach to matching order
                    foreach ($orders as &$o2) {
                        if (($o2['reference_id'] ?? null) === $r) {
                            $o2['payment_method'] = $pr['payment_method'] ?? null;
                            break;
                        }
                    }
                    unset($o2);
                }
            }
        }

        // Fetch deliveries for batch (pickup/delivery rows)
        $stmt3 = $pdo->prepare("SELECT d.delivery_id, d.delivery_type, d.delivery_status_id, ds.status_name as delivery_status, d.scheduled_time, d.actual_time
            FROM deliveries d
            LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
            WHERE d.batch_id = ?");
        $stmt3->execute([$batchId]);
        $deliveries = $stmt3->fetchAll(PDO::FETCH_ASSOC);

        // Determine pickup and delivery statuses separately (use the most recent/last fetched status per type)
        $pickupStatus = null;
        $deliveryStatus = null;
        $deliveryStatusId = null;
        foreach ($deliveries as $dv) {
            if (!empty($dv['delivery_type']) && strtolower($dv['delivery_type']) === 'pickup') {
                $pickupStatus = $dv['delivery_status'] ?? $pickupStatus;
            } elseif (!empty($dv['delivery_type']) && strtolower($dv['delivery_type']) === 'delivery') {
                $deliveryStatus = $dv['delivery_status'] ?? $deliveryStatus;
                $deliveryStatusId = $dv['delivery_status_id'] ?? $deliveryStatusId;
            }
        }

        // Calculate total quantity assigned to this batch (sum of order_details.quantity for orders in batch)
        $totalQtyStmt = $pdo->prepare("SELECT COALESCE(SUM(od.quantity),0) as total_quantity FROM orders o LEFT JOIN order_details od ON o.reference_id = od.reference_id WHERE o.batch_id = ?");
        $totalQtyStmt->execute([$batchId]);
        $tqRow = $totalQtyStmt->fetch(PDO::FETCH_ASSOC);
        $total_quantity = (int)($tqRow['total_quantity'] ?? 0);

        // Determine capacity based on vehicle_type (same rules used elsewhere)
        $capacity = (isset($b['vehicle_type']) && $b['vehicle_type'] === 'Tricycle') ? 5 : 10;
        $is_full = ($total_quantity >= $capacity);

        // Determine whether this batch is completed: batch_status_id == 3 or delivery_status_id == 3 (Delivered)
        $is_completed = false;
        if (!empty($b['batch_status_id']) && (int)$b['batch_status_id'] === 3) {
            $is_completed = true;
        }
        if (!$is_completed && !empty($deliveryStatusId) && (int)$deliveryStatusId === 3) {
            $is_completed = true;
        }

        $result[] = [
            'batch_id' => $batchId,
            'batch_number' => (int)$b['batch_number'],
            'vehicle_type' => $b['vehicle_type'],
            'vehicle' => $b['vehicle'],
            'batch_status' => $b['batch_status'],
            'pickup_time' => $b['pickup_time'],
            'delivery_time' => $b['delivery_time'],
            'pickup_status' => $pickupStatus,
            'delivery_status' => $deliveryStatus,
            'total_quantity' => $total_quantity,
            'capacity' => $capacity,
            'is_full' => $is_full,
            'is_completed' => $is_completed,
            'orders' => $orders,
            'deliveries' => $deliveries
        ];
    }

    // Also include unassigned orders (no batch yet) for the admin to review within the time window
    $stmt4 = $pdo->prepare("SELECT * FROM admin_management_view WHERE batch_id IS NULL AND (DATE(order_date) BETWEEN ? AND ?) ORDER BY order_date DESC");
    $stmt4->execute([$today, $endDate]);
    $unassignedRaw = $stmt4->fetchAll(PDO::FETCH_ASSOC);
    $unassigned = [];
    $seenUn = [];
    $refsUn = [];
    foreach ($unassignedRaw as $ur) {
        $ref = $ur['reference_id'] ?? null;
        if (!$ref) continue;
        if (isset($seenUn[$ref])) continue;
        $seenUn[$ref] = true;
        $unassigned[] = $ur;
        $refsUn[] = $ref;
    }

    // fetch items for unassigned orders
    $itemsMapUn = [];
    if (count($refsUn)) {
        $place = implode(',', array_fill(0, count($refsUn), '?'));
        $q = $pdo->prepare("SELECT od.reference_id, od.container_id, od.quantity, od.subtotal, cont.container_type, cont.price
            FROM order_details od
            LEFT JOIN containers cont ON od.container_id = cont.container_id
            WHERE od.reference_id IN ($place)");
        $q->execute($refsUn);
        $rows = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $rid = $r['reference_id'];
            if (!isset($itemsMapUn[$rid])) $itemsMapUn[$rid] = [];
            $itemsMapUn[$rid][] = $r;
        }
    }
    foreach ($unassigned as &$u) {
        $rid = $u['reference_id'];
        $u['items'] = $itemsMapUn[$rid] ?? [];
    }
    unset($u);

    // Fetch payment methods for unassigned orders and attach
    if (count($refsUn)) {
        $place = implode(',', array_fill(0, count($refsUn), '?'));
        $qp = $pdo->prepare("SELECT p.reference_id, pm.method_name as payment_method, p.payment_date
            FROM payments p
            LEFT JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
            WHERE p.reference_id IN ($place)
            ORDER BY p.payment_date DESC");
        $qp->execute($refsUn);
        $payRows = $qp->fetchAll(PDO::FETCH_ASSOC);
        $seenPay = [];
        foreach ($payRows as $pr) {
            $r = $pr['reference_id'];
            if (!isset($seenPay[$r])) {
                $seenPay[$r] = true;
                foreach ($unassigned as &$u2) {
                    if (($u2['reference_id'] ?? null) === $r) {
                        $u2['payment_method'] = $pr['payment_method'] ?? null;
                        break;
                    }
                }
                unset($u2);
            }
        }
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'data' => ['batches' => $result, 'unassigned' => $unassigned, 'window' => ['start' => $today, 'end' => $endDate, 'days' => $days]]]);
} catch (PDOException $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $e->getMessage()]);
}

exit;
