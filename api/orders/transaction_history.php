<?php
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
    
    // Fetch delivered orders for the customer
    // order_status_id = 3 means "Delivered" (as set by complete_delivery action)
    $sql = "
        SELECT 
            o.reference_id,
            o.order_date, 
            o.delivery_date, 
            o.total_amount, 
            od.quantity, 
            cont.container_type, 
            cont.price AS container_price, 
            od.subtotal,
            os.status_name AS delivery_status,
            ot.order_type_id AS order_type_id
        FROM orders o
        LEFT JOIN order_details od ON o.reference_id = od.reference_id
        LEFT JOIN containers cont ON od.container_id = cont.container_id
        LEFT JOIN order_types ot ON o.order_type_id = ot.order_type_id
        LEFT JOIN order_status os ON o.order_status_id = os.status_id
        WHERE o.customer_id = :customer_id
        AND o.order_status_id = 3
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
            $sql .= " AND (" . implode(" OR ", $conditions) . ")";
        }
    }

    $sql .= " ORDER BY o.order_date DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return JSON response for AJAX
        header('Content-Type: application/json');
        echo json_encode(['transactions' => $transactions]);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error', 'transactions' => []]);
    }
    exit;
}

// If not AJAX request, redirect to the transaction history page
header('Location: ../../pages/usertransaction-history.html');
exit;
