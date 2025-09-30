<?php
session_start();
require_once 'connect.php';

// Handle order tracking form
$tracking_data = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_order'])) {
    $reference_id = filter_input(INPUT_POST, 'reference_id', FILTER_SANITIZE_STRING);

    if ($reference_id) {
        $stmt = $pdo->prepare("
            SELECT o.*, c.first_name, c.last_name, c.customer_contact, c.street, c.barangay, c.city, c.province,
                   ot.type_name AS order_type, os.status_name AS order_status
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            JOIN order_types ot ON o.order_type_id = ot.order_type_id
            JOIN order_status os ON o.order_status_id = os.status_id
            WHERE o.reference_id = ?
        ");
        $stmt->execute([$reference_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $stmt = $pdo->prepare("
                SELECT od.quantity, od.subtotal, con.container_type
                FROM order_details od
                JOIN containers con ON od.container_id = con.container_id
                WHERE od.reference_id = ?
            ");
            $stmt->execute([$reference_id]);
            $order['details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($order['batch_id']) {
                $stmt = $pdo->prepare("
                    SELECT b.vehicle, b.vehicle_type, b.notes AS batch_notes, bs.status_name AS batch_status,
                           GROUP_CONCAT(e.first_name, ' ', e.last_name, ' (', COALESCE(e.role, 'None'), ')') AS employees
                    FROM batches b
                    JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
                    LEFT JOIN batch_employees be ON b.batch_id = be.batch_id
                    LEFT JOIN employees e ON be.employee_id = e.employee_id
                    WHERE b.batch_id = ?
                    GROUP BY b.batch_id
                ");
                $stmt->execute([$order['batch_id']]);
                $order['batch'] = $stmt->fetch(PDO::FETCH_ASSOC);

                $stmt = $pdo->prepare("
                    SELECT d.delivery_date, d.notes AS delivery_notes, ds.status_name AS delivery_status
                    FROM deliveries d
                    JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
                    WHERE d.batch_id = ?
                ");
                $stmt->execute([$order['batch_id']]);
                $order['delivery'] = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            $stmt = $pdo->prepare("
                SELECT p.amount_paid, p.transaction_reference, pm.method_name, ps.status_name AS payment_status
                FROM payments p
                JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
                JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id
                WHERE p.reference_id = ?
            ");
            $stmt->execute([$reference_id]);
            $order['payment'] = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                SELECT f.rating, f.feedback_text, f.feedback_date
                FROM customer_feedback f
                WHERE f.reference_id = ?
            ");
            $stmt->execute([$reference_id]);
            $order['feedback'] = $stmt->fetch(PDO::FETCH_ASSOC);

            $tracking_data = $order;
        } else {
            $tracking_error = "Order not found.";
        }
    } else {
        $tracking_error = "Invalid reference ID.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track an Order</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h1, h2 {
            color: #333;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .form-container {
            background-color: #fff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-container label {
            display: block;
            margin: 10px 0 5px;
        }
        .form-container input {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-container button {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-container button:hover {
            background-color: #0056b3;
        }
        .error {
            text-align: center;
            margin: 10px 0;
            color: red;
        }
        .tracking-section {
            background-color: #fff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .tracking-section dl {
            margin: 0;
        }
        .tracking-section dt {
            font-weight: bold;
            margin-top: 10px;
        }
        .tracking-section dd {
            margin-left: 20px;
        }
        .back-button {
            display: block;
            text-align: center;
            margin: 20px 0;
        }
        .back-button a {
            color: #007bff;
            text-decoration: none;
        }
        .back-button a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Track an Order</h1>

        <!-- Order Tracking Form -->
        <div class="form-container">
            <h2>Track Order</h2>
            <form method="POST" action="">
                <label for="reference_id">Reference ID</label>
                <input type="text" name="reference_id" required>
                <button type="submit" name="track_order">Track</button>
            </form>
        </div>

        <!-- Order Tracking Details -->
        <?php if (isset($tracking_error)): ?>
            <p class="error"><?php echo htmlspecialchars($tracking_error); ?></p>
        <?php elseif ($tracking_data): ?>
            <div class="tracking-section">
                <h2>Order Tracking for Reference ID: <?php echo htmlspecialchars($tracking_data['reference_id']); ?></h2>
                <dl>
                    <dt>Customer:</dt>
                    <dd><?php echo htmlspecialchars($tracking_data['first_name'] . ' ' . $tracking_data['last_name']); ?></dd>
                    <dd>Contact: <?php echo htmlspecialchars($tracking_data['customer_contact']); ?></dd>
                    <dd>Address: <?php echo htmlspecialchars($tracking_data['street'] . ', ' . $tracking_data['barangay'] . ', ' . $tracking_data['city'] . ', ' . $tracking_data['province']); ?></dd>

                    <dt>Order Date:</dt>
                    <dd><?php echo $tracking_data['order_date']; ?></dd>

                    <dt>Delivery Date:</dt>
                    <dd><?php echo $tracking_data['delivery_date']; ?></dd>

                    <dt>Order Type:</dt>
                    <dd><?php echo htmlspecialchars($tracking_data['order_type']); ?></dd>

                    <dt>Order Status:</dt>
                    <dd><?php echo htmlspecialchars($tracking_data['order_status']); ?></dd>

                    <dt>Items Ordered:</dt>
                    <?php foreach ($tracking_data['details'] as $detail): ?>
                        <dd><?php echo htmlspecialchars($detail['quantity'] . ' x ' . $detail['container_type'] . ' (Subtotal: ₱' . number_format($detail['subtotal'], 2) . ')'); ?></dd>
                    <?php endforeach; ?>

                    <dt>Total Amount:</dt>
                    <dd>₱<?php echo number_format($tracking_data['total_amount'], 2); ?></dd>

                    <?php if (isset($tracking_data['batch'])): ?>
                        <dt>Batch Info:</dt>
                        <dd>Batch ID: <?php echo $tracking_data['batch_id']; ?></dd>
                        <dd>Vehicle: <?php echo htmlspecialchars($tracking_data['batch']['vehicle']); ?></dd>
                        <dd>Vehicle Type: <?php echo htmlspecialchars($tracking_data['batch']['vehicle_type']); ?></dd>
                        <dd>Batch Status: <?php echo htmlspecialchars($tracking_data['batch']['batch_status']); ?></dd>
                        <dd>Employees: <?php echo htmlspecialchars($tracking_data['batch']['employees'] ?: 'None'); ?></dd>
                        <dd>Batch Notes: <?php echo htmlspecialchars($tracking_data['batch']['batch_notes'] ?: 'None'); ?></dd>
                    <?php else: ?>
                        <dt>Batch Info:</dt>
                        <dd>Not assigned yet.</dd>
                    <?php endif; ?>

                    <?php if (isset($tracking_data['delivery'])): ?>
                        <dt>Delivery Info:</dt>
                        <dd>Delivery Status: <?php echo htmlspecialchars($tracking_data['delivery']['delivery_status']); ?></dd>
                        <dd>Delivery Date: <?php echo $tracking_data['delivery']['delivery_date']; ?></dd>
                        <dd>Delivery Notes: <?php echo htmlspecialchars($tracking_data['delivery']['delivery_notes'] ?: 'None'); ?></dd>
                    <?php else: ?>
                        <dt>Delivery Info:</dt>
                        <dd>Not available yet.</dd>
                    <?php endif; ?>

                    <?php if (isset($tracking_data['payment'])): ?>
                        <dt>Payment Info:</dt>
                        <dd>Payment Status: <?php echo htmlspecialchars($tracking_data['payment']['payment_status']); ?></dd>
                        <dd>Method: <?php echo htmlspecialchars($tracking_data['payment']['method_name']); ?></dd>
                        <dd>Amount Paid: ₱<?php echo number_format($tracking_data['payment']['amount_paid'], 2); ?></dd>
                        <dd>Transaction Reference: <?php echo htmlspecialchars($tracking_data['payment']['transaction_reference'] ?: 'None'); ?></dd>
                    <?php else: ?>
                        <dt>Payment Info:</dt>
                        <dd>Not processed yet.</dd>
                    <?php endif; ?>

                    <?php if (isset($tracking_data['feedback'])): ?>
                        <dt>Customer Feedback:</dt>
                        <dd>Rating: <?php echo $tracking_data['feedback']['rating']; ?>/5</dd>
                        <dd>Feedback: <?php echo htmlspecialchars($tracking_data['feedback']['feedback_text'] ?: 'None'); ?></dd>
                        <dd>Date: <?php echo $tracking_data['feedback']['feedback_date']; ?></dd>
                    <?php else: ?>
                        <dt>Customer Feedback:</dt>
                        <dd>Not provided yet.</dd>
                    <?php endif; ?>
                </dl>
            </div>
        <?php endif; ?>

        <div class="back-button">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>