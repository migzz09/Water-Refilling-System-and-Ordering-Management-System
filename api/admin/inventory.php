<?php

session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connect.php';

// Allow admin or staff with 'Sales Manager' role
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
$isSalesManager = false;
if (isset($_SESSION['username'])) {
    $stmt = $pdo->prepare('SELECT staff_role FROM staff WHERE staff_user = ? LIMIT 1');
    $stmt->execute([$_SESSION['username']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && stripos($row['staff_role'], 'sales') !== false) {
        $isSalesManager = true;
    }
}
if (!$isAdmin && !$isSalesManager) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin or Sales Manager access required'
    ]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Get all inventory with container details
        // Some DBs may not have the optional `purchase_price` column (permissions/migration),
        // so attempt to include it and fall back to a safe query if the column is missing.
        try {
                $stmt = $pdo->query("
                    SELECT 
                        i.container_id,
                        i.container_type,
                        i.stock as stock_quantity,
                        i.last_updated,
                        c.price,
                        c.purchase_price,
                        c.photo,
                        c.is_visible
                    FROM inventory i
                    LEFT JOIN containers c ON i.container_id = c.container_id
                    ORDER BY i.container_id
                ");
                $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $inner) {
                // If purchase_price column doesn't exist, fall back to a query without it
                error_log('Inventory API fallback: ' . $inner->getMessage());
                $stmt = $pdo->query("
                    SELECT 
                        i.container_id,
                        i.container_type,
                        i.stock as stock_quantity,
                        i.last_updated,
                        c.price,
                        c.photo,
                        c.is_visible
                    FROM inventory i
                    LEFT JOIN containers c ON i.container_id = c.container_id
                    ORDER BY i.container_id
                ");
                $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
                // ensure purchase_price key is present (null) for each item
                foreach ($inventory as &$it) {
                    $it['purchase_price'] = null;
                }
                unset($it);
            }
        
        // Add size, image and visibility information based on container data
        $inventory = array_map(function($item) {
            $item['size'] = $item['container_type'] === 'Round' ? '5 Gallons' : '3 Gallons';
            $item['image'] = !empty($item['photo']) ? '/WRSOMS/assets/images/' . $item['photo'] : '/WRSOMS/assets/images/placeholder.svg';
            $item['is_visible'] = isset($item['is_visible']) ? (int)$item['is_visible'] : 0;
            $item['price'] = isset($item['price']) ? (float)$item['price'] : 0.0;
            // include purchase_price if present
            $item['purchase_price'] = isset($item['purchase_price']) ? (float)$item['purchase_price'] : null;
            $item['stock_quantity'] = isset($item['stock_quantity']) ? (int)$item['stock_quantity'] : 0;
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
