<?php
session_start();
require_once 'connect.php';

// Handle CRUD for payment_status, order_status, delivery_status, and order updates
$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Payment Status CRUD
    if (isset($_POST['add_payment_status'])) {
        $status_name = filter_input(INPUT_POST, 'status_name', FILTER_SANITIZE_STRING);
        if (empty($status_name)) {
            $errors[] = "Payment status name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO payment_status (status_name) VALUES (?)");
                $stmt->execute([$status_name]);
                $success = "Payment status added successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error adding payment status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_payment_status'])) {
        $payment_status_id = filter_input(INPUT_POST, 'payment_status_id', FILTER_VALIDATE_INT);
        $status_name = filter_input(INPUT_POST, 'status_name', FILTER_SANITIZE_STRING);
        if (!$payment_status_id || empty($status_name)) {
            $errors[] = "Invalid payment status ID or name.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE payment_status SET status_name = ? WHERE payment_status_id = ?");
                $stmt->execute([$status_name, $payment_status_id]);
                $success = "Payment status updated successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error updating payment status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_payment_status'])) {
        $payment_status_id = filter_input(INPUT_POST, 'payment_status_id', FILTER_VALIDATE_INT);
        if (!$payment_status_id) {
            $errors[] = "Invalid payment status ID.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM payment_status WHERE payment_status_id = ?");
                $stmt->execute([$payment_status_id]);
                $success = "Payment status deleted successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error deleting payment status: " . $e->getMessage();
            }
        }
    }

    // Order Status CRUD
    elseif (isset($_POST['add_order_status'])) {
        $status_name = filter_input(INPUT_POST, 'status_name', FILTER_SANITIZE_STRING);
        if (empty($status_name)) {
            $errors[] = "Order status name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO order_status (status_name) VALUES (?)");
                $stmt->execute([$status_name]);
                $success = "Order status added successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error adding order status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_order_status'])) {
        $status_id = filter_input(INPUT_POST, 'status_id', FILTER_VALIDATE_INT);
        $status_name = filter_input(INPUT_POST, 'status_name', FILTER_SANITIZE_STRING);
        if (!$status_id || empty($status_name)) {
            $errors[] = "Invalid order status ID or name.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE order_status SET status_name = ? WHERE status_id = ?");
                $stmt->execute([$status_name, $status_id]);
                $success = "Order status updated successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error updating order status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_order_status'])) {
        $status_id = filter_input(INPUT_POST, 'status_id', FILTER_VALIDATE_INT);
        if (!$status_id) {
            $errors[] = "Invalid order status ID.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM order_status WHERE status_id = ?");
                $stmt->execute([$status_id]);
                $success = "Order status deleted successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error deleting order status: " . $e->getMessage();
            }
        }
    }

    // Delivery Status CRUD
    elseif (isset($_POST['add_delivery_status'])) {
        $status_name = filter_input(INPUT_POST, 'status_name', FILTER_SANITIZE_STRING);
        if (empty($status_name)) {
            $errors[] = "Delivery status name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO delivery_status (status_name) VALUES (?)");
                $stmt->execute([$status_name]);
                $success = "Delivery status added successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error adding delivery status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_delivery_status'])) {
        $delivery_status_id = filter_input(INPUT_POST, 'delivery_status_id', FILTER_VALIDATE_INT);
        $status_name = filter_input(INPUT_POST, 'status_name', FILTER_SANITIZE_STRING);
        if (!$delivery_status_id || empty($status_name)) {
            $errors[] = "Invalid delivery status ID or name.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE delivery_status SET status_name = ? WHERE delivery_status_id = ?");
                $stmt->execute([$status_name, $delivery_status_id]);
                $success = "Delivery status updated successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error updating delivery status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_delivery_status'])) {
        $delivery_status_id = filter_input(INPUT_POST, 'delivery_status_id', FILTER_VALIDATE_INT);
        if (!$delivery_status_id) {
            $errors[] = "Invalid delivery status ID.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM delivery_status WHERE delivery_status_id = ?");
                $stmt->execute([$delivery_status_id]);
                $success = "Delivery status deleted successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error deleting delivery status: " . $e->getMessage();
            }
        }
    }

    // Update Order Payment and Delivery Status
    elseif (isset($_POST['update_order'])) {
        $reference_id = filter_input(INPUT_POST, 'reference_id', FILTER_SANITIZE_STRING);
        $payment_status_id = filter_input(INPUT_POST, 'payment_status_id', FILTER_VALIDATE_INT);
        $delivery_status_id = filter_input(INPUT_POST, 'delivery_status_id', FILTER_VALIDATE_INT);
        $order_status_id = filter_input(INPUT_POST, 'order_status_id', FILTER_VALIDATE_INT);

        if (!$reference_id || !$payment_status_id || !$delivery_status_id || !$order_status_id) {
            $errors[] = "Invalid order reference ID, payment status, delivery status, or order status.";
        } else {
            try {
                // Update order status
                $stmt = $pdo->prepare("UPDATE orders SET order_status_id = ? WHERE reference_id = ?");
                $stmt->execute([$order_status_id, $reference_id]);

                // Update or insert payment
                $stmt = $pdo->prepare("SELECT payment_id FROM payments WHERE reference_id = ?");
                $stmt->execute([$reference_id]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($payment) {
                    $stmt = $pdo->prepare("UPDATE payments SET payment_status_id = ?, payment_date = CURRENT_TIMESTAMP WHERE reference_id = ?");
                    $stmt->execute([$payment_status_id, $reference_id]);
                } else {
                    $stmt = $pdo->prepare("SELECT total_amount, batch_id FROM orders WHERE reference_id = ?");
                    $stmt->execute([$reference_id]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    $stmt = $pdo->prepare("INSERT INTO payments (reference_id, payment_method_id, payment_status_id, amount_paid) VALUES (?, 1, ?, ?)");
                    $stmt->execute([$reference_id, $payment_status_id, $order['total_amount']]);
                }

                // Update or insert delivery
                $stmt = $pdo->prepare("SELECT delivery_id FROM deliveries WHERE batch_id = (SELECT batch_id FROM orders WHERE reference_id = ?)");
                $stmt->execute([$reference_id]);
                $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($delivery) {
                    $stmt = $pdo->prepare("UPDATE deliveries SET delivery_status_id = ? WHERE delivery_id = ?");
                    $stmt->execute([$delivery_status_id, $delivery['delivery_id']]);
                } else {
                    $stmt = $pdo->prepare("SELECT batch_id FROM orders WHERE reference_id = ?");
                    $stmt->execute([$reference_id]);
                    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($batch['batch_id']) {
                        $stmt = $pdo->prepare("INSERT INTO deliveries (batch_id, delivery_status_id, delivery_date, notes) VALUES (?, ?, CURRENT_TIMESTAMP, 'Auto-created for order update')");
                        $stmt->execute([$batch['batch_id'], $delivery_status_id]);
                    }
                }

                $success = "Order updated successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error updating order: " . $e->getMessage();
            }
        }
    }
}

// Fetch data for display
$payment_statuses = $pdo->query("SELECT * FROM payment_status")->fetchAll(PDO::FETCH_ASSOC);
$order_statuses = $pdo->query("SELECT * FROM order_status")->fetchAll(PDO::FETCH_ASSOC);
$delivery_statuses = $pdo->query("SELECT * FROM delivery_status")->fetchAll(PDO::FETCH_ASSOC);

// Fetch orders with related data
$orders = $pdo->query("
    SELECT 
        o.reference_id,
        o.order_date,
        o.delivery_date,
        o.total_amount,
        os.status_name AS order_status,
        p.payment_status_id,
        ps.status_name AS payment_status,
        d.delivery_status_id,
        ds.status_name AS delivery_status,
        b.batch_id,
        b.vehicle,
        b.vehicle_type,
        bs.status_name AS batch_status,
        CONCAT(c.first_name, ' ', COALESCE(c.middle_name, ''), ' ', c.last_name) AS customer_name,
        c.city,
        c.barangay,
        od.quantity,
        cont.container_type,
        od.subtotal,
        GROUP_CONCAT(CONCAT(e.first_name, ' ', e.last_name, ' (', COALESCE(e.role, 'N/A'), ')') SEPARATOR ', ') AS assigned_employees
    FROM 
        orders o
    LEFT JOIN 
        order_status os ON o.order_status_id = os.status_id
    LEFT JOIN 
        payments p ON o.reference_id = p.reference_id
    LEFT JOIN 
        payment_status ps ON p.payment_status_id = ps.payment_status_id
    LEFT JOIN 
        batches b ON o.batch_id = b.batch_id
    LEFT JOIN 
        deliveries d ON b.batch_id = d.batch_id
    LEFT JOIN 
        delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
    LEFT JOIN 
        batch_status bs ON b.batch_status_id = bs.batch_status_id
    LEFT JOIN 
        customers c ON o.customer_id = c.customer_id
    LEFT JOIN 
        order_details od ON o.reference_id = od.reference_id
    LEFT JOIN 
        containers cont ON od.container_id = cont.container_id
    LEFT JOIN 
        batch_employees be ON b.batch_id = be.batch_id
    LEFT JOIN 
        employees e ON be.employee_id = e.employee_id
    GROUP BY 
        o.reference_id, o.order_date, o.delivery_date, o.total_amount,
        os.status_name, p.payment_status_id, ps.status_name,
        d.delivery_status_id, ds.status_name,
        b.batch_id, b.vehicle, b.vehicle_type, bs.status_name,
        c.customer_id, od.order_detail_id
    ORDER BY 
        o.order_date DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h1, h2, h3 {
            color: #333;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .form-container, .table-container {
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
        .form-container input, .form-container select {
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .action-buttons form {
            display: inline;
        }
        .action-buttons button {
            margin: 0 5px;
            padding: 5px 10px;
        }
        .back-button {
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
        <h1>Admin Panel</h1>

        <!-- Success or Error Messages -->
        <?php if (isset($success)): ?>
            <p class="success"><?php echo htmlspecialchars($success); ?></p>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Manage Orders -->
        <div class="table-container">
            <h2>Manage Orders</h2>
            <table>
                <tr>
                    <th>Order Ref</th>
                    <th>Order Date</th>
                    <th>Delivery Date</th>
                    <th>Total Amount</th>
                    <th>Order Status</th>
                    <th>Payment Status</th>
                    <th>Delivery Status</th>
                    <th>Batch ID</th>
                    <th>Vehicle</th>
                    <th>Vehicle Type</th>
                    <th>Batch Status</th>
                    <th>Customer</th>
                    <th>City</th>
                    <th>Barangay</th>
                    <th>Quantity</th>
                    <th>Container</th>
                    <th>Subtotal</th>
                    <th>Employees</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($order['reference_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['order_date'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['delivery_date'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['total_amount'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['order_status'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['payment_status'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['delivery_status'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['batch_id'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['vehicle'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['vehicle_type'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['batch_status'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['city'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['barangay'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['quantity'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['container_type'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['subtotal'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($order['assigned_employees'] ?? 'N/A'); ?></td>
                        <td class="action-buttons">
                            <form method="POST" action="">
                                <input type="hidden" name="reference_id" value="<?php echo $order['reference_id']; ?>">
                                <select name="order_status_id">
                                    <?php foreach ($order_statuses as $status): ?>
                                        <option value="<?php echo $status['status_id']; ?>" <?php echo ($status['status_id'] == ($order['order_status_id'] ?? '')) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status['status_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="payment_status_id">
                                    <?php foreach ($payment_statuses as $status): ?>
                                        <option value="<?php echo $status['payment_status_id']; ?>" <?php echo ($status['payment_status_id'] == ($order['payment_status_id'] ?? '')) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status['status_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="delivery_status_id">
                                    <?php foreach ($delivery_statuses as $status): ?>
                                        <option value="<?php echo $status['delivery_status_id']; ?>" <?php echo ($status['delivery_status_id'] == ($order['delivery_status_id'] ?? '')) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status['status_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_order">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Manage Delivery Status -->
        <div class="form-container">
            <h2>Manage Delivery Status</h2>
            <h3>Add Delivery Status</h3>
            <form method="POST" action="">
                <label for="status_name">Status Name</label>
                <input type="text" name="status_name" required>
                <button type="submit" name="add_delivery_status">Add Delivery Status</button>
            </form>

            <h3>Update/Delete Delivery Status</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Status Name</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($delivery_statuses as $status): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($status['delivery_status_id']); ?></td>
                        <td><?php echo htmlspecialchars($status['status_name']); ?></td>
                        <td class="action-buttons">
                            <form method="POST" action="">
                                <input type="hidden" name="delivery_status_id" value="<?php echo $status['delivery_status_id']; ?>">
                                <input type="text" name="status_name" value="<?php echo htmlspecialchars($status['status_name']); ?>" required>
                                <button type="submit" name="update_delivery_status">Update</button>
                                <button type="submit" name="delete_delivery_status" onclick="return confirm('Are you sure you want to delete this status?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Manage Payment Status -->
        <div class="form-container">
            <h2>Manage Payment Status</h2>
            <h3>Add Payment Status</h3>
            <form method="POST" action="">
                <label for="status_name">Status Name</label>
                <input type="text" name="status_name" required>
                <button type="submit" name="add_payment_status">Add Payment Status</button>
            </form>

            <h3>Update/Delete Payment Status</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Status Name</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($payment_statuses as $status): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($status['payment_status_id']); ?></td>
                        <td><?php echo htmlspecialchars($status['status_name']); ?></td>
                        <td class="action-buttons">
                            <form method="POST" action="">
                                <input type="hidden" name="payment_status_id" value="<?php echo $status['payment_status_id']; ?>">
                                <input type="text" name="status_name" value="<?php echo htmlspecialchars($status['status_name']); ?>" required>
                                <button type="submit" name="update_payment_status">Update</button>
                                <button type="submit" name="delete_payment_status" onclick="return confirm('Are you sure you want to delete this status?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Manage Order Status -->
        <div class="form-container">
            <h2>Manage Order Status</h2>
            <h3>Add Order Status</h3>
            <form method="POST" action="">
                <label for="status_name">Status Name</label>
                <input type="text" name="status_name" required>
                <button type="submit" name="add_order_status">Add Order Status</button>
            </form>

            <h3>Update/Delete Order Status</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Status Name</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($order_statuses as $status): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($status['status_id']); ?></td>
                        <td><?php echo htmlspecialchars($status['status_name']); ?></td>
                        <td class="action-buttons">
                            <form method="POST" action="">
                                <input type="hidden" name="status_id" value="<?php echo $status['status_id']; ?>">
                                <input type="text" name="status_name" value="<?php echo htmlspecialchars($status['status_name']); ?>" required>
                                <button type="submit" name="update_order_status">Update</button>
                                <button type="submit" name="delete_order_status" onclick="return confirm('Are you sure you want to delete this status?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="back-button">
            <a href="index.php">Back to Home</a>
        </div>
    </div>
</body>
</html>