<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Include the project's DB connection (in config/connect.php)
require_once __DIR__ . '/../../config/connect.php';

try {
    // Get all containers with their prices
    $stmt = $pdo->query("SELECT container_id, container_type, price FROM containers ORDER BY container_id");
    $containers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response = [];
    foreach ($containers as $c) {
        $type = $c['container_type'];
        // map to image filename (file should exist in assets/images)
        $image = ($type === 'Round') ? 'round_container.jpg' : (($type === 'Slim') ? 'slim_container.jpg' : 'placeholder.svg');

        $response[] = [
            'container_id' => (int)$c['container_id'],
            'container_type' => $type,
            'price' => (float)$c['price'],
            'image' => $image
        ];
    }

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

?>