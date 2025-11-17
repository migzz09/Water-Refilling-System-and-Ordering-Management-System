<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    require_once __DIR__ . '/../../config/connect.php';
    
    $customerId = $_SESSION['customer_id'];
    
    // Get current photo
    $stmt = $pdo->prepare('SELECT profile_photo FROM accounts WHERE customer_id = ?');
    $stmt->execute([$customerId]);
    $oldPhoto = $stmt->fetchColumn();
    
    // Delete file if exists
    if ($oldPhoto) {
        $filePath = __DIR__ . '/../../assets/images/profiles/' . $oldPhoto;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    // Update database
    $stmt = $pdo->prepare('UPDATE accounts SET profile_photo = NULL WHERE customer_id = ?');
    $stmt->execute([$customerId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile photo removed successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
