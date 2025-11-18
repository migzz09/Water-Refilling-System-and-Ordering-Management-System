<?php
/**
 * Confirm Account Deletion
 * Verifies deletion token and permanently deletes account
 * Method: POST
 * Body: { "token": "..." }
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

require_once __DIR__ . '/../../config/connect.php';

try {
    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';

    if (empty($token)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Deletion token is required']);
        exit;
    }

    // Find account with valid deletion token
    $stmt = $pdo->prepare('
        SELECT a.customer_id, a.deletion_expires, c.email, c.first_name
        FROM accounts a 
        JOIN customers c ON a.customer_id = c.customer_id 
        WHERE a.deletion_token = ? AND a.deletion_expires > NOW()
    ');
    $stmt->execute([$token]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired deletion token']);
        exit;
    }

    $customerId = $account['customer_id'];

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Helper function to check if table exists
        $tableExists = function($tableName) use ($pdo) {
            try {
                $result = $pdo->query("SELECT 1 FROM `$tableName` LIMIT 1");
                return true;
            } catch (PDOException $e) {
                return false;
            }
        };

        // Delete related data in order of foreign key dependencies
        // 1. Delete order items (if table exists)
        if ($tableExists('order_items')) {
            $stmt = $pdo->prepare('
                DELETE oi FROM order_items oi
                JOIN orders o ON oi.order_id = o.order_id
                WHERE o.customer_id = ?
            ');
            $stmt->execute([$customerId]);
        }

        // 2. Delete orders (if table exists)
        if ($tableExists('orders')) {
            $stmt = $pdo->prepare('DELETE FROM orders WHERE customer_id = ?');
            $stmt->execute([$customerId]);
        }

        // 3. Delete cart items (if table exists)
        if ($tableExists('cart')) {
            $stmt = $pdo->prepare('DELETE FROM cart WHERE customer_id = ?');
            $stmt->execute([$customerId]);
        }

        // 4. Delete addresses (if table exists)
        if ($tableExists('addresses')) {
            $stmt = $pdo->prepare('DELETE FROM addresses WHERE customer_id = ?');
            $stmt->execute([$customerId]);
        }

        // 5. Delete feedback (if table exists)
        if ($tableExists('feedback')) {
            $stmt = $pdo->prepare('DELETE FROM feedback WHERE customer_id = ?');
            $stmt->execute([$customerId]);
        }

        // 6. Delete customer profile picture if exists
        $stmt = $pdo->prepare('SELECT profile_photo FROM accounts WHERE customer_id = ?');
        $stmt->execute([$customerId]);
        $profilePhoto = $stmt->fetchColumn();
        
        if ($profilePhoto) {
            $photoPath = __DIR__ . '/../../assets/images/profiles/' . $profilePhoto;
            if (file_exists($photoPath)) {
                @unlink($photoPath);
            }
        }

        // 7. Delete customer record (if table exists)
        if ($tableExists('customers')) {
            $stmt = $pdo->prepare('DELETE FROM customers WHERE customer_id = ?');
            $stmt->execute([$customerId]);
        }

        // 8. Delete account record
        $stmt = $pdo->prepare('DELETE FROM accounts WHERE customer_id = ?');
        $stmt->execute([$customerId]);

        // Commit transaction
        $pdo->commit();

        // Clear session
        session_unset();
        session_destroy();

        echo json_encode([
            'success' => true,
            'message' => 'Account successfully deleted',
            'redirect' => '/wrsoms/index.html'
        ]);

    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log('confirm-deletion.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
