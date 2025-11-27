<?php
session_start();
require_once __DIR__ . '/../../config/connect.php';

// Check if admin is logged in
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    // Get active orders from orders table
    $sql_active = "
        SELECT
            o.reference_id AS order_ref,
            o.order_date,
            o.delivery_date,
            CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
            ot.type_name AS order_type,
            containers.container_type AS container_type,
            od.quantity,
            od.subtotal,
            (CASE WHEN od.quantity = 0 THEN 0 ELSE (od.subtotal / od.quantity) END) AS unit_price,
            os.status_name AS order_status,
            COALESCE(bs.status_name, 'N/A') AS batch_status,
            COALESCE(ps.status_name, 'N/A') AS payment_status,
            COALESCE(ds.status_name, 'N/A') AS delivery_status,
            'active' as source
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        LEFT JOIN order_types ot ON o.order_type_id = ot.order_type_id
        LEFT JOIN order_details od ON o.reference_id = od.reference_id
        LEFT JOIN containers ON od.container_id = containers.container_id
        LEFT JOIN order_status os ON o.order_status_id = os.status_id
        LEFT JOIN batches b ON o.batch_id = b.batch_id
        LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
        LEFT JOIN payments p ON o.reference_id = p.reference_id
        LEFT JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id
        LEFT JOIN deliveries d ON b.batch_id = d.batch_id
        LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
        ORDER BY o.order_date DESC
    ";
    
    $stmt = $pdo->query($sql_active);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get archived orders
    $sql_archived = "SELECT reference_id, user_id, delivery_date, total_amount, order_data, archived_at FROM archived_orders ORDER BY archived_at DESC";
    $stmt_archived = $pdo->query($sql_archived);
    $archived_orders = $stmt_archived->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse archived orders and add to transactions
    foreach ($archived_orders as $archived) {
        $order_data = json_decode($archived['order_data'], true);
        if ($order_data && isset($order_data['items'])) {
            foreach ($order_data['items'] as $item) {
                $transactions[] = [
                    'order_ref' => $archived['reference_id'],
                    'order_date' => $order_data['order_date'] ?? '',
                    'delivery_date' => $archived['delivery_date'],
                    'customer_name' => $order_data['customer']['name'] ?? 'N/A',
                    'order_type' => $item['order_type_name'] ?? 'N/A',
                    'container_type' => $item['container_type'] ?? 'N/A',
                    'quantity' => $item['quantity'] ?? 0,
                    'subtotal' => $item['subtotal'] ?? 0,
                    'unit_price' => ($item['quantity'] > 0) ? ($item['subtotal'] / $item['quantity']) : 0,
                    'order_status' => 'Archived',
                    'batch_status' => 'Archived',
                    'payment_status' => $order_data['payment_method'] ?? 'N/A',
                    'delivery_status' => 'Archived - ' . ($order_data['delivery_status'] ?? 'Completed'),
                    'source' => 'archived'
                ];
            }
        }
    }
    
    // Group transactions by reference_id
    $grouped = [];
    foreach ($transactions as $tx) {
        $ref = $tx['order_ref'];
        if (!isset($grouped[$ref])) {
            $grouped[$ref] = [
                'archived' => [],
                'active' => []
            ];
        }
        if ($tx['source'] === 'archived') {
            $grouped[$ref]['archived'][] = $tx;
        } else {
            $grouped[$ref]['active'][] = $tx;
        }
    }

    // For each reference_id, prefer all archived items if present, else all active
    $final = [];
    foreach ($grouped as $ref => $sets) {
        if (!empty($sets['archived'])) {
            foreach ($sets['archived'] as $item) {
                $final[] = $item;
            }
        } else {
            foreach ($sets['active'] as $item) {
                $final[] = $item;
            }
        }
    }

    // Sort all by order_date descending
    usort($final, function($a, $b) {
        return strtotime($b['order_date']) - strtotime($a['order_date']);
    });

    echo json_encode(['success' => true, 'transactions' => $final]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
