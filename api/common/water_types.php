<?php
header('Content-Type: application/json');
// Include the project's DB connection (in config/connect.php)
require_once __DIR__ . '/../../config/connect.php';

try {
    $stmt = $pdo->query("SELECT water_type_id, type_name, description FROM water_types ORDER BY water_type_id");
    $waterTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($waterTypes);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>