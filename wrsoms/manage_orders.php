<?php
session_start();
include 'connect.php';

// ---------------------------------------------------------------------------
// Archive Order
if (isset($_GET['archive']) && !empty($_GET['archive'])) {
    $ref = $_GET['archive'];
    $stmt = $pdo->prepare("UPDATE orders SET is_archived = 1 WHERE reference_id = ?");
    $stmt->execute([$ref]);
    header("Location: manage_orders.php");
    exit;
}

// ---------------------------------------------------------------------------
// Update Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $ref = $_POST['reference_id'];

    $order_status = $_POST['order_status_id'];
    $payment_status = $_POST['payment_status_id'];
    $delivery_status = $_POST['delivery_status_id'];
    $batch_status = $_POST['batch_status_id'];

    $pdo->prepare("UPDATE orders SET order_status_id = ? WHERE reference_id = ?")->execute([$order_status, $ref]);
    $pdo->prepare("UPDATE payments SET payment_status_id = ? WHERE reference_id = ?")->execute([$payment_status, $ref]);
    $pdo->prepare("
        UPDATE deliveries d
        JOIN batches b ON d.batch_id = b.batch_id
        JOIN orders o ON o.batch_id = b.batch_id
        SET d.delivery_status_id = ?
        WHERE o.reference_id = ?
    ")->execute([$delivery_status, $ref]);
    $pdo->prepare("
        UPDATE batches b
        JOIN orders o ON o.batch_id = b.batch_id
        SET b.batch_status_id = ?
        WHERE o.reference_id = ?
    ")->execute([$batch_status, $ref]);

    echo "<script>alert('Statuses updated successfully!');window.location='manage_orders.php';</script>";
    exit;
}

// ---------------------------------------------------------------------------
// Fetch statuses
$orderStatuses = $pdo->query("SELECT * FROM order_status")->fetchAll(PDO::FETCH_ASSOC);
$paymentStatuses = $pdo->query("SELECT * FROM payment_status")->fetchAll(PDO::FETCH_ASSOC);
$deliveryStatuses = $pdo->query("SELECT * FROM delivery_status")->fetchAll(PDO::FETCH_ASSOC);
$batchStatuses = $pdo->query("SELECT * FROM batch_status")->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------------------
// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "WHERE o.is_archived = 0";
$params = [];

if ($search !== '') {
    $where .= " AND (o.reference_id LIKE :s OR CONCAT(c.first_name, ' ', c.last_name) LIKE :s)";
    $params[':s'] = "%$search%";
}

// ---------------------------------------------------------------------------
// Fetch active orders
$sql = "
SELECT 
    o.reference_id, o.order_date, o.delivery_date, o.total_amount,
    os.status_name AS order_status, o.order_status_id,
    p.payment_status_id, ps.status_name AS payment_status,
    d.delivery_status_id, ds.status_name AS delivery_status,
    b.batch_id, b.batch_status_id, bs.status_name AS batch_status,
    b.vehicle_type, b.vehicle,
    CONCAT(c.first_name, ' ', COALESCE(c.middle_name,''), ' ', c.last_name) AS customer_name,
    c.customer_contact
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN order_status os ON o.order_status_id = os.status_id
LEFT JOIN payments p ON o.reference_id = p.reference_id
LEFT JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id
LEFT JOIN batches b ON o.batch_id = b.batch_id
LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
LEFT JOIN deliveries d ON b.batch_id = d.batch_id
LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
$where
ORDER BY o.order_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Orders - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f8f9fb; }
.card { border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.badge { font-size: 0.8rem; }
.status-paid { background-color: #28a745; }
.status-pending { background-color: #ffc107; color:#000; }
.status-cancelled { background-color: #dc3545; }
.status-processing { background-color: #0d6efd; }
.status-delivered { background-color: #20c997; }
</style>
</head>
<body class="p-3">
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Manage Orders</h3>
    <div>
    </div>
  </div>

  <div class="card p-3 mb-3">
    <form class="d-flex" method="get">
      <input type="text" name="search" class="form-control me-2" placeholder="Search by Ref ID or Customer..." value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-primary">Search</button>
      <?php if ($search !== ''): ?>
        <a href="manage_orders.php" class="btn btn-link">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <div class="card p-3">
    <?php if (empty($orders)): ?>
      <p class="text-muted text-center">No active orders found.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Ref ID</th>
            <th>Customer</th>
            <th>Contact</th>
            <th>Order Date</th>
            <th>Delivery Date</th>
            <th>Total</th>
            <th>Order</th>
            <th>Payment</th>
            <th>Delivery</th>
            <th>Batch</th>
            <th>Vehicle</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $row): ?>
          <tr>
            <form method="POST">
              <input type="hidden" name="reference_id" value="<?= htmlspecialchars($row['reference_id']) ?>">
              <td><strong><?= htmlspecialchars($row['reference_id']) ?></strong></td>
              <td><?= htmlspecialchars($row['customer_name']) ?></td>
              <td><?= htmlspecialchars($row['customer_contact']) ?></td>
              <td><?= date("M d, Y H:i", strtotime($row['order_date'])) ?></td>
              <td><?= $row['delivery_date'] ? date("M d, Y", strtotime($row['delivery_date'])) : '-' ?></td>
              <td>₱<?= number_format($row['total_amount'], 2) ?></td>

              <!-- Order -->
              <td>
                <select name="order_status_id" class="form-select form-select-sm">
                  <?php foreach ($orderStatuses as $st): ?>
                    <option value="<?= $st['status_id'] ?>" <?= $st['status_id']==$row['order_status_id']?'selected':'' ?>><?= htmlspecialchars($st['status_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>

              <!-- Payment -->
              <td>
                <select name="payment_status_id" class="form-select form-select-sm">
                  <?php foreach ($paymentStatuses as $st): ?>
                    <option value="<?= $st['payment_status_id'] ?>" <?= $st['payment_status_id']==$row['payment_status_id']?'selected':'' ?>><?= htmlspecialchars($st['status_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>

              <!-- Delivery -->
              <td>
                <select name="delivery_status_id" class="form-select form-select-sm">
                  <?php foreach ($deliveryStatuses as $st): ?>
                    <option value="<?= $st['delivery_status_id'] ?>" <?= $st['delivery_status_id']==$row['delivery_status_id']?'selected':'' ?>><?= htmlspecialchars($st['status_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>

              <!-- Batch -->
              <td>
                <select name="batch_status_id" class="form-select form-select-sm">
                  <?php foreach ($batchStatuses as $st): ?>
                    <option value="<?= $st['batch_status_id'] ?>" <?= $st['batch_status_id']==$row['batch_status_id']?'selected':'' ?>><?= htmlspecialchars($st['status_name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>

              <td><?= htmlspecialchars($row['vehicle'] ?: '-') ?></td>

              <td class="text-nowrap">
                <button name="update_status" class="btn btn-success btn-sm"> Save</button>
                <a href="?archive=<?= urlencode($row['reference_id']) ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Archive this order?')"> Archive</a>
              </td>
            </form>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
