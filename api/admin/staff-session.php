<?php
// Returns role information for current session (admin or staff)
header('Content-Type: application/json');
session_start();
try {
    require_once __DIR__ . '/../../config/connect.php';

    // Try to detect staff by username in session first.
    $username = $_SESSION['username'] ?? null;
    if ($username) {
        $stmt = $pdo->prepare('SELECT staff_id, staff_role, staff_user FROM staff WHERE staff_user = ? LIMIT 1');
        $stmt->execute([$username]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            // Return staff_user as well so frontend can display the username
            echo json_encode(['success' => true, 'role' => $r['staff_role'], 'staff_id' => $r['staff_id'], 'staff_user' => $r['staff_user']]);
            exit;
        }
    }

    // If session indicates admin (and no matching staff username found), return Admin
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        echo json_encode(['success' => true, 'role' => 'Admin']);
        exit;
    }

    // Not staff or admin
    echo json_encode(['success' => true, 'role' => null]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error', 'error' => $e->getMessage()]);
}
?>