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
        SELECT a.customer_id, a.username, a.password, a.is_verified, 
               COALESCE(a.is_admin, 0) as is_admin
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
        error_log("Is admin value: " . ($user['is_admin'] ?? 'NULL'));
        error_log("Is admin type: " . gettype($user['is_admin'] ?? null));
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
        // Login successful - Set session variables
        $_SESSION['customer_id'] = $user['customer_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = (int)$user['is_admin']; // Cast to integer
        
        // Debug: Log session info BEFORE write
        error_log("=== LOGIN SUCCESS ===");
        error_log("Session ID: " . session_id());
        error_log("Setting customer_id: " . $user['customer_id']);
        error_log("Setting username: " . $user['username']);
        error_log("Setting is_admin: " . (int)$user['is_admin']);
        error_log("Session after set: " . print_r($_SESSION, true));
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'customer_id' => $user['customer_id'],
                'username' => $user['username'],
                'is_admin' => (int)$user['is_admin'], // Return as integer
                'session_id' => session_id() // For debugging
            ]
        ]);
    } else {
        // Try staff table as fallback (staff accounts separate from customer accounts)
        try {
            $sstmt = $pdo->prepare("SELECT staff_id, staff_user, staff_password, staff_role FROM staff WHERE staff_user = ? LIMIT 1");
            $sstmt->execute([$username]);
            $staff = $sstmt->fetch(PDO::FETCH_ASSOC);
            $staffPasswordValid = false;
            if ($staff) {
                // Support hashed passwords and plaintext fallback
                if (password_verify($password, $staff['staff_password'])) {
                    $staffPasswordValid = true;
                } elseif ($password === $staff['staff_password']) {
                    $staffPasswordValid = true;
                }
            }

            if ($staff && $staffPasswordValid) {
                // Successful staff login - set staff-specific session variables
                $_SESSION['staff_id'] = (int)$staff['staff_id'];
                $_SESSION['username'] = $staff['staff_user'];
                $_SESSION['is_admin'] = 0; // Staff are not admin
                $_SESSION['staff_role'] = $staff['staff_role'];

                error_log("=== STAFF LOGIN SUCCESS === Username: {$staff['staff_user']} Role: {$staff['staff_role']}");

                // Add is_admin and staff_role to response for staff login
                echo json_encode([
                    'success' => true,
                    'message' => 'Staff login successful',
                    'data' => [
                        'staff_id' => (int)$staff['staff_id'],
                        'username' => $staff['staff_user'],
                        'is_admin' => 0, // Staff are not admin
                        'staff_role' => $staff['staff_role'],
                        'session_id' => session_id()
                    ]
                ]);
                exit;
            }

            // Still failed
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid username or password.',
                'errors' => ['Invalid credentials']
            ]);
        } catch (PDOException $se) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Login failed: ' . $se->getMessage(),
                'errors' => [$se->getMessage()]
            ]);
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Login failed: ' . $e->getMessage(),
        'errors' => [$e->getMessage()]
    ]);
}
