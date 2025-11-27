<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/../../config/connect.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id']) || !isset($_SESSION['username'])) {
    if (isset($_GET['search'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized', 'transactions' => []]);
        exit();
    }
    header('Location: ../../pages/login.html');
    exit();
}

$customer_id = $_SESSION['customer_id'];

// Handle AJAX search request
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    
    try {
        error_log("Transaction History API: Starting query for customer_id = $customer_id");
        
        // Get completed orders grouped by checkout_id
        $sql_active = "
            SELECT 
                o.checkout_id,
                MIN(o.reference_id) as first_reference_id,
                GROUP_CONCAT(DISTINCT o.reference_id ORDER BY o.reference_id SEPARATOR ', ') as all_reference_ids,
                MIN(o.order_date) as order_date,
                MAX(o.delivery_date) as delivery_date,
                SUM(o.total_amount) as total_amount,
                os.status_name AS delivery_status,
                'active' as source,
                COUNT(DISTINCT o.reference_id) as order_count
            FROM orders o
            LEFT JOIN order_status os ON o.order_status_id = os.status_id
            WHERE o.customer_id = :customer_id
            AND o.order_status_id = 3
            AND o.checkout_id IS NOT NULL
            AND o.checkout_id > 0
        ";

        $params = ['customer_id' => $customer_id];

        // Apply search filter if provided
        if (!empty($search_query)) {
            $search_length = strlen($search_query);
            $conditions = [];
            for ($i = 0; $i < $search_length; $i++) {
                $digit = $search_query[$i];
                if (is_numeric($digit)) {
                    $position = $i + 1;
                    $conditions[] = "SUBSTRING(o.reference_id FROM $position FOR 1) = :digit_$i";
                    $params["digit_$i"] = $digit;
                }
            }
            if (!empty($conditions)) {
                $sql_active .= " AND (" . implode(" OR ", $conditions) . ")";
            }
        }

        $sql_active .= " GROUP BY o.checkout_id ORDER BY MIN(o.order_date) DESC";

        $stmt = $pdo->prepare($sql_active);
        $stmt->execute($params);
        $checkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Transaction History API: Found " . count($checkouts) . " checkouts");

        $transactions = [];

        // For each checkout, get all order details
        foreach ($checkouts as $checkout) {
            $checkout_id = $checkout['checkout_id'];
            
            // Get all items for this checkout
            $items_sql = "
                SELECT 
                    o.reference_id,
                    od.quantity, 
                    cont.container_type, 
                    cont.price AS container_price, 
                    od.subtotal,
                    ot.type_name as order_type_name,
                    ot.order_type_id AS order_type_id
                FROM orders o
                JOIN order_details od ON o.reference_id = od.reference_id
                LEFT JOIN containers cont ON od.container_id = cont.container_id
                LEFT JOIN order_types ot ON od.order_type_id = ot.order_type_id
                WHERE o.checkout_id = :checkout_id
                ORDER BY o.reference_id, od.order_detail_id
            ";
            
            $items_stmt = $pdo->prepare($items_sql);
            $items_stmt->execute(['checkout_id' => $checkout_id]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group items by reference_id to show order structure
            $orders_in_checkout = [];
            foreach ($items as $item) {
                $ref = $item['reference_id'];
                if (!isset($orders_in_checkout[$ref])) {
                    $orders_in_checkout[$ref] = [];
                }
                $orders_in_checkout[$ref][] = $item;
            }
            
            $transactions[] = [
                'checkout_id' => $checkout_id,
                'reference_id' => $checkout['first_reference_id'],
                'all_reference_ids' => $checkout['all_reference_ids'],
                'order_date' => $checkout['order_date'],
                'delivery_date' => $checkout['delivery_date'],
                'total_amount' => $checkout['total_amount'],
                'delivery_status' => $checkout['delivery_status'],
                'source' => 'active',
                'order_count' => $checkout['order_count'],
                'items' => $items,
                'orders' => $orders_in_checkout
            ];
        }

        // Also get orders WITHOUT checkout_id (old orders before checkout system)
        $sql_no_checkout = "
            SELECT 
                o.reference_id,
                o.order_date,
                o.delivery_date,
                o.total_amount,
                os.status_name AS delivery_status
            FROM orders o
            LEFT JOIN order_status os ON o.order_status_id = os.status_id
            WHERE o.customer_id = :customer_id
            AND o.order_status_id = 3
            AND (o.checkout_id IS NULL OR o.checkout_id = 0)
        ";
        
        $params_no_checkout = ['customer_id' => $customer_id];
        
        if (!empty($search_query)) {
            $search_length = strlen($search_query);
            $conditions = [];
            for ($i = 0; $i < $search_length; $i++) {
                $digit = $search_query[$i];
                if (is_numeric($digit)) {
                    $position = $i + 1;
                    $conditions[] = "SUBSTRING(o.reference_id FROM $position FOR 1) = :no_checkout_digit_$i";
                    $params_no_checkout["no_checkout_digit_$i"] = $digit;
                }
            }
            if (!empty($conditions)) {
                $sql_no_checkout .= " AND (" . implode(" OR ", $conditions) . ")";
            }
        }
        
        $sql_no_checkout .= " ORDER BY o.order_date DESC";
        
        $stmt_no_checkout = $pdo->prepare($sql_no_checkout);
        $stmt_no_checkout->execute($params_no_checkout);
        $orders_no_checkout = $stmt_no_checkout->fetchAll(PDO::FETCH_ASSOC);
        
        // Process orders without checkout_id
        foreach ($orders_no_checkout as $order) {
            // Get items for this order
            $items_sql = "
                SELECT 
                    o.reference_id,
                    od.quantity, 
                    cont.container_type, 
                    cont.price AS container_price, 
                    od.subtotal,
                    ot.type_name as order_type_name,
                    ot.order_type_id AS order_type_id
                FROM orders o
                JOIN order_details od ON o.reference_id = od.reference_id
                LEFT JOIN containers cont ON od.container_id = cont.container_id
                LEFT JOIN order_types ot ON od.order_type_id = ot.order_type_id
                WHERE o.reference_id = :reference_id
            ";
            
            $items_stmt = $pdo->prepare($items_sql);
            $items_stmt->execute(['reference_id' => $order['reference_id']]);
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $transactions[] = [
                'reference_id' => $order['reference_id'],
                'order_date' => $order['order_date'],
                'delivery_date' => $order['delivery_date'],
                'total_amount' => $order['total_amount'],
                'delivery_status' => $order['delivery_status'],
                'source' => 'active',
                'items' => $items,
                'orders' => [$order['reference_id'] => $items]
            ];
        }

        // TODO: Add archived orders support when table is created
        /*
        // Then, get archived orders for this customer
        $sql_archived = "
            SELECT reference_id, user_id, delivery_date, total_amount, order_data
            FROM archived_orders
            WHERE user_id = :customer_id
        ";
        
        $params_archived = ['customer_id' => $customer_id];
        
        // Apply search filter for archived orders
        if (!empty($search_query)) {
            $search_length = strlen($search_query);
            $conditions = [];
            for ($i = 0; $i < $search_length; $i++) {
                $digit = $search_query[$i];
                if (is_numeric($digit)) {
                    $position = $i + 1;
                    $conditions[] = "SUBSTRING(reference_id FROM $position FOR 1) = :archived_digit_$i";
                    $params_archived["archived_digit_$i"] = $digit;
                }
            }
            if (!empty($conditions)) {
                $sql_archived .= " AND (" . implode(" OR ", $conditions) . ")";
            }
        }
        
        $stmt_archived = $pdo->prepare($sql_archived);
        $stmt_archived->execute($params_archived);
        $archived_orders = $stmt_archived->fetchAll(PDO::FETCH_ASSOC);
        
        // Parse archived orders JSON and add to transactions
        foreach ($archived_orders as $archived) {
            $order_data = json_decode($archived['order_data'], true);
            if ($order_data && isset($order_data['items'])) {
                // Group archived order items into single transaction
                $archived_items = [];
                foreach ($order_data['items'] as $item) {
                    // Map order type name to ID
                    $order_type_name = $item['order_type_name'] ?? '';
                    $order_type_id = null;
                    if (stripos($order_type_name, 'Refill') !== false && stripos($order_type_name, 'Buy') === false) {
                        $order_type_id = 1; // Refill
                    } elseif (stripos($order_type_name, 'Purchase') !== false || stripos($order_type_name, 'Buy') !== false) {
                        $order_type_id = 2; // Purchase New Container
                    }
                    
                    $archived_items[] = [
                        'reference_id' => $archived['reference_id'],
                        'quantity' => $item['quantity'] ?? 0,
                        'container_type' => $item['container_type'] ?? '',
                        'container_price' => 0,
                        'subtotal' => $item['subtotal'] ?? 0,
                        'order_type_id' => $order_type_id,
                        'order_type_name' => $order_type_name
                    ];
                }
                
                $transactions[] = [
                    'reference_id' => $archived['reference_id'],
                    'order_date' => $order_data['order_date'] ?? $archived['delivery_date'],
                    'delivery_date' => $archived['delivery_date'],
                    'total_amount' => $archived['total_amount'],
                    'delivery_status' => 'Archived - ' . ($order_data['delivery_status'] ?? 'Completed'),
                    'source' => 'archived',
                    'items' => $archived_items,
                    'orders' => [$archived['reference_id'] => $archived_items]
                ];
            }
        }
        */
        
        // Sort all transactions by order_date descending
        usort($transactions, function($a, $b) {
            return strtotime($b['order_date']) - strtotime($a['order_date']);
        });

        // Return JSON response for AJAX
        header('Content-Type: application/json');
        error_log("Transaction History API: Returning " . count($transactions) . " transactions");
        echo json_encode(['transactions' => $transactions]);
    } catch (Exception $e) {
        error_log("Transaction History API ERROR: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'transactions' => []]);
    }
    exit;
}

// If not AJAX request, redirect to the transaction history page
header('Location: ../../pages/usertransaction-history.html');
exit;
