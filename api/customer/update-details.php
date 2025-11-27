<?php
/**
 * Update Personal Details API
 * Updates customer personal information and address
 * Method: POST
 * Body: { "firstName": "...", "middleName": "...", "lastName": "...", "contact": "...", "street": "...", "barangay": "...", "city": "...", "province": "..." }
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
    $firstName = trim($input['firstName'] ?? '');
    $middleName = trim($input['middleName'] ?? '');
    $lastName = trim($input['lastName'] ?? '');
    $contact = trim($input['contact'] ?? '');
    $street = trim($input['street'] ?? '');
    $barangay = trim($input['barangay'] ?? '');
    $city = trim($input['city'] ?? '');
    $province = trim($input['province'] ?? '');

    $errors = [];

    // Validate required fields
    if (empty($firstName)) {
        $errors[] = 'First name is required';
    }
    if (empty($lastName)) {
        $errors[] = 'Last name is required';
    }
    if (empty($contact)) {
        $errors[] = 'Contact number is required';
    } elseif (!preg_match('/^[0-9]{11}$/', $contact)) {
        $errors[] = 'Contact number must be exactly 11 digits';
    }
    if (empty($street)) {
        $errors[] = 'Street address is required';
    }
    if (empty($barangay)) {
        $errors[] = 'Barangay is required';
    }
    if (empty($city)) {
        $errors[] = 'City is required';
    }
    if (empty($province)) {
        $errors[] = 'Province is required';
    }

    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }

    // Update customer record
    $stmt = $pdo->prepare('
        UPDATE customers 
        SET first_name = ?, 
            middle_name = ?, 
            last_name = ?, 
            customer_contact = ?, 
            street = ?, 
            barangay = ?, 
            city = ?, 
            province = ?
        WHERE customer_id = ?
    ');
    
    $stmt->execute([
        $firstName,
        $middleName ?: null,
        $lastName,
        $contact,
        $street,
        $barangay,
        $city,
        $province,
        $_SESSION['customer_id']
    ]);

    // Update session data
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;

    echo json_encode([
        'success' => true,
        'message' => 'Personal details updated successfully'
    ]);

} catch (Exception $e) {
    error_log('update-details.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
