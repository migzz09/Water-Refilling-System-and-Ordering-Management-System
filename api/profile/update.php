<?php
/**
 * Update Profile API
 * Updates user email and contact number
 * Method: POST
 * Body: { "email": "...", "contact": "..." }
 */
header('Content-Type: application/json');
session_start();

try {
    // Check authentication
    if (empty($_SESSION['customer_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    require_once __DIR__ . '/../../config/connect.php';

    // Get input
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    $contact = trim($input['contact'] ?? '');

    $errors = [];

    // Validate email
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    // Validate contact (11 digits)
    if (!empty($contact)) {
        if (!preg_match('/^[0-9]{11}$/', $contact)) {
            $errors[] = 'Contact number must be exactly 11 digits';
        }
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }

    // Update customer record
    $stmt = $pdo->prepare('
        UPDATE customers 
        SET email = ?, customer_contact = ?
        WHERE customer_id = ?
    ');
    
    $stmt->execute([
        $email ?: null,
        $contact ?: null,
        $_SESSION['customer_id']
    ]);

    // Update session email if changed
    if (!empty($email)) {
        $_SESSION['email'] = $email;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully'
    ]);

} catch (Exception $e) {
    error_log('update.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
