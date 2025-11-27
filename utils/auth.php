<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Validate the current request's authentication
 * @return array|false ['account_id' => int] or false if not authenticated
 */
function validateToken() {
    // Check session exists and has required fields
    if (empty($_SESSION['username']) || !isset($_SESSION['customer_id'])) {
        return false;
    }

    // Map customer_id to account_id using accounts table
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT a.account_id 
            FROM accounts a
            WHERE a.customer_id = :cid
            LIMIT 1
        ");
        $stmt->execute([':cid' => (int)$_SESSION['customer_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row && isset($row['account_id'])) {
            return ['account_id' => (int)$row['account_id']];
        }
    } catch (Exception $e) {
        error_log('validateToken DB error: ' . $e->getMessage());
    }

    return false;
}