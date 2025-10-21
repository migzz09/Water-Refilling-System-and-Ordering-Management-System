<?php
session_start();
include '../connect.php'; // uses your PDO connection ($pdo)

// Temporary session bypass for testing (remove when login is ready)
if (!isset($_SESSION['admin_logged_in'])) {
    $_SESSION['admin_logged_in'] = true;
}

// Handle archive action (mark as Delivered)
if (isset($_GET['archive_id'])) {
    $archive_id = $_GET['archive_id'];
    $stmt = $pdo->prepare("UPDATE orders SET order_status_id = 3 WHERE reference_id = ?");
    $stmt->execute([$archive_id]);
    header("Location: manage_orders.php");
    exit();
}

// Search logic
$search = "";
$where = "";
$params = [];

if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $where = "WHERE o.reference_id LIKE ? 
              OR CONCAT(c.first_name, ' ', c.last_name) LIKE ? 
              OR os.status_name LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Main query (pulls from actual tables)
$query = "
    SELECT 
        o.reference_id,
        CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
        c.customer_contact,
        o.order_date,
        o.delivery_date,
        o.total_amount,
        os.status_name AS order_status,
        ps.status_name AS payment_status,
        ds.status_name AS delivery_status
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN order_status os ON o.order_status_id = os.status_id
    LEFT JOIN payments p ON o.reference_id = p.reference_id
    LEFT JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id
    LEFT JOIN batches b ON o.batch_id = b.batch_id
    LEFT JOIN deliveries d ON b.batch_id = d.batch_id
    LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
    $where
    ORDER BY o.order_date DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Orders | Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        table th {
            background-color: #007bff;
            color: white;
            vertical-align: middle;
        }
        table td {
            vertical-align: middle;
        }
        .search-bar {
            max-width: 400px;
        }
        .btn-archive {
            background-color: #dc3545;
            color: white;
        }
        .btn-archive:hover {
            background-color: #bb2d3b;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-primary">Manage Orders</h2>
            <a href="../admin_dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>

        <!-- Search Bar -->
        <form class="mb-3" method="GET" action="">
            <div class="input-group search-bar">
                <input type="text" name="search" class="form-control" placeholder="Search orders..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>

        <!-- Orders Table -->
        <div class="card p-3">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Reference ID</th>
                            <th>Customer Name</th>
                            <th>Contact</th>
                            <th>Order Date</th>
                            <th>Delivery Date</th>
                            <th>Total Amount</th>
                            <th>Order Status</th>
                            <th>Payment Status</th>
                            <th>Delivery Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $row): ?>
                            <?php if ($row['order_status'] != 'Delivered' && $row['order_status'] != 'Archived'): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['reference_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['customer_contact']); ?></td>
                                    <td><?php echo date("M d, Y", strtotime($row['order_date'])); ?></td>
                                    <td><?php echo $row['delivery_date'] ? date("M d, Y", strtotime($row['delivery_date'])) : '—'; ?></td>
                                    <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo ($row['order_status'] == 'Pending') ? 'warning' : 
                                                 (($row['order_status'] == 'Dispatched') ? 'info' : 'success'); ?>">
                                            <?php echo htmlspecialchars($row['order_status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['payment_status'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['delivery_status'] ?? 'N/A'); ?></td>
                                    <td>
                                        <a href="?archive_id=<?php echo $row['reference_id']; ?>" 
                                           class="btn btn-archive btn-sm"
                                           onclick="return confirm('Mark this order as Archived?')">
                                           Archive
                                        </a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="10" class="text-center text-muted">No orders found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

