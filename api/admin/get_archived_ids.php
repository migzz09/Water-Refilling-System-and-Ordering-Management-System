<?php
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

// Allow both admin and staff users
if (!((isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) || (isset($_SESSION['staff_id']) && isset($_SESSION['staff_role'])))) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Fetch all archived reference IDs
    $stmt = $pdo->prepare("SELECT DISTINCT reference_id FROM archived_orders ORDER BY archived_at DESC");
    $stmt->execute();
    $archived_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'data' => [
            'archived_ids' => $archived_ids,
            'count' => count($archived_ids)
        ]
    ]);

} catch (Exception $e) {
    error_log("Get archived IDs error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch archived order IDs'
    ]);
}
