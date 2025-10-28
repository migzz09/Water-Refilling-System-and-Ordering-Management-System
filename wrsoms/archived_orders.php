<?php
session_start();
require_once 'connect.php';

// ---------------------------------------------------------------------------
// Restore Order
if (isset($_GET['restore']) && !empty($_GET['restore'])) {
    $ref = $_GET['restore'];
    $stmt = $pdo->prepare("UPDATE orders SET is_archived = 0 WHERE reference_id = ?");
    $stmt->execute([$ref]);
    header("Location: archived_orders.php");
    exit;
}

// ---------------------------------------------------------------------------
// Fetch statuses (for display)
$orderStatuses = $pdo->query("SELECT * FROM order_status")->fetchAll(PDO::FETCH_ASSOC);
$paymentStatuses = $pdo->query("SELECT * FROM payment_status")->fetchAll(PDO::FETCH_ASSOC);
$deliveryStatuses = $pdo->query("SELECT * FROM delivery_status")->fetchAll(PDO::FETCH_ASSOC);
$batchStatuses = $pdo->query("SELECT * FROM batch_status")->fetchAll(PDO::FETCH_ASSOC);

// ---------------------------------------------------------------------------
// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = "WHERE o.is_archived = 1";
$params = [];

if ($search !== '') {
    $where .= " AND (o.reference_id LIKE :s OR CONCAT(c.first_name, ' ', c.last_name) LIKE :s)";
    $params[':s'] = "%$search%";
}

// ---------------------------------------------------------------------------
// Fetch archived orders
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
ORDER BY b.vehicle_type, b.batch_number, o.order_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by batch for display
$batches = [];
foreach ($orders as $order) {
    $vehicleType = $order['vehicle_type'] ?: 'Unbatched';
    $batchKey = $vehicleType . ' - ' . ($order['batch_id'] ?: 'Unbatched');
    $batches[$batchKey]['vehicle_type'] = $vehicleType;
    $batches[$batchKey]['orders'][] = $order;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Archived Orders - Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f8f9fb; }
.card { border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.badge { font-size: 0.8rem; }
.section-header { background:#f1f3f5; padding:10px 15px; font-weight:600; border-left:4px solid #0d6efd; margin-bottom:10px; }
</style>
</head>
<body class="p-3">
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Archived Orders</h3>
  </div>

  <div class="card p-3 mb-3">
    <form class="d-flex" method="get">
      <input type="text" name="search" class="form-control me-2" placeholder="Search by Ref ID or Customer..." value="<?= htmlspecialchars($search) ?>">
      <button class="btn btn-primary">Search</button>
      <?php if ($search !== ''): ?>
        <a href="archived_orders.php" class="btn btn-link">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (empty($orders)): ?>
    <div class="card p-4 text-center text-muted">
      <p>No archived orders found.</p>
    </div>
  <?php else: ?>
    <?php foreach ($batches as $key => $group): ?>
      <div class="card p-3 mb-4">
        <div class="section-header">
          <?= htmlspecialchars($group['vehicle_type']) ?> Batch
        </div>
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
              <?php foreach ($group['orders'] as $row): ?>
              <tr>
                <td><strong><?= htmlspecialchars($row['reference_id']) ?></strong></td>
                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                <td><?= htmlspecialchars($row['customer_contact']) ?></td>
                <td><?= date("M d, Y H:i", strtotime($row['order_date'])) ?></td>
                <td><?= $row['delivery_date'] ? date("M d, Y", strtotime($row['delivery_date'])) : '-' ?></td>
                <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                <td><?= htmlspecialchars($row['order_status']) ?></td>
                <td><?= htmlspecialchars($row['payment_status']) ?></td>
                <td><?= htmlspecialchars($row['delivery_status']) ?></td>
                <td><?= htmlspecialchars($row['batch_status']) ?></td>
                <td><?= htmlspecialchars($row['vehicle'] ?: '-') ?></td>
                <td>
                  <a href="?restore=<?= urlencode($row['reference_id']) ?>" class="btn btn-success btn-sm" onclick="return confirm('Restore this order?')">Restore</a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</body>
</html>
