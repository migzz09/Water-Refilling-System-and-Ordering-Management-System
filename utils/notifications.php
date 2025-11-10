<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * Insert a notification row
 * @param mysqli $conn
 * @param int $account_id
 * @param string $message
 * @param string|null $reference_id
 * @param string $type
 * @return int|null inserted id
 */
function create_notification($conn, int $account_id, string $message, ?string $reference_id = null, string $type = 'system') {
    $sql = "INSERT INTO notifications (user_id, message, reference_id, notification_type) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    $stmt->bind_param('isss', $account_id, $message, $reference_id, $type);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $insert_id = $stmt->insert_id ?: null;
    $stmt->close();
    return $insert_id;
}
?>