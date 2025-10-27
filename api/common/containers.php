<?php
header('Content-Type: application/json');
// Include the project's DB connection (in config/connect.php)
require_once __DIR__ . '/../../config/connect.php';

try {
    // Get all containers with their prices
    $stmt = $pdo->query("SELECT container_id, container_type, price FROM containers ORDER BY container_id");
    $containers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Map container types to image names
    foreach ($containers as &$container) {
        if ($container['container_type'] === 'Round') {
            // images in assets/images are JPEGs
            $container['image'] = 'round_container.jpg';
        } else if ($container['container_type'] === 'Slim') {
            $container['image'] = 'slim_container.jpg';
        } else {
            $container['image'] = 'placeholder.jpg';
        }
    }

    echo json_encode($containers);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>