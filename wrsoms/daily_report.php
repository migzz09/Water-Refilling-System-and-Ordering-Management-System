<?php
require_once 'connect.php';
date_default_timezone_set('Asia/Manila');

// Handle selected date (default = today)
$selected_date = $_GET['date'] ?? date('Y-m-d');
$today = date('Y-m-d');

// Handle dates request for sidebar
if (isset($_GET['get_dates']) && $_GET['get_dates'] === 'true') {
    header('Content-Type: application/json');
    $dates_stmt = $pdo->query("SELECT DISTINCT DATE(order_date) as d FROM orders ORDER BY d DESC LIMIT 30");
    $all_dates = $dates_stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($all_dates);
    exit;
}

try {
    // Total Revenue (SAME AS DASHBOARD - from orders.total_amount)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$selected_date]);
    $total_revenue = $stmt->fetchColumn();

    // Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as orders_count FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$selected_date]);
    $orders_today = $stmt->fetchColumn();

    // New Customers
    $stmt = $pdo->prepare("SELECT COUNT(*) as new_customers FROM customers WHERE DATE(date_created) = ?");
    $stmt->execute([$selected_date]);
    $new_customers_today = $stmt->fetchColumn();

    // Completed Payments
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) as completed_payments FROM payments p 
        JOIN payment_status ps ON p.payment_status_id = ps.payment_status_id 
        WHERE DATE(p.payment_date) = ? AND ps.status_name = 'Paid'");
    $stmt->execute([$selected_date]);
    $completed_payments_today = $stmt->fetchColumn();

    // Recent Orders
    $stmt = $pdo->prepare("SELECT * FROM admin_management_view WHERE DATE(order_date)=? ORDER BY order_date DESC LIMIT 10");
    $stmt->execute([$selected_date]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Products (30 days)
    $last30 = date('Y-m-d', strtotime($selected_date . ' -29 days'));
    $stmt = $pdo->prepare("
        SELECT c.container_type, 
               COALESCE(SUM(od.quantity), 0) as qty, 
               COALESCE(SUM(od.subtotal), 0) as revenue
        FROM order_details od
        JOIN orders o ON od.reference_id = o.reference_id
        JOIN containers c ON od.container_id = c.container_id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY od.container_id
        ORDER BY qty DESC
        LIMIT 10
    ");
    $stmt->execute([$last30, $selected_date]);
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Past 7 days - Revenue (SAME AS DASHBOARD - from orders.total_amount)
    $labels = [];
    $revenue_data = [];
    $orders_data = [];
    for ($i=6; $i>=0; $i--) {
        $d = date('Y-m-d', strtotime($selected_date . " -$i days"));
        $labels[] = $d;
        
        // Revenue from orders (SAME AS DASHBOARD)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(order_date) = ?");
        $stmt->execute([$d]);
        $revenue_data[] = (float)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = ?");
        $stmt->execute([$d]);
        $orders_data[] = (int)$stmt->fetchColumn();
    }

    // Payments by Method (30 days)
    $stmt = $pdo->prepare("
        SELECT pm.method_name, COALESCE(SUM(p.amount_paid), 0) as total
        FROM payments p
        JOIN payment_methods pm ON p.payment_method_id = pm.payment_method_id
        WHERE DATE(p.payment_date) BETWEEN ? AND ?
        GROUP BY pm.payment_method_id
    ");
    $stmt->execute([$last30, $selected_date]);
    $payments_by_method = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching daily report statistics: " . $e->getMessage());
}

$prev_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
$next_date = date('Y-m-d', strtotime($selected_date . ' +1 day'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Daily Report — Water Refilling</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    background: #F7FAFC;
    color: #1A202C;
    min-height: 100vh;
    display: flex;
    overflow-x: hidden;
}

.sidebar {
    width: 64px;
    background: #1A202C;
    color: #E2E8F0;
    position: fixed;
    height: 100vh;
    padding: 24px 0;
    transition: width 0.3s ease;
    z-index: 1000;
}

.sidebar:hover, .sidebar:focus-within {
    width: 240px;
}

.logo-container {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    font-size: 20px;
    font-weight: 600;
    color: #3B82F6;
    text-align: center;
    margin-bottom: 32px;
    opacity: 0;
    transition: opacity 0.3s ease;
    background: transparent;
    padding: 8px;
}

.logo-img {
    width: 32px;
    height: 32px;
    object-fit: contain;
    background: transparent;
}

.logo-text {
    font-size: 20px;
    font-weight: 600;
    color: #3B82F6;
}

.sidebar:hover .logo-container, .sidebar:focus-within .logo-container {
    opacity: 1;
}

.sidebar ul {
    list-style: none;
}

.sidebar ul li {
    padding: 16px;
    cursor: pointer;
    transition: background 0.2s ease;
}

.sidebar ul li:hover, .sidebar ul li:focus {
    background: #2D3748;
}

.sidebar ul li.active {
    background: #3B82F6;
    color: #FFFFFF;
    position: relative;
}

.sidebar ul li a, .sidebar ul li button {
    color: inherit;
    text-decoration: none;
    font-size: 14px;
    opacity: 0;
    transition: opacity 0.3s ease;
    display: block;
    text-align: center;
    background: none;
    border: none;
    width: 100%;
    cursor: pointer;
}

.sidebar:hover ul li a, .sidebar:focus-within ul li a,
.sidebar:hover ul li button, .sidebar:focus-within ul li button {
    opacity: 1;
    text-align: left;
}

.content {
    margin-left: 64px;
    padding: 24px;
    width: calc(100% - 64px);
    background: #FFFFFF;
    min-height: 100vh;
    transition: margin-left 0.3s ease, width 0.3s ease;
}

.sidebar:hover ~ .content, .sidebar:focus-within ~ .content {
    margin-left: 240px;
    width: calc(100% - 240px);
}

/* Report Sidebar */
.sidebar-report {
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
.sidebar-report.active {
    left: 0;
}
.sidebar-report ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.sidebar-report ul li {
    padding: 10px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.sidebar-report ul li a {
    color: #fff;
    text-decoration: none;
    display: block;
}
.sidebar-report ul li a:hover {
    background: #34495e;
}
.close-btn {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 1.5rem;
    cursor: pointer;
    color: #fff;
}

.card{border:none;border-radius:.75rem;box-shadow:0 6px 18px rgba(38,78,118,.06)}
.stat{font-size:1.6rem;font-weight:700}
.small-muted{color:#6b7280;font-size:.95rem}
.table thead th{border-bottom:2px solid #e6eef8}
.stat-sub{font-size:.875rem;color:#6b7280}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding-bottom: 16px;
    }
    .sidebar:hover, .sidebar:focus-within {
        width: 100%;
    }
    .content {
        margin-left: 0;
        width: 100%;
        padding: 16px;
    }
    .sidebar:hover ~ .content, .sidebar:focus-within ~ .content {
        margin-left: 0;
        width: 100%;
    }
}
</style>
</head>
<body>
<!-- Main Sidebar (same as admin_dashboard.php) -->
<nav class="sidebar" aria-label="Main navigation">
    <div class="logo-container">
        <img src="ww_logo.png" alt="Water World Logo" class="logo-img">
        <span class="logo-text">WATER WORLD</span>
    </div>
    <ul>
        <li><a href="admin_dashboard.php">Dashboard</a></li>
        <li><a href="manage_orders.php">Manage Orders</a></li>
        <li><a href="status.php">Manage Status</a></li>
        <li class="active"><button type="button" onclick="showDailyReport()">Daily Report</button></li>
    </ul>
</nav>

<!-- Report Sidebar Panel -->
<div class="sidebar-report" id="reportSidebar">
    <span class="close-btn" onclick="toggleReportSidebar()">&times;</span>
    <ul id="reportDates"></ul>
</div>

<!-- Main Content -->
<main class="content" id="mainContent">
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Daily Sales Report</h2>
                <div class="small-muted">Overview for <?= date('F j, Y', strtotime($selected_date)) ?></div>
            </div>
            <div>
                <button type="button" onclick="toggleReportSidebar()" class="btn btn-outline-primary me-2" title="Date Selector">📅</button>
                <a href="daily_report.php?date=<?= $prev_date ?>" class="btn btn-outline-primary">⬅ Previous Day</a>
                <?php if ($selected_date < $today): ?>
                    <a href="daily_report.php?date=<?= $next_date ?>" class="btn btn-outline-primary">Next Day ➡</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="small-muted">Total Revenue</div>
                    <div class="stat">₱ <?= number_format($total_revenue, 2) ?></div>
                    <div class="stat-sub mt-1"><?= $orders_today ?> orders</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="small-muted">Orders</div>
                    <div class="stat"><?= $orders_today ?></div>
                    <div class="stat-sub mt-1"><?= $new_customers_today ?> new customers</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="small-muted">New Customers</div>
                    <div class="stat"><?= $new_customers_today ?></div>
                    <div class="stat-sub mt-1">Registered</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="small-muted">Completed Payments</div>
                    <div class="stat">₱ <?= number_format($completed_payments_today, 2) ?></div>
                    <div class="stat-sub mt-1">Paid status</div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="card p-3">
                    <h6 class="mb-3">Revenue — Last 7 days</h6>
                    <canvas id="revenueLine"></canvas>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card p-3 mb-3">
                    <h6 class="mb-3">Orders — Last 7 days</h6>
                    <canvas id="ordersBar"></canvas>
                </div>
                <div class="card p-3">
                    <h6 class="mb-3">Payments by Method — Last 30 days</h6>
                    <canvas id="paymentsPie" style="height:240px"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables -->
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card p-3">
                    <h6 class="mb-3">Recent Orders</h6>
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="small-muted">
                                <tr>
                                    <th>Reference</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($recent_orders)): ?>
                                    <?php foreach ($recent_orders as $ro): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($ro['reference_id']) ?></td>
                                            <td><?= htmlspecialchars($ro['customer_name'] ?? 'Guest') ?></td>
                                            <td><?= htmlspecialchars($ro['order_date']) ?></td>
                                            <td class="text-end">₱ <?= number_format($ro['total_amount'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
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
                            <thead class="small-muted">
                                <tr>
                                    <th>Container</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($top_products)): ?>
                                    <?php foreach ($top_products as $tp): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($tp['container_type']) ?></td>
                                            <td class="text-end"><?= (int)$tp['qty'] ?></td>
                                            <td class="text-end">₱ <?= number_format($tp['revenue'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" class="text-center small-muted">No sales in last 30 days</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function toggleReportSidebar() {
    document.getElementById('reportSidebar').classList.toggle('active');
}

function fetchDates() {
    fetch('daily_report.php?get_dates=true')
        .then(response => response.json())
        .then(dates => {
            const ul = document.getElementById('reportDates');
            ul.innerHTML = '';
            dates.forEach(d => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#';
                a.textContent = d;
                a.onclick = () => {
                    window.location.href = 'daily_report.php?date=' + d;
                };
                li.appendChild(a);
                ul.appendChild(li);
            });
        })
        .catch(error => console.error('Error fetching dates:', error));
}

document.addEventListener('DOMContentLoaded', function() {
    fetchDates();
});

const labels = <?= json_encode($labels) ?>;
const revenueData = <?= json_encode($revenue_data) ?>;
const ordersData = <?= json_encode($orders_data) ?>;
const paymentsByMethod = <?= json_encode($payments_by_method) ?>;

new Chart(document.getElementById('revenueLine'), {
    type: 'line',
    data: { labels: labels, datasets: [{ label: 'Revenue (₱)', data: revenueData, tension:0.3, fill:true, borderColor:'#007bff', backgroundColor:'rgba(0,123,255,0.2)' }] },
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