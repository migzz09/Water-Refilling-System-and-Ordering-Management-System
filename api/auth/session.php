<?php
/**
 * Session Check API
 * Returns current session status and user info
 */
session_start();
header('Content-Type: application/json');

try {
    if (isset($_SESSION['customer_id'])) {
        // User is logged in
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'data' => [
                'customer_id' => $_SESSION['customer_id'],
                'username' => $_SESSION['username'] ?? null,
                'email' => $_SESSION['email'] ?? null
            ],
            'message' => 'User is authenticated'
        ]);
    } else {
        // User is not logged in
        echo json_encode([
            'success' => true,
            'authenticated' => false,
            'data' => null,
            'message' => 'User is not authenticated'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error checking session: ' . $e->getMessage()
    ]);
}
