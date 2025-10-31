<?php
header('Content-Type: application/json');
session_start();

// Ensure addresses array exists in session
if (!isset($_SESSION['addresses'])) {
    $_SESSION['addresses'] = [];
}

$addresses = array_values($_SESSION['addresses']);

// If there are no session-stored addresses but the user is logged in,
// attempt to fall back to the customers table (registered address).
// Try fallback by customer_id first. If not available (user just registered but not logged in),
// try using registered_email stored in session by the registration endpoint.
if (empty($addresses) && (isset($_SESSION['customer_id']) || isset($_SESSION['registered_email']))) {
    try {
        // Reuse existing DB connection file used elsewhere in the API
        require_once __DIR__ . '/../../config/connect.php';
        if (isset($_SESSION['customer_id'])) {
            $stmt = $pdo->prepare('SELECT first_name, middle_name, last_name, customer_contact, street, barangay, city, province FROM customers WHERE customer_id = ? LIMIT 1');
            $stmt->execute([$_SESSION['customer_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // user may have just registered; registration stores registered_email in session
            $stmt = $pdo->prepare('SELECT first_name, middle_name, last_name, customer_contact, street, barangay, city, province FROM customers WHERE email = ? LIMIT 1');
            $stmt->execute([$_SESSION['registered_email']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($row) {
            $addresses[] = [
                'id' => 0,
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'customer_contact' => $row['customer_contact'],
                'street' => $row['street'],
                'barangay' => $row['barangay'],
                'city' => $row['city'],
                'province' => $row['province']
            ];
        }
    } catch (Exception $e) {
        // If DB connection fails, log and continue returning session addresses (possibly empty)
        error_log('addresses.php DB fallback error: ' . $e->getMessage());
    }
}

echo json_encode(['addresses' => $addresses]);
?>
