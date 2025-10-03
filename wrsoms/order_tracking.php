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
    <title>Track an Order - WaterWorld</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f9fbfc;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }

        header {
            background: #ffffffcc;
            backdrop-filter: blur(10px);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e5e5;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #008CBA;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
        }

        nav ul li a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            position: relative;
            padding-bottom: 4px;
            transition: color 0.3s;
        }

        nav ul li a::after {
            content: "";
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: #008CBA;
            transition: width 0.3s;
        }

        nav ul li a:hover {
            color: #008CBA;
        }

        nav ul li a:hover::after {
            width: 100%;
        }

        section {
            opacity: 0;
            transform: translateY(30px);
            transition: all 1s ease;
        }

        section.show {
            opacity: 1;
            transform: translateY(0);
        }

        .tracking {
            padding: 4rem 5%;
            text-align: center;
        }

        .tracking h1 {
            font-size: 2.2rem;
            margin-bottom: 1rem;
            color: #008CBA;
            animation: slideDown 1.5s ease forwards;
        }

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            max-width: 500px;
            margin: 2rem auto;
        }

        .form-container label {
            display: block;
            margin: 10px 0 5px;
            font-weight: 500;
            color: #333;
        }

        .form-container input {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-container button {
            background: #008CBA;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
        }

        .form-container button:hover {
            background: #005f80;
            transform: scale(1.05);
        }

        .error {
            color: #d32f2f;
            margin: 1rem 0;
            font-weight: 500;
            text-align: center;
        }

        .tracking-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            max-width: 700px;
            margin: 2rem auto;
            text-align: left;
        }

        .tracking-section h2 {
            color: #008CBA;
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }

        .tracking-section dl {
            margin: 0;
        }

        .tracking-section dt {
            font-weight: bold;
            margin-top: 1rem;
            color: #333;
        }

        .tracking-section dd {
            margin-left: 20px;
            color: #555;
        }

        .back-button {
            text-align: center;
            margin: 2rem 0;
        }

        .back-button a {
            color: #008CBA;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .back-button a:hover {
            color: #005f80;
            text-decoration: underline;
        }

        footer {
            background: #008CBA;
            color: white;
            text-align: center;
            padding: 2rem 5%;
            margin-top: 2rem;
        }

        footer .socials {
            margin: 1rem 0;
        }

        footer .socials a {
            margin: 0 10px;
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            transition: color 0.3s;
        }

        footer .socials a:hover {
            color: #cceeff;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">WaterWorld</div>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="order_placement.php">Order</a></li>
                <li><a href="order_tracking.php">Track</a></li>
                <li><a href="#">Contact</a></li>
            </ul>
        </nav>
    </header>

    <section class="tracking show">
        <h1>Track an Order</h1>

        <div class="form-container">
            <form method="POST" action="">
                <label for="reference_id">Reference ID</label>
                <input type="text" name="reference_id" required>
                <button type="submit" name="track_order">Track</button>
            </form>
        </div>

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
    </section>

    <footer>
        <p>&copy; 2025 WaterWorld Water Station. All rights reserved.</p>
        <div class="socials">
            <a href="#">Facebook</a>
            <a href="#">Twitter</a>
            <a href="#">Instagram</a>
        </div>
    </footer>

    <script>
        const sections = document.querySelectorAll("section");

        const revealOnScroll = () => {
            const triggerBottom = window.innerHeight * 0.85;

            sections.forEach(section => {
                const sectionTop = section.getBoundingClientRect().top;

                if (sectionTop < triggerBottom) {
                    section.classList.add("show");
                }
            });
        };

        window.addEventListener("scroll", revealOnScroll);
        revealOnScroll();
    </script>
</body>
</html>