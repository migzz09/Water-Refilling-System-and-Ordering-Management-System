<?php
/**
 * Check Order Cutoff Time Status
 * Public API to check if orders can be placed at current time
 */

date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/../../config/connect.php';

// Set JSON header
header('Content-Type: application/json');

try {
    $currentTime = date('H:i:s');
    $currentDay = date('l'); // Monday, Tuesday, etc.
    
    // Get cutoff time setting
    $stmt = $pdo->prepare("
        SELECT 
            is_enabled,
            TIME_FORMAT(cutoff_time, '%H:%i') as cutoff_time
        FROM cutoff_time_setting
        LIMIT 1
    ");
    $stmt->execute();
    $cutoffSetting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's business hours
    $stmt = $pdo->prepare("
        SELECT 
            is_open,
            TIME_FORMAT(open_time, '%H:%i') as open_time,
            TIME_FORMAT(close_time, '%H:%i') as close_time
        FROM business_hours
        WHERE day_of_week = ?
    ");
    $stmt->execute([$currentDay]);
    $todayHours = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $canPlaceOrder = false;
    $message = '';
    $reason = '';
    
    // Check if business is open today
    if (!$todayHours || !$todayHours['is_open']) {
        $message = "We're closed today. Orders cannot be placed.";
        $reason = 'business_closed';
    } 
    // Check if within business hours
    else {
        $currentTimeObj = strtotime($currentTime);
        $openTimeObj = strtotime($todayHours['open_time']);
        $closeTimeObj = strtotime($todayHours['close_time']);
        
        if ($currentTimeObj < $openTimeObj) {
            $message = "We haven't opened yet. Please place your order after {$todayHours['open_time']}.";
            $reason = 'before_opening';
        } elseif ($currentTimeObj > $closeTimeObj) {
            $message = "We're closed for today. Please place your order tomorrow.";
            $reason = 'after_closing';
        }
        // Check cutoff time
        elseif ($cutoffSetting && $cutoffSetting['is_enabled']) {
            $cutoffTimeObj = strtotime($cutoffSetting['cutoff_time']);
            
            if ($currentTimeObj <= $cutoffTimeObj) {
                $canPlaceOrder = true;
                $message = "Orders are being accepted for today.";
                $reason = 'within_cutoff';
            } else {
                $canPlaceOrder = false;
                $message = "Sorry, today's order cutoff time has passed. Please order tomorrow.";
                $reason = 'after_cutoff';
            }
        } else {
            // Cutoff time disabled, orders accepted during business hours
            $canPlaceOrder = true;
            $message = "Orders are being accepted.";
            $reason = 'no_cutoff';
        }
    }
    
    echo json_encode([
        'success' => true,
        'can_place_order' => $canPlaceOrder,
        'message' => $message,
        'reason' => $reason,
        'current_time' => date('H:i'),
        'cutoff_time' => $cutoffSetting['cutoff_time'] ?? null,
        'cutoff_enabled' => (bool)($cutoffSetting['is_enabled'] ?? false),
        'business_hours' => $todayHours
    ]);
    
} catch (Exception $e) {
    error_log("Order cutoff status API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to check order status: ' . $e->getMessage()
    ]);
}
