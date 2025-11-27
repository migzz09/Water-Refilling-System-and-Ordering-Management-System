<?php
/**
 * Logout API Endpoint
 * Method: POST
 */
session_start();
header('Content-Type: application/json');

try {
    // Clear session
    session_unset();
    session_destroy();
    
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Logout failed: ' . $e->getMessage()
    ]);
}
