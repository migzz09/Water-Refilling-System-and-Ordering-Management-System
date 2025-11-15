<?php
/**
 * Admin Business Hours Management API
 * Get and update business operating hours
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
        // Fetch all business hours
        $stmt = $pdo->prepare("
            SELECT 
                id,
                day_of_week,
                is_open,
                TIME_FORMAT(open_time, '%H:%i') as open_time,
                TIME_FORMAT(close_time, '%H:%i') as close_time,
                updated_at
            FROM business_hours
            ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
        ");
        $stmt->execute();
        $hours = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Convert is_open to boolean
        foreach ($hours as &$hour) {
            $hour['is_open'] = (bool)$hour['is_open'];
        }
        
        echo json_encode([
            'success' => true,
            'hours' => $hours
        ]);
        
    } elseif ($method === 'POST') {
        // Update business hours
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['hours']) || !is_array($input['hours'])) {
            throw new Exception('Invalid hours data');
        }
        
        $pdo->beginTransaction();
        
        try {
            foreach ($input['hours'] as $hour) {
                if (!isset($hour['day_of_week']) || !isset($hour['is_open']) || 
                    !isset($hour['open_time']) || !isset($hour['close_time'])) {
                    throw new Exception('Missing required fields');
                }
                
                // Validate day of week
                $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                if (!in_array($hour['day_of_week'], $validDays)) {
                    throw new Exception('Invalid day of week: ' . $hour['day_of_week']);
                }
                
                // Validate time format
                if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $hour['open_time']) ||
                    !preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $hour['close_time'])) {
                    throw new Exception('Invalid time format');
                }
                
                // Update or insert
                $stmt = $pdo->prepare("
                    INSERT INTO business_hours (day_of_week, is_open, open_time, close_time)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        is_open = VALUES(is_open),
                        open_time = VALUES(open_time),
                        close_time = VALUES(close_time)
                ");
                
                $stmt->execute([
                    $hour['day_of_week'],
                    $hour['is_open'] ? 1 : 0,
                    $hour['open_time'],
                    $hour['close_time']
                ]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Business hours updated successfully'
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Business hours API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
