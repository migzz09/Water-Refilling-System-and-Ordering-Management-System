<?php
session_start();
header('Content-Type: application/json');

error_log("=== SESSION CHECK ===");
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));
error_log("customer_id: " . (isset($_SESSION['customer_id']) ? $_SESSION['customer_id'] : 'NOT SET'));
error_log("username: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'NOT SET'));
error_log("is_admin: " . (isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : 'NOT SET'));

try {
    if (isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id'])) {
        // Fetch additional account details from database
        require_once __DIR__ . '/../../config/connect.php';
        
        $stmt = $pdo->prepare('SELECT password_changed_at, profile_photo FROM accounts WHERE customer_id = ?');
        $stmt->execute([$_SESSION['customer_id']]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // User is logged in
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'customer_id' => $_SESSION['customer_id'],
                'username' => $_SESSION['username'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'first_name' => $_SESSION['first_name'] ?? null,
                'last_name' => $_SESSION['last_name'] ?? null,
                'is_admin' => $_SESSION['is_admin'] ?? 0,
                'password_changed_at' => $account['password_changed_at'] ?? null,
                'profile_photo' => $account['profile_photo'] ?? null
            ],
            'message' => 'User is authenticated'
        ]);
    } else if (isset($_SESSION['username']) && isset($_SESSION['is_admin'])) {
        // Fallback: If we have username and is_admin but customer_id is empty
        // This handles cases where customer_id might be NULL in database
        error_log("FALLBACK AUTH: customer_id is empty but username exists");
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'customer_id' => $_SESSION['customer_id'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'first_name' => $_SESSION['first_name'] ?? null,
                'last_name' => $_SESSION['last_name'] ?? null,
                'is_admin' => $_SESSION['is_admin'] ?? 0,
                'password_changed_at' => null,
                'profile_photo' => null
            ],
            'message' => 'User is authenticated (fallback mode)'
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
