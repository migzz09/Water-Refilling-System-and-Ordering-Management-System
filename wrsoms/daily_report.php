<?php
require_once 'connect.php';
date_default_timezone_set('Asia/Manila');

// Handle selected date (default = today)
$selected_date = $_GET['date'] ?? date('Y-m-d');
$today = date('Y-m-d');

// Total sales for selected date
try {
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount_paid),0) AS total FROM payments WHERE DATE(payment_date) = ?");
    $stmt->execute([$selected_date]);
    $total_sales_today = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $total_sales_today = 0; error_log($e->getMessage()); }

// Orders
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = ?");
$stmt->execute([$selected_date]);
$orders_today = (int)$stmt->fetchColumn();

// New customers
$stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE DATE(date_created) = ?");
$stmt->execute([$selected_date]);
$new_customers_today = (int)$stmt->fetchColumn();

// Completed payments
$stmt = $pdo->prepare("SELECT IFNULL(SUM(amount_paid),0) FROM payments p 
    JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id 
    WHERE DATE(p.payment_date) = ? AND ps.status_name = 'Paid'");
$stmt->execute([$selected_date]);
$completed_payments_today = (float)$stmt->fetchColumn();

// Recent orders
$recent_orders = [];
try {
    $res = $pdo->prepare("SELECT * FROM admin_management_view WHERE DATE(order_date)=? ORDER BY order_date DESC LIMIT 10");
    $res->execute([$selected_date]);
    $recent_orders = $res->fetchAll();
} catch (PDOException $e) { error_log($e->getMessage()); }

// Top products last 30 days
$last30 = date('Y-m-d', strtotime($selected_date . ' -29 days'));
$top_products_stmt = $pdo->prepare("
    SELECT c.container_type, SUM(od.quantity) AS qty, SUM(od.subtotal) AS revenue
    FROM order_details od
    JOIN orders o ON od.reference_id = o.reference_id
    JOIN containers c ON od.container_id = c.container_id
    WHERE DATE(o.order_date) BETWEEN ? AND ?
    GROUP BY od.container_id
    ORDER BY qty DESC
    LIMIT 10
");
$top_products_stmt->execute([$last30, $selected_date]);
$top_products = $top_products_stmt->fetchAll();

// Past 7 days
$labels = [];
$sales_data = [];
$orders_data = [];
for ($i=6;$i>=0;$i--) {
    $d = date('Y-m-d', strtotime($selected_date . " -$i days"));
    $labels[] = $d;
    $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount_paid),0) FROM payments WHERE DATE(payment_date) = ?");
    $stmt->execute([$d]);
    $sales_data[] = (float)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$d]);
    $orders_data[] = (int)$stmt->fetchColumn();
}

// Payments by method
$payments_by_method_stmt = $pdo->prepare("
    SELECT pm.method_name, IFNULL(SUM(p.amount_paid),0) AS total
    FROM payments p
    JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
    WHERE DATE(p.payment_date) BETWEEN ? AND ?
    GROUP BY pm.payment_method_id
");
$payments_by_method_stmt->execute([$last30, $selected_date]);
$payments_by_method = $payments_by_method_stmt->fetchAll();

// Dates for sidebar
$dates_stmt = $pdo->query("SELECT DISTINCT DATE(order_date) as d FROM orders ORDER BY d DESC");
$all_dates = $dates_stmt->fetchAll(PDO::FETCH_COLUMN);

$prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Daily Report — Water Refilling</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
<style>
body{background:#f7f9fc;color:#1f2937;font-family:Segoe UI,Arial}
.card{border:none;border-radius:.75rem;box-shadow:0 6px 18px rgba(38,78,118,.06)}
.stat{font-size:1.6rem;font-weight:700}
.small-muted{color:#6b7280;font-size:.95rem}
.table thead th{border-bottom:2px solid #e6eef8}

/* Sidebar */
.sidebar {
  position: fixed;
  top: 0;
  left: -250px;
  width: 250px;
  height: 100%;
  background: #2c3e50;
  color: #fff;
  transition: 0.3s;
  padding-top: 60px;
  overflow-y: auto;
  z-index: 1050;
}
.sidebar.active { left: 0; }
.sidebar ul { list-style:none; padding:0; margin:0; }
.sidebar ul li { padding:10px 20px; border-bottom:1px solid rgba(255,255,255,.1); }
.sidebar ul li a { color:#fff; text-decoration:none; display:block; }
.sidebar ul li a:hover { background:#34495e; }

/* Hamburger */
.menu-toggle {
  font-size: 1.5rem;
  cursor: pointer;
  margin-right: 10px;
  user-select: none;
}
.close-btn {
  position: absolute;
  top: 10px;
  right: 15px;
  font-size: 1.5rem;
  cursor: pointer;
  color: #fff;
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <span class="close-btn" onclick="toggleSidebar()">&times;</span>
  <ul>
    <?php foreach ($all_dates as $d): ?>
      <li><a href="daily_report.php?date=<?= $d ?>"><?= $d ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
      <span class="menu-toggle" onclick="toggleSidebar()">&#9776;</span>
      <div>
        <h2 class="mb-0">Daily Sales Report</h2>
        <div class="small-muted">Overview for <?= date('F j, Y', strtotime($selected_date)) ?></div>
      </div>
    </div>
    <div>
      <a href="admin.php" class="btn btn-secondary">⬅ Back to Dashboard</a>
      <a href="daily_report.php?date=<?= $prev_date ?>" class="btn btn-outline-primary">⬅ Previous Day</a>
      <?php if ($selected_date < $today): ?>
        <a href="daily_report.php?date=<?= $next_date ?>" class="btn btn-outline-primary">Next Day ➡</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card p-3"><div class="small-muted">Total Sales</div><div class="stat">₱ <?= number_format($total_sales_today,2) ?></div><div class="stat-sub mt-1"><?= $orders_today ?> orders</div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="small-muted">Orders</div><div class="stat"><?= $orders_today ?></div><div class="stat-sub mt-1"><?= $new_customers_today ?> new customers</div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="small-muted">New Customers</div><div class="stat"><?= $new_customers_today ?></div><div class="stat-sub mt-1">Registered</div></div></div>
    <div class="col-md-3"><div class="card p-3"><div class="small-muted">Completed Payments</div><div class="stat">₱ <?= number_format($completed_payments_today,2) ?></div><div class="stat-sub mt-1">Paid status</div></div></div>
  </div>

  <!-- Charts -->
  <div class="row g-3 mb-4">
    <div class="col-lg-7"><div class="card p-3"><h6 class="mb-3">Sales — Last 7 days</h6><canvas id="salesLine"></canvas></div></div>
    <div class="col-lg-5">
      <div class="card p-3 mb-3"><h6 class="mb-3">Orders — Last 7 days</h6><canvas id="ordersBar"></canvas></div>
      <div class="card p-3"><h6 class="mb-3">Payments by Method — Last 30 days</h6><canvas id="paymentsPie" style="height:240px"></canvas></div>
    </div>
  </div>

  <!-- Tables -->
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card p-3">
        <h6 class="mb-3">Recent Orders</h6>
        <div class="table-responsive">
          <table class="table">
            <thead class="small-muted"><tr><th>Reference</th><th>Customer</th><th>Date</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php if (count($recent_orders)): foreach ($recent_orders as $ro): ?>
              <tr>
                <td><?= htmlspecialchars($ro['reference_id']) ?></td>
                <td><?= htmlspecialchars($ro['customer_name'] ?? 'Guest') ?></td>
                <td><?= htmlspecialchars($ro['order_date']) ?></td>
                <td class="text-end">₱ <?= number_format($ro['total_amount'],2) ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="4" class="text-center small-muted">No recent orders</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card p-3">
        <h6 class="mb-3">Top Selling Containers (30 days)</h6>
        <div class="table-responsive">
          <table class="table">
            <thead class="small-muted"><tr><th>Container</th><th class="text-end">Qty</th><th class="text-end">Revenue</th></tr></thead>
            <tbody>
              <?php if (count($top_products)): foreach ($top_products as $tp): ?>
                <tr>
                  <td><?= htmlspecialchars($tp['container_type']) ?></td>
                  <td class="text-end"><?= (int)$tp['qty'] ?></td>
                  <td class="text-end">₱ <?= number_format($tp['revenue'],2) ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="3" class="text-center small-muted">No sales in last 30 days</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleSidebar(){
  document.getElementById('sidebar').classList.toggle('active');
}

const labels = <?= json_encode($labels) ?>;
const salesData = <?= json_encode($sales_data) ?>;
const ordersData = <?= json_encode($orders_data) ?>;
const paymentsByMethod = <?= json_encode($payments_by_method) ?>;

new Chart(document.getElementById('salesLine'), {
  type: 'line',
  data: { labels: labels, datasets: [{ label: 'Sales (₱)', data: salesData, tension:0.3, fill:true, borderColor:'#007bff', backgroundColor:'rgba(0,123,255,0.2)' }] },
  options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true, ticks:{callback:v=>'₱'+v}}} }
});

new Chart(document.getElementById('ordersBar'), {
  type: 'bar',
  data: { labels: labels, datasets: [{ label:'Orders', data: ordersData, backgroundColor:'#28a745' }] },
  options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});

const pieLabels = paymentsByMethod.map(p=>p.method_name || 'Method');
const pieData = paymentsByMethod.map(p=>parseFloat(p.total) || 0);
new Chart(document.getElementById('paymentsPie'), {
  type:'pie',
  data:{ labels:pieLabels, datasets:[{ data:pieData, backgroundColor:['#007bff','#ffc107','#28a745','#dc3545','#6f42c1'] }] },
  options:{ responsive:true, plugins:{legend:{position:'bottom'}} }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
