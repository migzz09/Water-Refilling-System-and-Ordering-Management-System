<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/connect.php';

// Simple admin endpoint to update the Purchase New Container/s price.
// Accepts POST (application/json or form-data) with either:
// - key = 'purchase_new_price' and value = numeric string
// - or order_type_id and price (for future extension)

// Allow CORS if needed (same-origin expected)
try {
    // Read input (JSON or form)
    $input = null;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
    } else {
        $input = $_POST;
    }

    if (!$input) throw new Exception('No input provided');

    // Prefer direct key/value update
    if (isset($input['key']) && isset($input['value'])) {
        $key = trim($input['key']);
        $value = trim($input['value']);
        if ($key !== 'purchase_new_price') throw new Exception('Unsupported key');
        if (!is_numeric($value)) throw new Exception('Value must be numeric');

        // Ensure settings table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            `k` varchar(100) NOT NULL PRIMARY KEY,
            `v` varchar(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $up = $pdo->prepare("INSERT INTO settings (k, v) VALUES (:k, :v)
            ON DUPLICATE KEY UPDATE v = :v2");
        $up->execute([':k' => $key, ':v' => $value, ':v2' => $value]);

        echo json_encode(['success' => true, 'message' => 'Price updated']);
        exit;
    }

    // For legacy support: order_type_id + price
    if (isset($input['order_type_id']) && isset($input['price'])) {
        $price = $input['price'];
        if (!is_numeric($price)) throw new Exception('Price must be numeric');
        // Only support Purchase New Container/s mapping (order_type_id check optional)
        $stmt = $pdo->prepare('SELECT type_name FROM order_types WHERE order_type_id = ?');
        $stmt->execute([$input['order_type_id']]);
        $ot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ot) throw new Exception('Order type not found');
        if (strcasecmp(trim($ot['type_name']), 'Purchase New Container/s') !== 0) {
            throw new Exception('Only purchase-new type can be updated via this endpoint');
        }
        // Persist to settings
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            `k` varchar(100) NOT NULL PRIMARY KEY,
            `v` varchar(255) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        $up = $pdo->prepare("INSERT INTO settings (k, v) VALUES ('purchase_new_price', :v) ON DUPLICATE KEY UPDATE v = :v2");
        $up->execute([':v' => $price, ':v2' => $price]);
        echo json_encode(['success' => true, 'message' => 'Price updated']);
        exit;
    }

    throw new Exception('Invalid input');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>