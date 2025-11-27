<?php
/**
 * Get Current Business Hours Status
 * Public API to check if business is currently open
 */
require_once __DIR__ . '/../config/connect.php';

// Set JSON header
header('Content-Type: application/json');

try {
    $currentDay = date('l'); // Monday, Tuesday, etc.
    $currentTime = date('H:i:s');
    
    $stmt = $pdo->prepare("
        SELECT 
            day_of_week,
            is_open,
            TIME_FORMAT(open_time, '%H:%i') as open_time,
            TIME_FORMAT(close_time, '%H:%i') as close_time
        FROM business_hours
        WHERE day_of_week = ?
    ");
    $stmt->execute([$currentDay]);
    $today = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$today) {
        // Default to closed if no data
        echo json_encode([
            'success' => true,
            'is_currently_open' => false,
            'day' => $currentDay,
            'message' => 'Business hours not configured'
        ]);
        exit;
    }
    
    $isOpen = (bool)$today['is_open'];
    $isCurrentlyOpen = false;
    $message = '';
    
    if ($isOpen) {
        $currentTimeObj = strtotime($currentTime);
        $openTimeObj = strtotime($today['open_time']);
        $closeTimeObj = strtotime($today['close_time']);
        
        if ($currentTimeObj >= $openTimeObj && $currentTimeObj <= $closeTimeObj) {
            $isCurrentlyOpen = true;
            $message = "We're open! Today's hours: {$today['open_time']} - {$today['close_time']}";
        } else {
            $message = "We're closed right now. Today's hours: {$today['open_time']} - {$today['close_time']}";
        }
    } else {
        $message = "We're closed today";
    }
    
    // Get all week hours for reference
    $stmt = $pdo->prepare("
        SELECT 
            day_of_week,
            is_open,
            TIME_FORMAT(open_time, '%H:%i') as open_time,
            TIME_FORMAT(close_time, '%H:%i') as close_time
        FROM business_hours
        ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
    ");
    $stmt->execute();
    $weekHours = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($weekHours as &$hour) {
        $hour['is_open'] = (bool)$hour['is_open'];
    }
    
    echo json_encode([
        'success' => true,
        'is_currently_open' => $isCurrentlyOpen,
        'current_day' => $currentDay,
        'current_time' => date('H:i'),
        'today_hours' => $today,
        'week_hours' => $weekHours,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    error_log("Business hours status API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check business hours: ' . $e->getMessage()
    ]);
}
