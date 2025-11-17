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
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }

    $file = $_FILES['photo'];
    $customerId = $_SESSION['customer_id'];
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size too large. Maximum 5MB allowed.');
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../assets/images/profiles/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $customerId . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Failed to save uploaded file');
    }
    
    // Update database
    require_once __DIR__ . '/../../config/connect.php';
    
    // Delete old photo if exists
    $stmt = $pdo->prepare('SELECT profile_photo FROM accounts WHERE customer_id = ?');
    $stmt->execute([$customerId]);
    $oldPhoto = $stmt->fetchColumn();
    
    if ($oldPhoto && file_exists(__DIR__ . '/../../assets/images/profiles/' . $oldPhoto)) {
        unlink(__DIR__ . '/../../assets/images/profiles/' . $oldPhoto);
    }
    
    // Update with new photo
    $stmt = $pdo->prepare('UPDATE accounts SET profile_photo = ? WHERE customer_id = ?');
    $stmt->execute([$filename, $customerId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile photo updated successfully',
        'filename' => $filename,
        'url' => '/WRSOMS/assets/images/profiles/' . $filename
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
