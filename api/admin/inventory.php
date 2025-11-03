<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connect.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get all inventory with container details
        $stmt = $pdo->query("
            SELECT 
                i.container_id,
                i.container_type,
                i.stock as stock_quantity,
                i.last_updated,
                c.price
            FROM inventory i
            LEFT JOIN containers c ON i.container_id = c.container_id
            ORDER BY i.container_id
        ");
        
        $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add size information based on container type
        $inventory = array_map(function($item) {
            $item['size'] = $item['container_type'] === 'Round' ? '5 Gallons' : '3 Gallons';
            return $item;
        }, $inventory);
        
        echo json_encode([
            'success' => true,
            'data' => $inventory
        ]);
        
    } else if ($method === 'PUT' || $method === 'PATCH') {
        // Update stock quantity for a container
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['container_id']) || !isset($data['stock'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'container_id and stock are required'
            ]);
            exit;
        }
        
        $container_id = (int)$data['container_id'];
        $stock = (int)$data['stock'];
        
        if ($stock < 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Stock quantity cannot be negative'
            ]);
            exit;
        }
        
        // Update the stock
        $stmt = $pdo->prepare("
            UPDATE inventory 
            SET stock = ?, last_updated = NOW() 
            WHERE container_id = ?
        ");
        $stmt->execute([$stock, $container_id]);
        
        if ($stmt->rowCount() === 0) {
            // No rows updated, maybe container doesn't exist in inventory
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Container not found in inventory'
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Stock updated successfully',
            'data' => [
                'container_id' => $container_id,
                'stock' => $stock
            ]
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Inventory API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
