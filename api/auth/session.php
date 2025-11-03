<?php
session_start();
header('Content-Type: application/json');

error_log("Session check - Session ID: " . session_id());
error_log("Session check - customer_id: " . (isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : 'NOT SET'));
error_log("Session check - username: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'NOT SET'));

try {
<<<<<<< HEAD
    if (isset($_SESSION['customer_id'])) {
        require_once __DIR__ . '/../../config/connect.php';
        
        $stmt = $pdo->prepare('SELECT password_changed_at, profile_photo FROM accounts WHERE customer_id = ?');
        $stmt->execute([$_SESSION['customer_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
=======
    if (isset($_SESSION['customer_id']) || isset($_SESSION['is_admin'])) {
        // User is logged in
>>>>>>> 6331eec0d731e826fbf0e3fd0d86819f75212fa7
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
<<<<<<< HEAD
                'customer_id' => $_SESSION['customer_id'],
                'username' => isset($_SESSION['username']) ? $_SESSION['username'] : null,
                'email' => isset($_SESSION['email']) ? $_SESSION['email'] : null,
                'first_name' => isset($_SESSION['first_name']) ? $_SESSION['first_name'] : null,
                'last_name' => isset($_SESSION['last_name']) ? $_SESSION['last_name'] : null,
                'password_changed_at' => isset($account['password_changed_at']) ? $account['password_changed_at'] : null,
                'profile_photo' => isset($account['profile_photo']) ? $account['profile_photo'] : null
=======
                'customer_id' => $_SESSION['customer_id'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'first_name' => $_SESSION['first_name'] ?? null,
                'last_name' => $_SESSION['last_name'] ?? null,
                'is_admin' => $_SESSION['is_admin'] ?? 0
>>>>>>> 6331eec0d731e826fbf0e3fd0d86819f75212fa7
            ],
            'message' => 'User is authenticated'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'authenticated' => false,
            'user' => null,
            'message' => 'User is not authenticated'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'authenticated' => false,
        'message' => 'Error checking session: ' . $e->getMessage()
    ]);
}
