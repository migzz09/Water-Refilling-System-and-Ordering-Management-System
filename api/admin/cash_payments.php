<?php
// Start output buffering to catch any errors
ob_start();

// Session must start FIRST before any output
session_start();

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

try {
    // Manual database connection instead of using includes
    $host = 'localhost';
    $dbname = 'wrsoms'; // Change this to your actual database name
    $username = 'root';
    $password = '';
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // GET single payment details
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['payment_id'])) {
        $paymentId = $_GET['payment_id'];
        
        $sql = "SELECT 
                    p.payment_id,
                    p.reference_id,
                    p.amount_paid,
                    p.payment_date,
                    p.transaction_reference,
                    ps.status_name as payment_status,
                    pm.method_name as payment_method,
                    o.order_date,
                    o.delivery_date,
                    o.total_amount,
                    os.status_name as order_status,
                    CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) as customer_name,
                    c.email,
                    c.customer_contact,
                    c.street,
                    c.barangay,
                    c.city,
                    c.province,
                    b.batch_id,
                    b.vehicle,
                    b.vehicle_type
                FROM payments p
                INNER JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id
                INNER JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
                LEFT JOIN orders o ON p.reference_id = o.reference_id
                LEFT JOIN order_status os ON o.order_status_id = os.status_id
                LEFT JOIN customers c ON o.customer_id = c.customer_id
                LEFT JOIN batches b ON o.batch_id = b.batch_id
                WHERE p.payment_id = :payment_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':payment_id' => $paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment) {
            // Get order items
            $itemsSql = "SELECT 
                            od.quantity,
                            od.subtotal,
                            c.container_type,
                            c.price,
                            wt.type_name as water_type,
                            ot.type_name as order_type
                        FROM order_details od
                        JOIN containers c ON od.container_id = c.container_id
                        LEFT JOIN water_types wt ON od.water_type_id = wt.water_type_id
                        LEFT JOIN order_types ot ON od.order_type_id = ot.order_type_id
                        WHERE od.reference_id = :reference_id";
            
            $itemsStmt = $conn->prepare($itemsSql);
            $itemsStmt->execute([':reference_id' => $payment['reference_id']]);
            $payment['order_items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'payment' => $payment
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Payment not found'
            ]);
        }
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle verify payment action
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['action']) && $input['action'] === 'verify' && isset($input['payment_id'])) {
            $paymentId = $input['payment_id'];
            
            // Update payment status to Paid (status_id = 2)
            $sql = "UPDATE payments 
                    SET payment_status_id = 2
                    WHERE payment_id = :payment_id";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([':payment_id' => $paymentId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment verified successfully'
            ]);
            exit;
        }
    }
    
    // GET request - Fetch COD payments with customer details
        $sql = "SELECT 
                p.payment_id,
                p.reference_id,
                p.amount_paid as amount,
                p.payment_date,
                p.transaction_reference,
                ps.status_name as payment_status,
                pm.method_name as payment_method,
                CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name
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
        'payments' => $payments ?: []
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error',
        'error' => $e->getMessage()
    ]);
}

ob_end_flush();
?>
