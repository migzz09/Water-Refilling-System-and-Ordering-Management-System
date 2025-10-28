<?php
session_start();
require_once 'connect.php';

$ref = $_GET['ref'] ?? '';
if (!$ref || !isset($_SESSION['pending_payment'])) {
    die("<h2>Invalid or missing payment reference.</h2>");
}

$payment = $_SESSION['pending_payment'];
$customer_id = $_SESSION['customer_id'];
$cart = $payment['cart'];
$total_amount = $payment['total_amount'];

// Fetch customer info
$stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, customer_contact, street, barangay, city, province FROM customers WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle “Confirm Order” click
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    try {
        $pdo->beginTransaction();

        // Create order
        $stmt = $pdo->prepare("
            INSERT INTO orders (reference_id, customer_id, order_type_id, order_status_id, total_amount, order_date)
            VALUES (?, ?, 1, 2, ?, NOW())
        ");
        $stmt->execute([$ref, $customer_id, $total_amount]);

        // Order details
        foreach ($cart as $item) {
            $subtotal = $item['price'] * $item['quantity'];
            $stmt = $pdo->prepare("
                INSERT INTO order_details (reference_id, container_id, water_type_id, order_type_id, quantity, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ref, $item['id'], $item['water_type_id'], $item['order_type_id'], $item['quantity'], $subtotal]);
        }

        // Payment record
        $stmt = $pdo->prepare("
            INSERT INTO payments (reference_id, payment_method_id, payment_status_id, amount_paid)
            VALUES (?, 2, 2, ?)
        ");
        $stmt->execute([$ref, $total_amount]);

        $pdo->commit();

        unset($_SESSION['cart']);
        unset($_SESSION['pending_payment']);

        echo "<h2>✅ Order Confirmed!</h2>
              <p>Your order <strong>$ref</strong> has been successfully recorded.</p>
              <p><a href='index.php'>Return to Home</a></p>";
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<h3>Error:</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment Successful - Confirm Order</title>
<style>
body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #f0f6fa;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}
.modal {
    background: white;
    border-radius: 15px;
    padding: 2rem;
    max-width: 500px;
    width: 90%;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
h2 {
    color: #008CBA;
    text-align: center;
    margin-bottom: 1rem;
}
.cart-item {
    display: flex;
    justify-content: space-between;
    border-bottom: 1px solid #eee;
    padding: .5rem 0;
}
.cart-total {
    text-align: right;
    font-weight: bold;
    color: #008CBA;
    margin-top: 1rem;
}
.warning {
    color: #d32f2f;
    text-align: center;
    font-weight: 500;
    margin: 1rem 0;
}
button {
    background: linear-gradient(90deg,#008CBA,#00aaff);
    color: white;
    border: none;
    padding: 1rem;
    border-radius: 8px;
    font-weight: bold;
    width: 100%;
    font-size: 1rem;
    cursor: pointer;
    transition: transform .3s;
}
button:hover {
    transform: translateY(-2px);
}
</style>
</head>
<body>
<div class="modal">
    <h2>Your Order Summary</h2>
    <?php foreach ($cart as $item): ?>
        <div class="cart-item">
            <span><?= htmlspecialchars($item['quantity'] . ' x ' . $item['name'] . ' (' . $item['water_type_name'] . ', ' . $item['order_type_name'] . ')') ?></span>
            <span>₱<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
        </div>
    <?php endforeach; ?>
    <div class="cart-total">Total: ₱<?= number_format($total_amount, 2) ?></div>

    <p><strong>Reference ID:</strong> <?= htmlspecialchars($ref) ?></p>
    <p><strong>Customer Name:</strong> <?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></p>
    <p><strong>Contact Number:</strong> <?= htmlspecialchars($customer['customer_contact']) ?></p>
    <p><strong>Address:</strong> <?= htmlspecialchars($customer['street'] . ', ' . $customer['barangay'] . ', ' . $customer['city'] . ', ' . $customer['province']) ?></p>
    <p><strong>Order Date:</strong> <?= date('Y-m-d H:i:s') ?></p>
    <p class="warning">Please take a screenshot of this receipt for your records!</p>

    <form method="POST">
        <button type="submit" name="confirm_order">Confirm Order</button>
    </form>
</div>
</body>
</html>
