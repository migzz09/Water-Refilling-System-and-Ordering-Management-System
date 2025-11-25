<?php
ob_start();
session_start();
ob_clean();
header('Content-Type: application/json');

try {
    $conn = new PDO("mysql:host=localhost;dbname=wrsoms;charset=utf8mb4", 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT 
            p.payment_id,
            p.reference_id,
            p.amount_paid as amount,
            p.payment_date,
            p.transaction_reference,
            pm.method_name as payment_method,
            ps.status_name as payment_status,
            CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
            o.delivery_personnel_name as driver_name
        FROM payments p
        INNER JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
        INNER JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id
        LEFT JOIN orders o ON p.reference_id = o.reference_id
        LEFT JOIN customers c ON o.customer_id = c.customer_id
        WHERE pm.method_name IN ('COD', 'GCash')
        ORDER BY p.payment_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'payments' => $payments
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'line' => $e->getLine()
    ]);
}

ob_end_flush();
