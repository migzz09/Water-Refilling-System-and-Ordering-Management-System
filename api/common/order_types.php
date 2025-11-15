<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connect.php';

/**
 * Return order types and include a price for Purchase New Container/s.
 * Price is stored in a lightweight `settings` table (created on demand).
 */
try {
    // Ensure settings table exists (idempotent)
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        `k` varchar(100) NOT NULL PRIMARY KEY,
        `v` varchar(255) DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Get purchase new price from settings (key: purchase_new_price)
    $priceStmt = $pdo->prepare("SELECT v FROM settings WHERE k = :k LIMIT 1");
    $priceStmt->execute([':k' => 'purchase_new_price']);
    $row = $priceStmt->fetch(PDO::FETCH_ASSOC);
    $purchasePrice = $row && isset($row['v']) && is_numeric($row['v']) ? (float)$row['v'] : 250.00;

    $stmt = $pdo->query("SELECT order_type_id, type_name FROM order_types ORDER BY order_type_id");
    $orderTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Attach price where applicable
    foreach ($orderTypes as &$ot) {
        if (strcasecmp(trim($ot['type_name']), 'Purchase New Container/s') === 0) {
            $ot['price'] = $purchasePrice;
        } else {
            $ot['price'] = null;
        }
        // keep key uniform naming with purchase_price when applicable
        if (isset($ot['price']) && $ot['price'] !== null) {
            $ot['purchase_price'] = $ot['price'];
        } else {
            $ot['purchase_price'] = null;
        }
    }

    echo json_encode($orderTypes);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>