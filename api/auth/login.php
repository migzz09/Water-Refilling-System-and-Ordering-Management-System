<?php
/**
 * Login API Endpoint
 * Method: POST
 * Body: { "username": "...", "password": "..." }
 */
session_start();
require_once '../../config/connect.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

// Clear existing session to prevent conflicts
session_unset();
session_destroy();
session_start();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

$errors = [];

// Validation
if (empty($username) || empty($password)) {
    $errors[] = "Username and password are required.";
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $errors
    ]);
    exit;
}

// Authenticate user
try {
    $stmt = $pdo->prepare("
        SELECT a.customer_id, a.username, a.password, a.is_verified
        FROM accounts a
        WHERE a.username = ? AND a.is_verified = 1
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Debug logging (remove after testing)
    error_log("Login attempt for user: " . $username);
    error_log("User found: " . ($user ? 'yes' : 'no'));
    if ($user) {
        error_log("Password length in DB: " . strlen($user['password']));
        error_log("Password starts with: " . substr($user['password'], 0, 10));
        error_log("Is verified: " . $user['is_verified']);
    }

    // Check if user exists and verify password
    $passwordValid = false;
    if ($user) {
        // Try password_verify for hashed passwords
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
        } 
        // Fallback: check if it's an old unhashed password
        elseif ($password === $user['password']) {
            $passwordValid = true;
            // Note: Can't rehash yet due to VARCHAR(50) limit - need to alter table first
        }
    }

    if ($user && $passwordValid) {
        // Login successful
        $_SESSION['customer_id'] = $user['customer_id'];
        $_SESSION['username'] = $user['username'];
        
        // Force session to be written before response
        session_write_close();
        session_start(); // Restart for any further operations
        
        // Debug: Log session info
        error_log("Session set - customer_id: " . $_SESSION['customer_id']);
        error_log("Session ID: " . session_id());
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'customer_id' => $user['customer_id'],
                'username' => $user['username'],
                'session_id' => session_id() // For debugging
            ]
        ]);
    } else {
        // Login failed
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid username, password, or account not verified.',
            'errors' => ['Invalid credentials']
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Login failed: ' . $e->getMessage(),
        'errors' => [$e->getMessage()]
    ]);
}
