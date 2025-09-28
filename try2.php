<?php
// Database connection settings
$host = 'localhost';
$port = '4306';
$dbname = 'pbl';
$username = 'root';
$password = 'your_password'; // Replace with your phpMyAdmin password

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submission for new orders
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {
    $customer_id = $_POST['customer_id'];
    $order_type_id = $_POST['order_type_id'];
    $container_id = $_POST['container_id'];
    $quantity = $_POST['quantity'];
    $delivery_date = $_POST['delivery_date'];

    // Calculate subtotal based on container price
    $stmt = $pdo->prepare("SELECT price FROM containers WHERE container_id = ?");
    $stmt->execute([$container_id]);
    $container = $stmt->fetch(PDO::FETCH_ASSOC);
    $subtotal = $container['price'] * $quantity;

    // Insert into orders table (default batch_id = NULL, order_status_id = 1 for Pending)
    $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_type_id, batch_id, order_date, delivery_date, order_status_id, total_amount) VALUES (?, ?, NULL, NOW(), ?, 1, ?)");
    $stmt->execute([$customer_id, $order_type_id, $delivery_date, $subtotal]);

    // Get the inserted order_id
    $order_id = $pdo->lastInsertId();

    // Insert into order_details table
    $stmt = $pdo->prepare("INSERT INTO order_details (order_id, container_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
    $stmt->execute([$order_id, $container_id, $quantity, $subtotal]);

    $success = "Order added successfully!";
}

// Handle order tracking form
$tracking_data = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_order'])) {
    $order_id = $_POST['order_id'];

    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT o.*, c.first_name, c.last_name, c.customer_contact, c.street, c.barangay, c.city, c.province,
               ot.type_name AS order_type, os.status_name AS order_status
        FROM orders o
        JOIN customers c ON o.customer_id = c.customer_id
        JOIN order_types ot ON o.order_type_id = ot.order_type_id
        JOIN order_status os ON o.order_status_id = os.status_id
        WHERE o.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($order) {
        // Fetch order details (containers)
        $stmt = $pdo->prepare("
            SELECT od.quantity, od.subtotal, con.container_type
            FROM order_details od
            JOIN containers con ON od.container_id = con.container_id
            WHERE od.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order['details'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch batch info if assigned
        if ($order['batch_id']) {
            $stmt = $pdo->prepare("
                SELECT b.vehicle, b.notes AS batch_notes, bs.status_name AS batch_status,
                       GROUP_CONCAT(e.first_name, ' ', e.last_name, ' (', be.role, ')') AS employees
                FROM batches b
                JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
                LEFT JOIN batch_employees be ON b.batch_id = be.batch_id
                LEFT JOIN employees e ON be.employee_id = e.employee_id
                WHERE b.batch_id = ?
                GROUP BY b.batch_id
            ");
            $stmt->execute([$order['batch_id']]);
            $order['batch'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch delivery info
            $stmt = $pdo->prepare("
                SELECT d.delivery_date, d.notes AS delivery_notes, ds.status_name AS delivery_status
                FROM deliveries d
                JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
                WHERE d.batch_id = ?
            ");
            $stmt->execute([$order['batch_id']]);
            $order['delivery'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Fetch payment info
        $stmt = $pdo->prepare("
            SELECT p.amount_paid, p.transaction_reference, pm.method_name, ps.status_name AS payment_status
            FROM payments p
            JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
            JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id
            WHERE p.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order['payment'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch feedback
        $stmt = $pdo->prepare("
            SELECT f.rating, f.feedback_text, f.feedback_date
            FROM customer_feedback f
            WHERE f.order_id = ?
        ");
        $stmt->execute([$order_id]);
        $order['feedback'] = $stmt->fetch(PDO::FETCH_ASSOC);

        $tracking_data = $order;
    } else {
        $tracking_error = "Order not found.";
    }
}

// Fetch data for display
// Orders with customer names and order details
$orders = $pdo->query("
    SELECT o.order_id, c.first_name, c.last_name, ot.type_name, os.status_name, o.total_amount, o.delivery_date
    FROM orders o
    JOIN customers c ON o.customer_id = c.customer_id
    JOIN order_types ot ON o.order_type_id = ot.order_type_id
    JOIN order_status os ON o.order_status_id = os.status_id
")->fetchAll(PDO::FETCH_ASSOC);

// Batches with assigned employees
$batches = $pdo->query("
    SELECT b.batch_id, b.vehicle, bs.status_name, GROUP_CONCAT(e.first_name, ' ', e.last_name, ' (', be.role, ')') as employees
    FROM batches b
    JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
    LEFT JOIN batch_employees be ON b.batch_id = be.batch_id
    LEFT JOIN employees e ON be.employee_id = e.employee_id
    GROUP BY b.batch_id
")->fetchAll(PDO::FETCH_ASSOC);

// Deliveries
$deliveries = $pdo->query("
    SELECT d.delivery_id, b.vehicle, ds.status_name, d.delivery_date, d.notes
    FROM deliveries d
    JOIN batches b ON d.batch_id = b.batch_id
    JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
")->fetchAll(PDO::FETCH_ASSOC);

// Customer feedback
$feedbacks = $pdo->query("
    SELECT f.feedback_id, c.first_name, c.last_name, f.rating, f.feedback_text, f.feedback_date
    FROM customer_feedback f
    JOIN customers c ON f.customer_id = c.customer_id
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch customers, containers, and order types for the form
$customers = $pdo->query("SELECT customer_id, CONCAT(first_name, ' ', last_name) as name FROM customers")->fetchAll(PDO::FETCH_ASSOC);
$containers = $pdo->query("SELECT container_id, container_type, price FROM containers")->fetchAll(PDO::FETCH_ASSOC);
$order_types = $pdo->query("SELECT order_type_id, type_name FROM order_types")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Water Refilling Station Management</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: #fff;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
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
        .form-container select, .form-container input {
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
        .success, .error {
            text-align: center;
            margin: 10px 0;
        }
        .success {
            color: green;
        }
        .error {
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
        @media (max-width: 600px) {
            table, .form-container, .tracking-section {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Water Refilling Station Management</h1>

        <!-- Success Message for Adding Order -->
        <?php if (isset($success)): ?>
            <p class="success"><?php echo $success; ?></p>
        <?php endif; ?>

        <!-- Add New Order Form -->
        <div class="form-container">
            <h2>Add New Order</h2>
            <form method="POST">
                <label for="customer_id">Customer</label>
                <select name="customer_id" required>
                    <option value="">Select Customer</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['customer_id']; ?>"><?php echo htmlspecialchars($customer['name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="order_type_id">Order Type</label>
                <select name="order_type_id" required>
                    <option value="">Select Order Type</option>
                    <?php foreach ($order_types as $type): ?>
                        <option value="<?php echo $type['order_type_id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="container_id">Container</label>
                <select name="container_id" required>
                    <option value="">Select Container</option>
                    <?php foreach ($containers as $container): ?>
                        <option value="<?php echo $container['container_id']; ?>">
                            <?php echo htmlspecialchars($container['container_type'] . ' (₱' . $container['price'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="quantity">Quantity</label>
                <input type="number" name="quantity" min="1" required>

                <label for="delivery_date">Delivery Date</label>
                <input type="date" name="delivery_date" required>

                <button type="submit" name="add_order">Add Order</button>
            </form>
        </div>

        <!-- Order Tracking Form -->
        <div class="form-container">
            <h2>Track Order</h2>
            <form method="POST">
                <label for="order_id">Order ID</label>
                <input type="number" name="order_id" min="1" required>
                <button type="submit" name="track_order">Track</button>
            </form>
        </div>

        <!-- Order Tracking Details -->
        <?php if (isset($tracking_error)): ?>
            <p class="error"><?php echo $tracking_error; ?></p>
        <?php elseif ($tracking_data): ?>
            <div class="tracking-section">
                <h2>Order Tracking for Order ID: <?php echo $tracking_data['order_id']; ?></h2>
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
                        <dd><?php echo $detail['quantity'] . ' x ' . htmlspecialchars($detail['container_type']) . ' (Subtotal: ₱' . number_format($detail['subtotal'], 2) . ')'; ?></dd>
                    <?php endforeach; ?>

                    <dt>Total Amount:</dt>
                    <dd>₱<?php echo number_format($tracking_data['total_amount'], 2); ?></dd>

                    <?php if (isset($tracking_data['batch'])): ?>
                        <dt>Batch Info:</dt>
                        <dd>Batch ID: <?php echo $tracking_data['batch_id']; ?></dd>
                        <dd>Vehicle: <?php echo htmlspecialchars($tracking_data['batch']['vehicle']); ?></dd>
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

        <!-- Orders Table -->
        <h2>Orders</h2>
        <table>
            <tr>
                <th>Order ID</th>
                <th>Customer</th>
                <th>Order Type</th>
                <th>Status</th>
                <th>Total Amount</th>
                <th>Delivery Date</th>
            </tr>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?php echo $order['order_id']; ?></td>
                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($order['type_name']); ?></td>
                    <td><?php echo htmlspecialchars($order['status_name']); ?></td>
                    <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                    <td><?php echo $order['delivery_date']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Batches Table -->
        <h2>Batches</h2>
        <table>
            <tr>
                <th>Batch ID</th>
                <th>Vehicle</th>
                <th>Status</th>
                <th>Employees</th>
            </tr>
            <?php foreach ($batches as $batch): ?>
                <tr>
                    <td><?php echo $batch['batch_id']; ?></td>
                    <td><?php echo htmlspecialchars($batch['vehicle']); ?></td>
                    <td><?php echo htmlspecialchars($batch['status_name']); ?></td>
                    <td><?php echo htmlspecialchars($batch['employees'] ?: 'None'); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Deliveries Table -->
        <h2>Deliveries</h2>
        <table>
            <tr>
                <th>Delivery ID</th>
                <th>Vehicle</th>
                <th>Status</th>
                <th>Delivery Date</th>
                <th>Notes</th>
            </tr>
            <?php foreach ($deliveries as $delivery): ?>
                <tr>
                    <td><?php echo $delivery['delivery_id']; ?></td>
                    <td><?php echo htmlspecialchars($delivery['vehicle']); ?></td>
                    <td><?php echo htmlspecialchars($delivery['status_name']); ?></td>
                    <td><?php echo $delivery['delivery_date']; ?></td>
                    <td><?php echo htmlspecialchars($delivery['notes'] ?: 'None'); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Customer Feedback Table -->
        <h2>Customer Feedback</h2>
        <table>
            <tr>
                <th>Feedback ID</th>
                <th>Customer</th>
                <th>Rating</th>
                <th>Feedback</th>
                <th>Date</th>
            </tr>
            <?php foreach ($feedbacks as $feedback): ?>
                <tr>
                    <td><?php echo $feedback['feedback_id']; ?></td>
                    <td><?php echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']); ?></td>
                    <td><?php echo $feedback['rating']; ?>/5</td>
                    <td><?php echo htmlspecialchars($feedback['feedback_text'] ?: 'None'); ?></td>
                    <td><?php echo $feedback['feedback_date']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>