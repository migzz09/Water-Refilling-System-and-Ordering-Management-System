<?php
// Staff management API for admin panel
ob_start();
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../../config/connect.php'; // provides $pdo

    // Authorization: require admin
    if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin privileges required']);
        exit;
    }

    // Handle POST actions (create/delete/update)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $input['action'] ?? '';

        if ($action === 'create') {
            $user = trim($input['staff_user'] ?? '');
            $pass = $input['staff_password'] ?? '';
            $role = $input['staff_role'] ?? '';
            $firstName = trim($input['first_name'] ?? '');
            $lastName = trim($input['last_name'] ?? '');

            if ($user === '' || $pass === '' || $role === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Username, password, and role are required']);
                exit;
            }

            // Hash password for better safety
            $hashed = password_hash($pass, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('INSERT INTO staff (staff_user, staff_password, staff_role, first_name, last_name) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$user, $hashed, $role, $firstName, $lastName]);

            echo json_encode(['success' => true, 'message' => 'Staff created']);
            exit;
        }

        if ($action === 'update') {
            $id = intval($input['staff_id'] ?? 0);
            $firstName = trim($input['first_name'] ?? '');
            $lastName = trim($input['last_name'] ?? '');
            $role = $input['staff_role'] ?? '';
            
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid id']);
                exit;
            }
            
            $stmt = $pdo->prepare('UPDATE staff SET first_name = ?, last_name = ?, staff_role = ? WHERE staff_id = ?');
            $stmt->execute([$firstName, $lastName, $role, $id]);
            echo json_encode(['success' => true, 'message' => 'Staff updated']);
            exit;
        }

        if ($action === 'delete') {
            $id = intval($input['staff_id'] ?? 0);
            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid id']);
                exit;
            }
            $stmt = $pdo->prepare('DELETE FROM staff WHERE staff_id = ?');
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Staff deleted']);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }

    // GET - list staff with names
    $stmt = $pdo->query('SELECT staff_id, staff_user, staff_role, first_name, last_name FROM staff ORDER BY staff_id DESC');
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'staff' => $staff ?: []]);
    exit;

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error', 'error' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error', 'error' => $e->getMessage()]);
}

ob_end_flush();
?>