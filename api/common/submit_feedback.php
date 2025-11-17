<?php
/**
 * Submit Customer Feedback API
 * Handles customer feedback submission
 */
session_start();
require_once __DIR__ . '/../../config/connect.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please log in to submit feedback'
    ]);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (!isset($input['rating']) || !isset($input['category']) || !isset($input['message'])) {
        throw new Exception('Missing required fields');
    }
    
    $customer_id = $_SESSION['customer_id'];
    $rating = intval($input['rating']);
    $category = trim($input['category']);
    $subject = isset($input['subject']) ? trim($input['subject']) : '';
    $message = trim($input['message']);
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        throw new Exception('Rating must be between 1 and 5');
    }
    
    // Validate message
    if (empty($message)) {
        throw new Exception('Feedback message is required');
    }
    
    // Get or create category_id from feedback_category table
    $category_id = null;
    $categoryStmt = $pdo->prepare("SELECT category_id FROM feedback_category WHERE LOWER(category_name) = LOWER(?)");
    $categoryStmt->execute([$category]);
    $categoryResult = $categoryStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($categoryResult) {
        $category_id = $categoryResult['category_id'];
    } else {
        // If category doesn't exist, create it
        $insertCategoryStmt = $pdo->prepare("INSERT INTO feedback_category (category_name) VALUES (?)");
        $insertCategoryStmt->execute([ucfirst($category)]);
        $category_id = $pdo->lastInsertId();
    }
    
    // Prepare feedback text with category and subject (kept for backward compatibility)
    $feedback_text = "Category: " . $category;
    if (!empty($subject)) {
        $feedback_text .= "\nSubject: " . $subject;
    }
    $feedback_text .= "\n\n" . $message;
    
    // Insert feedback into database with normalized category_id
    // Note: reference_id can be NULL for general feedback not tied to a specific order
    $stmt = $pdo->prepare("
        INSERT INTO customer_feedback (
            customer_id, 
            category_id,
            rating, 
            feedback_text
        ) VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $customer_id,
        $category_id,
        $rating,
        $feedback_text
    ]);
    
    $feedback_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your feedback!',
        'data' => [
            'feedback_id' => $feedback_id
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
