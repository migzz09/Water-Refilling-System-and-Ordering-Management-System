<?php
session_start();
require_once 'connect.php';

// Set Philippine time (Asia/Manila, UTC+8)
date_default_timezone_set('Asia/Manila');

// Fetch order statistics for today
$today = date('Y-m-d');
try {
    // Total Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $total_orders = $stmt->fetchColumn();

    // Pending Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE order_status_id = 1 AND DATE(order_date) = ?");
    $stmt->execute([$today]);
    $pending_orders = $stmt->fetchColumn();

    // Completed Orders
    $stmt = $pdo->prepare("SELECT COUNT(*) as completed_orders FROM orders WHERE order_status_id = 3 AND DATE(order_date) = ?");
    $stmt->execute([$today]);
    $completed_orders = $stmt->fetchColumn();

    // Total Revenue
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total_revenue FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $total_revenue = $stmt->fetchColumn();

    // Average Order Value
    $stmt = $pdo->prepare("SELECT COALESCE(AVG(total_amount), 0) as avg_order_value FROM orders WHERE DATE(order_date) = ?");
    $stmt->execute([$today]);
    $avg_order_value = $stmt->fetchColumn();

    // Pending Deliveries
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_deliveries
        FROM orders o
        LEFT JOIN deliveries d ON o.batch_id = d.batch_id
        WHERE DATE(o.order_date) = ? AND (d.delivery_status_id = 1 OR d.delivery_status_id IS NULL)
    ");
    $stmt->execute([$today]);
    $pending_deliveries = $stmt->fetchColumn();

    // Recent Orders
    $stmt = $pdo->prepare("
        SELECT o.reference_id, o.order_date, o.total_amount, os.status_name as order_status
        FROM orders o
        LEFT JOIN order_status os ON o.order_status_id = os.status_id
        WHERE DATE(o.order_date) = ?
        ORDER BY o.order_date DESC
        LIMIT 5
    ");
    $stmt->execute([$today]);
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Batch Overview
    $stmt = $pdo->prepare("
        SELECT 
            b.vehicle_type, 
            b.batch_number, 
            COUNT(o.reference_id) as order_count,
            bs.status_name as batch_status
        FROM batches b
        LEFT JOIN orders o ON b.batch_id = o.batch_id AND DATE(o.order_date) = ?
        LEFT JOIN batch_status bs ON b.batch_status_id = bs.batch_status_id
        WHERE DATE(b.batch_date) = ?
        GROUP BY b.vehicle_type, b.batch_number, bs.status_name
        HAVING b.batch_number BETWEEN 1 AND 3
        ORDER BY b.vehicle_type, b.batch_number
    ");
    $stmt->execute([$today, $today]);
    $batch_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching order statistics: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - WATER WORLD</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js"></script>
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

        /* === SIDEBAR: ALWAYS OPEN === */
        .sidebar {
            width: 240px;
            background: #1A202C;
            color: #E2E8F0;
            position: fixed;
            height: 100vh;
            padding: 24px 0;
            z-index: 1000;
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
            opacity: 1;
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
            opacity: 1;
            display: block;
            text-align: left;
            background: none;
            border: none;
            width: 100%;
            cursor: pointer;
        }

        /* === CONTENT AREA === */
        .content {
            margin-left: 240px;
            padding: 24px;
            width: calc(100% - 240px);
            background: #FFFFFF;
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid #E2E8F0;
        }

        .header .title {
            font-size: 24px;
            font-weight: 600;
            color: #1A202C;
        }

        .header .date-time {
            font-size: 14px;
            color: #6B7280;
        }

        .metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .metric-card {
            background: #FFFFFF;
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .metric-card:hover, .metric-card:focus-within {
            transform: translateY(-4px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .metric-card h3 {
            font-size: 12px;
            font-weight: 500;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .metric-card p {
            font-size: 20px;
            font-weight: 600;
            color: #1A202C;
        }

        .metric-card[data-color="blue"] { border-left: 4px solid #3B82F6; }
        .metric-card[data-color="green"] { border-left: 4px solid #10B981; }
        .metric-card[data-color="purple"] { border-left: 4px solid #8B5CF6; }
        .metric-card[data-color="teal"] { border-left: 4px solid #14B8A6; }
        .metric-card[data-color="pink"] { border-left: 4px solid #EC4899; }
        .metric-card[data-color="gray"] { border-left: 4px solid #6B7280; }

        .quick-actions {
            margin-bottom: 24px;
        }

        .quick-actions button {
            background: #3B82F6;
            color: #FFFFFF;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .quick-actions button:hover, .quick-actions button:focus {
            background: #2563EB;
            transform: translateY(-2px);
        }

        .quick-actions button:focus {
            outline: 2px solid #3B82F6;
            outline-offset: 2px;
        }

        .batch-table, .recent-orders-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #FFFFFF;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .batch-table th,
        .batch-table td,
        .recent-orders-table th,
        .recent-orders-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #E2E8F0;
        }

        .batch-table th,
        .recent-orders-table th {
            background: #3B82F6;
            color: #FFFFFF;
            font-weight: 500;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .batch-table tr:hover,
        .recent-orders-table tr:hover {
            background: #F7FAFC;
        }

        .batch-table tr:last-child td,
        .recent-orders-table tr:last-child td {
            border-bottom: none;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding-bottom: 16px;
            }
            .content {
                margin-left: 0;
                width: 100%;
                padding: 16px;
            }
            .metrics {
                grid-template-columns: 1fr;
            }
            .logo-container {
                flex-direction: column;
                gap: 4px;
            }
            .logo-img {
                width: 28px;
                height: 28px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .metric-card, .quick-actions button, .sidebar, .content {
                transition: none;
            }
        }

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
    </style>
</head>
<body>
    <nav class="sidebar" aria-label="Main navigation">
        <div class="logo-container">
            <img src="images/ww_logo.png" alt="Water World Logo" class="logo-img">
            <span class="logo-text">WATER WORLD</span>
        </div>
        <ul>
            <li class="active"><button type="button" data-page="dashboard">Dashboard</button></li>
            <li><button type="button" data-page="manage_orders.php">Manage Orders</button></li>
            <li><button type="button" data-page="status.php">Manage Status</button></li>
            <li><button type="button" data-page="daily_report.php">Daily Report</button></li>
            <li><button type="button" data-page="admin_transaction_history.php">Transaction History</button></li>
            <li><button type="button" data-page="admin_feedback.php">Feedback</button></li>
        </ul>
    </nav>
    <div class="sidebar-report" id="reportSidebar">
        <span class="close-btn" onclick="toggleReportSidebar()">×</span>
        <ul id="reportDates"></ul>
    </div>
    <main class="content" aria-label="Dashboard content" id="mainContent">
        <header class="header">
            <h1 class="title">Dashboard</h1>
            <div class="date-time" aria-live="polite">Date: <?php echo date('F d, Y'); ?> | Time: <?php echo date('h:i A T'); ?></div>
        </header>
        <section class="quick-actions" aria-label="Quick actions">
            <button type="button" data-page="dashboard" aria-label="Refresh dashboard data">Refresh Data</button>
        </section>
        <section class="metrics" aria-label="Key metrics">
            <div class="metric-card" data-color="blue" role="region" aria-label="Total Orders">
                <h3>Total Orders</h3>
                <p><?php echo htmlspecialchars($total_orders); ?></p>
            </div>
            <div class="metric-card" data-color="green" role="region" aria-label="Pending Orders">
                <h3>Pending Orders</h3>
                <p><?php echo htmlspecialchars($pending_orders); ?></p>
            </div>
            <div class="metric-card" data-color="purple" role="region" aria-label="Completed Orders">
                <h3>Completed Orders</h3>
                <p><?php echo htmlspecialchars($completed_orders); ?></p>
            </div>
            <div class="metric-card" data-color="teal" role="region" aria-label="Total Revenue">
                <h3>Total Revenue</h3>
                <p><?php echo '₱' . number_format($total_revenue, 2); ?></p>
            </div>
            <div class="metric-card" data-color="pink" role="region" aria-label="Average Order Value">
                <h3>Avg Order Value</h3>
                <p><?php echo '₱' . number_format($avg_order_value, 2); ?></p>
            </div>
            <div class="metric-card" data-color="gray" role="region" aria-label="Pending Deliveries">
                <h3>Pending Deliveries</h3>
                <p><?php echo htmlspecialchars($pending_deliveries); ?></p>
            </div>
        </section>
        <section class="metric-card" role="region" aria-label="Batch Overview">
            <h3>Batch Overview</h3>
            <table class="batch-table" aria-describedby="batch-table-desc">
                <caption id="batch-table-desc" class="sr-only">Overview of batches by vehicle type, batch number, order count, and status</caption>
                <thead>
                    <tr>
                        <th scope="col">Vehicle Type</th>
                        <th scope="col">Batch #</th>
                        <th scope="col">Orders</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $batches = [];
                    foreach ($batch_details as $batch) {
                        $key = $batch['vehicle_type'] . '-' . $batch['batch_number'];
                        $batches[$key] = $batch;
                    }
                    for ($i = 1; $i <= 3; $i++) {
                        foreach (['Tricycle', 'Car'] as $vehicle_type) {
                            $key = $vehicle_type . '-' . $i;
                            $batch = $batches[$key] ?? ['vehicle_type' => $vehicle_type, 'batch_number' => $i, 'order_count' => 0, 'batch_status' => 'N/A'];
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($batch['vehicle_type']) . "</td>";
                            echo "<td>#" . htmlspecialchars($batch['batch_number']) . "</td>";
                            echo "<td>" . htmlspecialchars($batch['order_count']) . "</td>";
                            echo "<td>" . htmlspecialchars($batch['batch_status']) . "</td>";
                            echo "</tr>";
                        }
                    }
                    ?>
                </tbody>
            </table>
        </section>
        <section class="metric-card" role="region" aria-label="Recent Orders">
            <h3>Recent Orders</h3>
            <table class="recent-orders-table" aria-describedby="recent-orders-table-desc">
                <caption id="recent-orders-table-desc" class="sr-only">List of recent orders with order ID, date, amount, and status</caption>
                <thead>
                    <tr>
                        <th scope="col">Order ID</th>
                        <th scope="col">Date</th>
                        <th scope="col">Amount</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['reference_id']); ?></td>
                            <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                            <td><?php echo '₱' . number_format($order['total_amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($order['order_status']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>
    <script>
        // Store the original dashboard content
        const originalContent = document.getElementById('mainContent').innerHTML;

        // Generalized function to load page content
        function loadPage(page, params = '') {
            const mainContent = document.getElementById('mainContent');
            const reportSidebar = document.getElementById('reportSidebar');

            // Update active state in sidebar
            document.querySelectorAll('.sidebar li').forEach(li => li.classList.remove('active'));
            const button = document.querySelector(`.sidebar button[data-page="${page}"]`);
            if (button) {
                button.parentElement.classList.add('active');
            }

            // Reset report sidebar visibility unless loading daily report
            if (page !== 'daily_report.php') {
                reportSidebar.classList.remove('active');
            }

            // Handle dashboard (restore original content)
            if (page === 'dashboard') {
                mainContent.innerHTML = originalContent;
                return;
            }

            // Fetch page content
            fetch(`${page}${params}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.text();
                })
                .then(data => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const content = doc.querySelector('.container') || doc.body;
                    const scripts = Array.from(doc.querySelectorAll('script')).map(script => script.textContent).join('\n');

                    // Update main content
                    mainContent.innerHTML = content.outerHTML || content.innerHTML;

                    // Execute scripts
                    const scriptElement = document.createElement('script');
                    scriptElement.textContent = scripts;
                    mainContent.appendChild(scriptElement);

                    // Fetch report dates for daily report
                    if (page === 'daily_report.php') {
                        fetchDates();
                    }
                })
                .catch(error => {
                    console.error(`Error fetching ${page}:`, error);
                    mainContent.innerHTML = `<p>Error loading ${page.replace('.php', '').replace('_', ' ')}. Please try again.</p>`;
                });
        }

        function toggleReportSidebar() {
            document.getElementById('reportSidebar').classList.toggle('active');
        }

        function showDailyReport(date = '<?php echo date('Y-m-d'); ?>') {
            loadPage('daily_report.php', `?date=${encodeURIComponent(date)}`);
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
                        a.onclick = () => showDailyReport(d);
                        li.appendChild(a);
                        ul.appendChild(li);
                    });
                })
                .catch(error => console.error('Error fetching dates:', error));
        }

        // Event listeners for sidebar buttons
        document.querySelectorAll('.sidebar button').forEach(button => {
            button.addEventListener('click', () => {
                const page = button.getAttribute('data-page');
                loadPage(page);
            });
        });

        // Event listener for refresh button
        document.querySelector('.quick-actions button[data-page="dashboard"]').addEventListener('click', () => {
            loadPage('dashboard');
        });

        // Load Bootstrap
        const bootstrapScript = document.createElement('script');
        bootstrapScript.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js';
        document.body.appendChild(bootstrapScript);
    </script>
</body>
</html>