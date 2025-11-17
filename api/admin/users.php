<?php
// Start output buffering to catch any errors
ob_start();

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1); // Enable to see actual error
ini_set('log_errors', 1);

ob_clean();

header('Content-Type: application/json');

try {
    // Manual database connection
    $host = 'localhost';
    $dbname = 'wrsoms';
    $username = 'root';
    $password = '';
    
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // GET single user details
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user_id'])) {
        $userId = $_GET['user_id'];
        
        $sql = "SELECT 
                    a.account_id as user_id,
                    a.username,
                    a.is_admin,
                    a.is_verified,
                    a.password_changed_at,
                    a.profile_photo,
                    c.customer_id,
                    c.first_name,
                    c.middle_name,
                    c.last_name,
                    c.email,
                    c.customer_contact,
                    c.street,
                    c.barangay,
                    c.city,
                    c.province,
                    c.date_created,
                    COUNT(DISTINCT o.reference_id) as total_orders,
                    COALESCE(SUM(o.total_amount), 0) as total_spent
                FROM accounts a
                LEFT JOIN customers c ON a.customer_id = c.customer_id
                LEFT JOIN orders o ON c.customer_id = o.customer_id
                WHERE a.account_id = :user_id
                GROUP BY a.account_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Get recent orders
            $orderSql = "SELECT 
                            reference_id,
                            order_date,
                            delivery_date,
                            total_amount,
                            os.status_name as order_status
                        FROM orders o
                        JOIN order_status os ON o.order_status_id = os.status_id
                        WHERE o.customer_id = :customer_id
                        ORDER BY o.order_date DESC
                        LIMIT 5";
            
            $orderStmt = $conn->prepare($orderSql);
            $orderStmt->execute([':customer_id' => $user['customer_id']]);
            $user['recent_orders'] = $orderStmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
        }
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        if ($action === 'create') {
            // Create new user
            $sql = "INSERT INTO accounts (username, password, is_admin, customer_id) 
                    VALUES (:username, :password, :is_admin, :customer_id)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':username' => $input['username'],
                ':password' => password_hash($input['password'], PASSWORD_DEFAULT),
                ':is_admin' => $input['is_admin'] ?? 0,
                ':customer_id' => $input['customer_id'] ?? NULL
            ]);
            
            echo json_encode(['success' => true, 'message' => 'User created successfully']);
            
        } elseif ($action === 'update') {
            // Update user
            $sql = "UPDATE accounts SET username = :username, is_admin = :is_admin 
                    WHERE account_id = :account_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':username' => $input['username'],
                ':is_admin' => $input['is_admin'],
                ':account_id' => $input['user_id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
            
        } elseif ($action === 'delete') {
            // Delete user
            $sql = "DELETE FROM accounts WHERE account_id = :account_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':account_id' => $input['user_id']]);
            
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        }
        
    } else {
        // GET request - Fetch accounts with customer info
        $sql = "SELECT 
                    a.account_id as user_id,
                    a.username,
                    a.is_admin,
                    a.is_verified,
                    CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as full_name,
                    c.email,
                    c.customer_contact
                FROM accounts a
                LEFT JOIN customers c ON a.customer_id = c.customer_id
                ORDER BY a.account_id DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'users' => $users ?: []
        ]);
    }
    
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
