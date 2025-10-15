<?php
session_start();
require_once 'connect.php';

// Handle CRUD for payment_status, order_status, delivery_status, batch_status, and order updates
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

    // Batch Status CRUD
    elseif (isset($_POST['add_batch_status'])) {
        $status_name = filter_input(INPUT_POST, 'status_name', FILTER_SANITIZE_STRING);
        if (empty($status_name)) {
            $errors[] = "Batch status name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO batch_status (status_name) VALUES (?)");
                $stmt->execute([$status_name]);
                $success = "Batch status added successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error adding batch status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_batch_status'])) {
        $batch_status_id = filter_input(INPUT_POST, 'batch_status_id', FILTER_VALIDATE_INT);
        $status_name = filter_input(INPUT_POST, 'status_name', FILTER_SANITIZE_STRING);
        if (!$batch_status_id || empty($status_name)) {
            $errors[] = "Invalid batch status ID or name.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE batch_status SET status_name = ? WHERE batch_status_id = ?");
                $stmt->execute([$status_name, $batch_status_id]);
                $success = "Batch status updated successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error updating batch status: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_batch_status'])) {
        $batch_status_id = filter_input(INPUT_POST, 'batch_status_id', FILTER_VALIDATE_INT);
        if (!$batch_status_id) {
            $errors[] = "Invalid batch status ID.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM batch_status WHERE batch_status_id = ?");
                $stmt->execute([$batch_status_id]);
                $success = "Batch status deleted successfully.";
            } catch (PDOException $e) {
                $errors[] = "Error deleting batch status: " . $e->getMessage();
            }
        }
    }

    // Update Order, Payment, Delivery, and Batch Status
    elseif (isset($_POST['update_order'])) {
        $reference_id = filter_input(INPUT_POST, 'reference_id', FILTER_SANITIZE_STRING);
        $payment_status_id = filter_input(INPUT_POST, 'payment_status_id', FILTER_VALIDATE_INT);
        $delivery_status_id = filter_input(INPUT_POST, 'delivery_status_id', FILTER_VALIDATE_INT);
        $order_status_id = filter_input(INPUT_POST, 'order_status_id', FILTER_VALIDATE_INT);
        $batch_status_id = filter_input(INPUT_POST, 'batch_status_id', FILTER_VALIDATE_INT);

        if (!$reference_id || !$payment_status_id || !$delivery_status_id || !$order_status_id || !$batch_status_id) {
            $errors[] = "Invalid order reference ID, payment status, delivery status, order status, or batch status.";
        } else {
            try {
                $pdo->beginTransaction();

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

                // Update batch status
                $stmt = $pdo->prepare("SELECT batch_id FROM orders WHERE reference_id = ?");
                $stmt->execute([$reference_id]);
                $batch = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($batch['batch_id']) {
                    $stmt = $pdo->prepare("UPDATE batches SET batch_status_id = ? WHERE batch_id = ?");
                    $stmt->execute([$batch_status_id, $batch['batch_id']]);
                }

                $pdo->commit();
                $success = "Order, payment, delivery, and batch status updated successfully.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Error updating order: " . $e->getMessage();
            }
        }
    }
}

// Fetch data for display
$payment_statuses = $pdo->query("SELECT * FROM payment_status")->fetchAll(PDO::FETCH_ASSOC);
$order_statuses = $pdo->query("SELECT * FROM order_status")->fetchAll(PDO::FETCH_ASSOC);
$delivery_statuses = $pdo->query("SELECT * FROM delivery_status")->fetchAll(PDO::FETCH_ASSOC);
$batch_statuses = $pdo->query("SELECT * FROM batch_status")->fetchAll(PDO::FETCH_ASSOC);

// Pagination for orders
$orders_per_page = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $orders_per_page;

// Get total number of orders
$stmt = $pdo->query("SELECT COUNT(*) FROM admin_management_view");
$total_orders = $stmt->fetchColumn();
$total_pages = ceil($total_orders / $orders_per_page);

// Fetch orders for current page
$stmt = $pdo->prepare("SELECT * FROM admin_management_view LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', (int)$orders_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            padding: 0;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            width: 90%;
            padding: 15px;
            box-sizing: border-box;
        }
        h1, h2, h3 {
            color: #333;
            text-align: center;
            margin-bottom: 15px;
            font-size: 20px;
        }
        .form-container, .table-container {
            background-color: #fff;
            padding: 15px;
            margin: 15px 0;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        .form-container label {
            display: block;
            margin: 8px 0 4px;
            font-weight: bold;
            font-size: 12px;
        }
        .form-container input, .form-container select {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 3px;
            box-sizing: border-box;
            font-size: 12px;
        }
        .form-container button {
            background-color: #007bff;
            color: #fff;
            padding: 8px 16px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }
        .form-container button:hover {
            background-color: #0056b3;
        }
        .success, .error {
            text-align: center;
            margin: 10px 0;
            padding: 8px;
            border-radius: 3px;
            font-size: 12px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .table-wrapper {
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 8px;
            text-align: left;
            font-size: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        th:nth-child(1) { width: 10%; } /* Order Ref */
        th:nth-child(2) { width: 15%; } /* Order Date */
        th:nth-child(3) { width: 10%; } /* Total Amount */
        th:nth-child(4) { width: 10%; } /* Order Status */
        th:nth-child(5) { width: 10%; } /* Payment Status */
        th:nth-child(6) { width: 10%; } /* Delivery Status */
        th:nth-child(7) { width: 10%; } /* Batch Status */
        th:nth-child(8) { width: 15%; } /* Customer */
        th:nth-child(9) { width: 20%; } /* Actions */
        .action-buttons form {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .action-buttons label {
            font-size: 10px;
            font-weight: bold;
            margin: 2px 0;
        }
        .action-buttons select {
            padding: 4px;
            border-radius: 3px;
            font-size: 10px;
            width: 100%;
        }
        .action-buttons button {
            padding: 4px 8px;
            font-size: 10px;
        }
        .pagination {
            text-align: center;
            margin: 15px 0;
        }
        .pagination a, .pagination span {
            display: inline-block;
            margin: 0 5px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 3px;
            text-decoration: none;
            color: #007bff;
            font-size: 12px;
        }
        .pagination a:hover {
            background-color: #007bff;
            color: #fff;
        }
        .pagination .current {
            background-color: #007bff;
            color: #fff;
            border-color: #007bff;
        }
        .pagination .disabled {
            color: #999;
            cursor: not-allowed;
        }
        .back-button {
            text-align: center;
            margin: 15px 0;
        }
        .back-button a {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
        }
        .back-button a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .container {
                padding: 10px;
                width: 95%;
            }
            th, td {
                font-size: 10px;
                padding: 6px;
            }
            .action-buttons label {
                font-size: 9px;
                margin: 1px 0;
            }
            .action-buttons select, .action-buttons button {
                font-size: 9px;
                padding: 3px;
            }
            h1, h2, h3 {
                font-size: 16px;
            }
            .pagination a, .pagination span {
                padding: 6px 10px;
                font-size: 10px;
            }
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
            <div class="table-wrapper">
                <table>
                    <tr>
                        <th>Order Ref</th>
                        <th>Order Date</th>
                        <th>Total Amount</th>
                        <th>Order Status</th>
                        <th>Payment Status</th>
                        <th>Delivery Status</th>
                        <th>Batch Status</th>
                        <th>Customer</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td title="<?php echo htmlspecialchars($order['reference_id'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($order['reference_id'] ?? 'N/A'); ?>
                            </td>
                            <td title="<?php echo htmlspecialchars($order['order_date'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($order['order_date'] ?? 'N/A'); ?>
                            </td>
                            <td title="<?php echo htmlspecialchars($order['total_amount'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($order['total_amount'] ?? 'N/A'); ?>
                            </td>
                            <td title="<?php echo htmlspecialchars($order['order_status'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($order['order_status'] ?? 'N/A'); ?>
                            </td>
                            <td title="<?php echo htmlspecialchars($order['payment_status'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($order['payment_status'] ?? 'N/A'); ?>
                            </td>
                            <td title="<?php echo htmlspecialchars($order['delivery_status'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($order['delivery_status'] ?? 'N/A'); ?>
                            </td>
                            <td title="<?php echo htmlspecialchars($order['batch_status'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($order['batch_status'] ?? 'N/A'); ?>
                            </td>
                            <td title="<?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?>">
                                <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?>
                            </td>
                            <td class="action-buttons">
                                <form method="POST" action="">
                                    <input type="hidden" name="reference_id" value="<?php echo $order['reference_id']; ?>">
                                    <label for="order_status_id_<?php echo $order['reference_id']; ?>">Order</label>
                                    <select name="order_status_id" id="order_status_id_<?php echo $order['reference_id']; ?>">
                                        <?php foreach ($order_statuses as $status): ?>
                                            <option value="<?php echo $status['status_id']; ?>" 
                                                <?php echo ($status['status_id'] == ($order['order_status_id'] ?? 1)) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status['status_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="payment_status_id_<?php echo $order['reference_id']; ?>">Payment</label>
                                    <select name="payment_status_id" id="payment_status_id_<?php echo $order['reference_id']; ?>">
                                        <?php foreach ($payment_statuses as $status): ?>
                                            <option value="<?php echo $status['payment_status_id']; ?>" 
                                                <?php echo ($status['payment_status_id'] == ($order['payment_status_id'] ?? 1)) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status['status_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="delivery_status_id_<?php echo $order['reference_id']; ?>">Delivery</label>
                                    <select name="delivery_status_id" id="delivery_status_id_<?php echo $order['reference_id']; ?>">
                                        <?php foreach ($delivery_statuses as $status): ?>
                                            <option value="<?php echo $status['delivery_status_id']; ?>" 
                                                <?php echo ($status['delivery_status_id'] == ($order['delivery_status_id'] ?? 1)) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status['status_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="batch_status_id_<?php echo $order['reference_id']; ?>">Batch</label>
                                    <select name="batch_status_id" id="batch_status_id_<?php echo $order['reference_id']; ?>">
                                        <?php foreach ($batch_statuses as $status): ?>
                                            <option value="<?php echo $status['batch_status_id']; ?>" 
                                                <?php echo ($status['batch_status_id'] == ($order['batch_status_id'] ?? 1)) ? 'selected' : ''; ?>>
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
            <!-- Pagination Controls -->
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">Previous</a>
                <?php else: ?>
                    <span class="disabled">Previous</span>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" <?php echo $i == $page ? 'class="current"' : ''; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Next</a>
                <?php else: ?>
                    <span class="disabled">Next</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Manage Batch Status -->
        <div class="form-container">
            <h2>Manage Batch Status</h2>
            <h3>Add Batch Status</h3>
            <form method="POST" action="">
                <label for="status_name">Status Name</label>
                <input type="text" name="status_name" required>
                <button type="submit" name="add_batch_status">Add Batch Status</button>
            </form>

            <h3>Update/Delete Batch Status</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Status Name</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($batch_statuses as $status): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($status['batch_status_id']); ?></td>
                        <td><?php echo htmlspecialchars($status['status_name']); ?></td>
                        <td class="action-buttons">
                            <form method="POST" action="">
                                <input type="hidden" name="batch_status_id" value="<?php echo $status['batch_status_id']; ?>">
                                <input type="text" name="status_name" value="<?php echo htmlspecialchars($status['status_name']); ?>" required>
                                <button type="submit" name="update_batch_status">Update</button>
                                <button type="submit" name="delete_batch_status" onclick="return confirm('Are you sure you want to delete this status?');">Delete</button>
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
