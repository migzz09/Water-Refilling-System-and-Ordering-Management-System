<?php
// Notifications API — uses PDO $pdo from [connect.php](http://_vscodecontentref_/0) and validateToken() from [auth.php](http://_vscodecontentref_/1)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/connect.php';
require_once __DIR__ . '/../utils/auth.php';

header('Content-Type: application/json');

if (!function_exists('validateToken')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: validateToken() missing']);
    exit;
}

$user = validateToken();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$account_id = (int)$user['account_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // fetch notifications (latest 50) for this account
        $stmt = $pdo->prepare("
            SELECT notification_id, user_id, message, reference_id, notification_type, is_read, created_at
            FROM notifications
            WHERE user_id = :uid
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([':uid' => $account_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cnt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = :uid AND is_read = 0");
        $cnt->execute([':uid' => $account_id]);
        $unread = (int)$cnt->fetchColumn();

        echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => $unread]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $action = $data['action'] ?? '';

        if ($action === 'mark_read') {
            $nid = (int)($data['notification_id'] ?? 0);
            if ($nid > 0) {
                $u = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = :nid AND user_id = :uid");
                $u->execute([':nid' => $nid, ':uid' => $account_id]);
            }
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'mark_all_read') {
            $u = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid");
            $u->execute([':uid' => $account_id]);
            echo json_encode(['success' => true]);
            exit;
        }

        if ($action === 'mark_seen') {
            // optional behavior — mark notifications older than 30s as seen to avoid race
            $u = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND created_at < (NOW() - INTERVAL 30 SECOND)");
            $u->execute([':uid' => $account_id]);
            echo json_encode(['success' => true]);
            exit;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
} catch (Throwable $e) {
    error_log('notifications.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
?>