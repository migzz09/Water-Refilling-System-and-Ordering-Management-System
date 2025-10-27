<?php
header('Content-Type: application/json');
// Include the project's DB connection (in config/connect.php)
require_once __DIR__ . '/../../config/connect.php';

try {
    $stmt = $pdo->query("SELECT order_type_id, type_name FROM order_types ORDER BY order_type_id");
    $orderTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($orderTypes);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>