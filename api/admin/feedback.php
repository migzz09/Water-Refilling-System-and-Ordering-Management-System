<?php
/**
 * Admin Feedback Management API
 * Get all customer feedback with customer details
 */
session_start();
require_once __DIR__ . '/../../config/connect.php';

// Set JSON header
header('Content-Type: application/json');

// Admin authentication check - must have is_admin flag set
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

try {
    // Get search parameter if provided
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Base query to get feedback with customer details and category
    $query = "
        SELECT 
            cf.feedback_id,
            cf.customer_id,
            cf.reference_id,
            cf.category_id,
            cf.rating,
            cf.feedback_text,
            cf.feedback_date,
            c.first_name,
            c.last_name,
            c.email,
            c.customer_contact,
            a.username,
            fc.category_name
        FROM customer_feedback cf
        LEFT JOIN customers c ON cf.customer_id = c.customer_id
        LEFT JOIN accounts a ON a.customer_id = c.customer_id
        LEFT JOIN feedback_category fc ON cf.category_id = fc.category_id
    ";
    
    $params = [];
    
    // Add search condition if provided
    if (!empty($search)) {
        $query .= " WHERE 
            cf.feedback_id LIKE :search OR
            cf.reference_id LIKE :search OR
            c.first_name LIKE :search OR
            c.last_name LIKE :search OR
            c.email LIKE :search OR
            a.username LIKE :search OR
            cf.feedback_text LIKE :search
        ";
        $params[':search'] = "%$search%";
    }
    
    // Order by most recent first
    $query .= " ORDER BY cf.feedback_date DESC LIMIT 100";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process feedback to extract subject and message
    foreach ($feedback as &$item) {
        $text = $item['feedback_text'];
        $subject = '';
        $message = $text;
        
        // Use category_name from joined table, fallback to parsing if null
        if (empty($item['category_name']) && preg_match('/Category:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
            $item['category_name'] = trim($matches[1]);
        }
        
        // Extract subject
        if (preg_match('/Subject:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
            $subject = trim($matches[1]);
        }
        
        // Extract the actual message (everything after the last newline following Subject or Category)
        if (preg_match('/(?:Category:[^\n]*(?:\nSubject:[^\n]*)?)\n\n(.+)/s', $text, $matches)) {
            $message = trim($matches[1]);
        } elseif (preg_match('/Category:[^\n]*\n\n(.+)/s', $text, $matches)) {
            $message = trim($matches[1]);
        }
        
        $item['category'] = $item['category_name'] ?? 'Uncategorized';
        $item['subject'] = $subject;
        $item['message'] = $message;
        $item['customer_name'] = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
        
        // Clean up - remove category_name to avoid confusion
        unset($item['category_name']);
    }
    
    echo json_encode([
        'success' => true,
        'feedback' => $feedback,
        'total' => count($feedback)
    ]);
    
} catch (Exception $e) {
    error_log("Admin feedback API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch feedback: ' . $e->getMessage()
    ]);
}
