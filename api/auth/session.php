<?php
/**
 * Session Check API
 * Returns current session status and user info
 */
session_start();
header('Content-Type: application/json');

try {
    if (isset($_SESSION['customer_id']) || isset($_SESSION['is_admin'])) {
        // User is logged in
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => [
                'customer_id' => $_SESSION['customer_id'] ?? null,
                'username' => $_SESSION['username'] ?? null,
                'email' => $_SESSION['email'] ?? null,
                'first_name' => $_SESSION['first_name'] ?? null,
                'last_name' => $_SESSION['last_name'] ?? null,
                'is_admin' => $_SESSION['is_admin'] ?? 0
            ],
            'message' => 'User is authenticated'
        ]);
    } else {
        // User is not logged in
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
