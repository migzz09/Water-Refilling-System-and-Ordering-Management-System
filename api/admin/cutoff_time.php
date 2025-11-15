<?php
/**
 * Admin Cutoff Time Management API
 * Get and update order cutoff time setting
 */
session_start();
require_once __DIR__ . '/../../config/connect.php';

// Set JSON header
header('Content-Type: application/json');

// Admin authentication check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Fetch cutoff time setting
        $stmt = $pdo->prepare("
            SELECT 
                id,
                is_enabled,
                TIME_FORMAT(cutoff_time, '%H:%i') as cutoff_time,
                updated_at
            FROM cutoff_time_setting
            LIMIT 1
        ");
        $stmt->execute();
        $cutoffTime = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cutoffTime) {
            // Return default if not found
            $cutoffTime = [
                'is_enabled' => true,
                'cutoff_time' => '16:00'
            ];
        } else {
            $cutoffTime['is_enabled'] = (bool)$cutoffTime['is_enabled'];
        }
        
        echo json_encode([
            'success' => true,
            'cutoff_time' => $cutoffTime
        ]);
        
    } elseif ($method === 'POST') {
        // Update cutoff time
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['cutoff_time'])) {
            throw new Exception('Cutoff time is required');
        }
        
        $isEnabled = isset($input['is_enabled']) ? $input['is_enabled'] : true;
        $cutoffTime = $input['cutoff_time'];
        
        // Validate time format
        if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $cutoffTime)) {
            throw new Exception('Invalid time format. Use HH:MM format');
        }
        
        // Update or insert
        $stmt = $pdo->prepare("
            INSERT INTO cutoff_time_setting (id, is_enabled, cutoff_time)
            VALUES (1, ?, ?)
            ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                cutoff_time = VALUES(cutoff_time)
        ");
        
        $stmt->execute([
            $isEnabled ? 1 : 0,
            $cutoffTime
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cutoff time updated successfully'
        ]);
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Cutoff time API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
