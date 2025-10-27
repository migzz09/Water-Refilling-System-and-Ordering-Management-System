<?php
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

    // Fetch detailed feedback for the side panel (only delivered orders)
    $sql = "
        SELECT 
            o.order_date, 
            o.total_amount, 
            od.quantity, 
            cont.container_type, 
            cont.price AS container_price, 
            od.subtotal,
            ds.status_name AS delivery_status,
            ot.order_type_id AS order_type_id,
            f.rating,
            f.feedback_text AS comment,
            f.feedback_date,
            f.reference_id
        FROM customer_feedback f
        LEFT JOIN orders o ON f.reference_id = o.reference_id
        LEFT JOIN order_details od ON o.reference_id = od.reference_id
        LEFT JOIN containers cont ON od.container_id = cont.container_id
        LEFT JOIN batches b ON o.batch_id = b.batch_id
        LEFT JOIN deliveries d ON b.batch_id = d.batch_id
        LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
        LEFT JOIN order_types ot ON o.order_type_id = ot.order_type_id
        WHERE ds.delivery_status_id = 3 OR ds.status_name = 'Delivered'
        ORDER BY f.feedback_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $all_feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
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

        .sidebar-report, .feedback-panel {
            position: fixed;
            top: 0;
            right: -250px;
            width: 250px;
            height: 100%;
            background: #2c3e50;
            color: #fff;
            transition: right 0.3s;
            padding-top: 60px;
            overflow-y: auto;
            z-index: 1050;
        }

        .sidebar-report.active, .feedback-panel.active {
            right: 0;
        }

        .sidebar-report ul, .feedback-items {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-report ul li, .feedback-items li {
            padding: 10px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-report ul li a, .feedback-items li a {
            color: #fff;
            text-decoration: none;
            display: block;
        }

        .sidebar-report ul li a:hover, .feedback-items li a:hover {
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

        .feedback-items {
            padding: 1.5rem;
        }

        .feedback-header {
            background: #3B82F6;
            color: #FFFFFF;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1;
        }

        .feedback-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
        }

        .close-feedback {
            background: none;
            border: none;
            color: #FFFFFF;
            font-size: 1.5rem;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .close-feedback:hover {
            transform: rotate(90deg);
        }

        .date-header {
            background: #F7FAFC;
            padding: 0.5rem 1rem;
            font-weight: 500;
            color: #3B82F6;
            border-bottom: 1px solid #E2E8F0;
        }

        .feedback-item {
            background: #FFFFFF;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #E2E8F0;
            margin-bottom: 0.5rem;
        }

        .feedback-item:hover {
            background: #F7FAFC;
        }

        .feedback-details {
            flex-grow: 1;
        }

        .feedback-type {
            font-weight: 500;
            color: #3B82F6;
            margin-bottom: 0.25rem;
        }

        .feedback-time {
            font-size: 0.8rem;
            color: #6B7280;
        }

        .feedback-rating {
            font-weight: 500;
            color: #3B82F6;
            display: flex;
            align-items: center;
        }

        .feedback-rating .star {
            color: #F59E0B;
            font-size: 1rem;
            margin-right: 0.2rem;
        }

        .floating-feedback {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #3B82F6;
            color: #FFFFFF;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
            z-index: 999;
        }

        .floating-feedback:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .feedback-icon {
            font-size: 1.8rem;
        }

        /* Modal & detailed feedback styles (from admin_userfeedback.php) */
        .feedback-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .feedback-content {
            background: #fff;
            padding: 2rem;
            border-radius: 10px;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            position: relative;
            font-size: 0.9rem;
            line-height: 1.5;
            border: 2px dashed #ccc;
            clip-path: polygon(0 0, 100% 0, 100% 85%, 95% 100%, 5% 100%, 0 85%);
            animation: slideIn 0.3s ease-out;
            background-image: linear-gradient(to bottom, #fff, #f9fbfc);
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .feedback-title {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 1.5rem;
            color: #008CBA;
        }

        .feedback-details p {
            display: flex;
            justify-content: space-between;
            margin: 0.75rem 0;
        }

        .feedback-details p strong {
            color: #333;
        }

        .feedback-details .comment {
            margin-top: 1rem;
            padding: 0.5rem;
            background: #f9f9f9;
            border-radius: 5px;
            word-wrap: break-word;
        }

        .feedback-details .total {
            font-weight: bold;
            border-top: 1px dashed #ccc;
            padding-top: 0.75rem;
            margin-top: 1rem;
        }

        .back-btn {
            display: block;
            margin: 1rem auto 0;
            padding: 0.5rem 1rem;
            background: #008CBA;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .back-btn:hover {
            background: #006b9a;
        }

        .close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #888;
        }

        .close:hover {
            color: #333;
        }

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
            .sidebar-report, .feedback-panel {
                width: 100%;
                right: -100%;
            }
            .sidebar-report.active, .feedback-panel.active {
                right: 0;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .metric-card, .quick-actions button, .sidebar, .content, .sidebar-report, .feedback-panel {
                transition: none;
            }
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
            <li class="active"><button type="button" onclick="showDashboard()">Dashboard</button></li>
            <li><button type="button" onclick="showManageOrders()">Manage Orders</button></li>
            <li><button type="button" onclick="showArchivedOrders()">Archived Orders</button></li>
            <li><a href="status.php">Manage Status</a></li>
            <li><button type="button" onclick="showDailyReport()">Daily Report</button></li>
            <li><button type="button" onclick="toggleFeedbackPanel()">Feedback</button></li>
        </ul>
    </nav>
    <div class="sidebar-report" id="reportSidebar">
        <span class="close-btn" onclick="toggleReportSidebar()">&times;</span>
        <ul id="reportDates"></ul>
    </div>
    <div class="feedback-panel" id="feedbackPanel">
        <div class="feedback-header">
            <h2>All Feedback</h2>
            <button class="close-feedback" onclick="toggleFeedbackPanel()">&times;</button>
        </div>
        <div class="feedback-items">
            <?php if (empty($all_feedback)): ?>
                <p>No feedback found.</p>
            <?php else: ?>
                <?php
                    $currentDate = '';
                ?>
                <?php foreach ($all_feedback as $feedback): ?>
                    <?php
                        $feedbackDate = date('Y-m-d', strtotime($feedback['feedback_date']));
                        $dateHeader = date('F d, Y', strtotime($feedback['feedback_date']));
                        $time = date('h:i a', strtotime($feedback['feedback_date']));
                        $transactionType = '';
                        switch ($feedback['order_type_id']) {
                            case 1:
                                $transactionType = 'Refill';
                                break;
                            case 2:
                                $transactionType = 'Buy Container';
                                break;
                            case 3:
                                $transactionType = 'Refill and Buy Container';
                                break;
                            default:
                                $transactionType = 'Unknown';
                        }
                        if ($currentDate !== $feedbackDate) {
                            echo '<div class="date-header">' . htmlspecialchars($dateHeader) . '</div>';
                            $currentDate = $feedbackDate;
                        }
                        // Prepare a clean JSON payload for the client-side modal
                        $payload = htmlspecialchars(json_encode($feedback), ENT_COMPAT, 'UTF-8');
                    ?>
                    <div class="feedback-item" onclick="showFeedback('<?php echo $payload; ?>')">
                        <div class="feedback-details">
                            <div class="feedback-type"><?php echo htmlspecialchars($transactionType); ?></div>
                            <div class="feedback-time"><?php echo $time; ?></div>
                        </div>
                        <div class="feedback-rating">
                            <?php for ($i = 0; $i < $feedback['rating']; $i++): ?>
                                <span class="star">★</span>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <!-- Feedback details modal -->
        <div id="feedback-modal" class="feedback-modal">
            <div class="feedback-content">
                <span class="close" onclick="closeFeedback()">&times;</span>
                <div class="feedback-title">Feedback Details</div>
                <div class="feedback-details">
                    <p><strong>Type:</strong> <span id="feedback-type"></span></p>
                    <p><strong>Amount:</strong> <span id="feedback-amount"></span></p>
                    <p><strong>Feedback Date:</strong> <span id="feedback-date"></span></p>
                    <p><strong>Container Type:</strong> <span id="feedback-container-type"></span></p>
                    <p><strong>Quantity:</strong> <span id="feedback-quantity"></span></p>
                    <p><strong>Price per Container:</strong> <span id="feedback-container-price"></span></p>
                    <p><strong>Subtotal:</strong> <span id="feedback-subtotal"></span></p>
                    <p><strong>Rating:</strong> <span id="feedback-rating"></span></p>
                    <p class="comment"><strong>Comment:</strong> <span id="feedback-comment"></span></p>
                    <p class="total"><strong>Total Amount:</strong> <span id="feedback-total"></span></p>
                    <p><strong>Status:</strong> <span id="feedback-status"></span></p>
                </div>
                <button class="back-btn" onclick="goBack()">Back</button>
            </div>
        </div>
    </div>
    <main class="content" aria-label="Dashboard content" id="mainContent">
        <header class="header">
            <h1 class="title">Dashboard</h1>
            <div class="date-time" aria-live="polite">Date: <?php echo date('F d, Y'); ?> | Time: <?php echo date('h:i A T'); ?></div>
        </header>
        <section class="quick-actions" aria-label="Quick actions">
            <button type="button" onclick="location.reload();" aria-label="Refresh dashboard data">Refresh Data</button>
            <button type="button" onclick="toggleFeedbackPanel()" aria-label="View feedback">View Feedback</button>
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
    <div class="floating-feedback" onclick="toggleFeedbackPanel()">
        <div class="feedback-icon">📝</div>
    </div>
    <script>
        let originalContent = document.getElementById('mainContent').innerHTML;

        function showDashboard() {
            const mainContent = document.getElementById('mainContent');
            mainContent.innerHTML = originalContent;
            document.getElementById('reportSidebar').classList.remove('active');
            document.getElementById('feedbackPanel').classList.remove('active');
            document.querySelector('.sidebar li.active').classList.remove('active');
            document.querySelector('.sidebar li button[onclick="showDashboard()"]').parentElement.classList.add('active');
        }

        function toggleReportSidebar() {
            document.getElementById('reportSidebar').classList.toggle('active');
            document.getElementById('feedbackPanel').classList.remove('active');
            document.querySelector('.sidebar li.active').classList.remove('active');
            document.querySelector('.sidebar li button[onclick="showDailyReport()"]').parentElement.classList.add('active');
        }

        function toggleFeedbackPanel() {
            document.getElementById('feedbackPanel').classList.toggle('active');
            document.getElementById('reportSidebar').classList.remove('active');
            document.querySelector('.sidebar li.active').classList.remove('active');
            document.querySelector('.sidebar li button[onclick="toggleFeedbackPanel()"]').parentElement.classList.add('active');
        }

        function showDailyReport(date = '<?php echo date('Y-m-d'); ?>') {
            fetch('daily_report.php?date=' + encodeURIComponent(date))
                .then(response => response.text())
                .then(data => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data, 'text/html');
                    const content = doc.querySelector('.container').outerHTML;
                    const scripts = Array.from(doc.querySelectorAll('script')).map(script => script.textContent).join('\n');
                    document.getElementById('mainContent').innerHTML = content;

                    const scriptElement = document.createElement('script');
                    scriptElement.textContent = scripts;
                    document.getElementById('mainContent').appendChild(scriptElement);

                    document.querySelector('.sidebar li.active').classList.remove('active');
                    document.querySelector('.sidebar li button[onclick="showDailyReport()"]').parentElement.classList.add('active');

                    fetchDates();
                })
                .catch(error => {
                    console.error('Error fetching daily report:', error);
                    document.getElementById('mainContent').innerHTML = '<p>Error loading daily report. Please try again.</p>';
                });
        }

        function showManageOrders(searchQuery = '') {
    const url = searchQuery
        ? 'manage_orders.php?search=' + encodeURIComponent(searchQuery)
        : 'manage_orders.php';

    fetch(url)
        .then(response => response.text())
        .then(data => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const content = doc.querySelector('.container-fluid')?.outerHTML || '<p>Unable to load Manage Orders.</p>';
            document.getElementById('mainContent').innerHTML = content;

            const searchForm = document.querySelector('#mainContent form[method="get"]');
            if (searchForm) {
                searchForm.addEventListener('submit', function (e) {
                    e.preventDefault();
                    const query = this.querySelector('input[name="search"]').value;
                    showManageOrders(query);
                });
            }

            const clearBtn = document.querySelector('#mainContent a.btn-link');
            if (clearBtn) {
                clearBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    showManageOrders();
                });
            }

            const activeItem = document.querySelector('.sidebar li.active');
            if (activeItem) activeItem.classList.remove('active');
            document.querySelector('.sidebar li button[onclick="showManageOrders()"]').parentElement.classList.add('active');
        })
        .catch(error => {
            console.error('Error loading Manage Orders:', error);
            document.getElementById('mainContent').innerHTML = '<p>Error loading Manage Orders page.</p>';
        });
}

        function showArchivedOrders() {
        fetch('archived_orders.php')
            .then(response => response.text())
            .then(data => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const content = doc.querySelector('.container-fluid')?.outerHTML || '<p>Unable to load Archived Orders.</p>';
                document.getElementById('mainContent').innerHTML = content;

                const activeItem = document.querySelector('.sidebar li.active');
                if (activeItem) activeItem.classList.remove('active');
                document.querySelector('.sidebar li button[onclick="showArchivedOrders()"]').parentElement.classList.add('active');
            })
            .catch(error => {
                console.error('Error loading Archived Orders:', error);
                document.getElementById('mainContent').innerHTML = '<p>Error loading Archived Orders page.</p>';
            });
        }
        function searchManageOrders(query = '') {
        const url = 'manage_orders.php' + (query ? '?search=' + encodeURIComponent(query) : '');
        fetch(url)
        .then(response => response.text())
        .then(data => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(data, 'text/html');
            const content = doc.querySelector('.container-fluid')?.outerHTML || '<p>Unable to load Manage Orders.</p>';
            document.getElementById('mainContent').innerHTML = content;
        })
        .catch(error => {
            console.error('Error fetching search results:', error);
            document.getElementById('mainContent').innerHTML = '<p>Error loading search results.</p>';
        });
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

        // Feedback modal controls and rendering
        const feedbackModal = document.getElementById('feedback-modal');
        const feedbackType = document.getElementById('feedback-type');
        const feedbackAmount = document.getElementById('feedback-amount');
        const feedbackDate = document.getElementById('feedback-date');
        const feedbackContainerType = document.getElementById('feedback-container-type');
        const feedbackQuantity = document.getElementById('feedback-quantity');
        const feedbackContainerPrice = document.getElementById('feedback-container-price');
        const feedbackSubtotal = document.getElementById('feedback-subtotal');
        const feedbackRating = document.getElementById('feedback-rating');
        const feedbackComment = document.getElementById('feedback-comment');
        const feedbackTotal = document.getElementById('feedback-total');
        const feedbackStatus = document.getElementById('feedback-status');

        function showFeedback(feedbackData) {
            try {
                const feedback = JSON.parse(feedbackData);
                let receiptTypeText = '';
                switch (parseInt(feedback.order_type_id)) {
                    case 1:
                        receiptTypeText = 'Refill';
                        break;
                    case 2:
                        receiptTypeText = 'Buy Container';
                        break;
                    case 3:
                        receiptTypeText = 'Refill and Buy Container';
                        break;
                    default:
                        receiptTypeText = 'Unknown';
                }
                feedbackType.textContent = receiptTypeText;
                feedbackAmount.textContent = feedback.total_amount ? `₱${parseFloat(feedback.total_amount).toFixed(2)}` : '-';
                feedbackDate.textContent = feedback.feedback_date ? new Date(feedback.feedback_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '-';
                feedbackContainerType.textContent = feedback.container_type || '-';
                feedbackQuantity.textContent = feedback.quantity || '-';
                feedbackContainerPrice.textContent = feedback.container_price ? `₱${parseFloat(feedback.container_price).toFixed(2)}` : '-';
                feedbackSubtotal.textContent = feedback.subtotal ? `₱${parseFloat(feedback.subtotal).toFixed(2)}` : '-';
                feedbackRating.textContent = feedback.rating ? '★'.repeat(feedback.rating) : '-';
                feedbackComment.textContent = feedback.comment || 'No comment provided';
                feedbackTotal.textContent = feedback.total_amount ? `₱${parseFloat(feedback.total_amount).toFixed(2)}` : '-';
                feedbackStatus.textContent = feedback.delivery_status || '-';
                document.querySelector('.feedback-items').style.display = 'none';
                feedbackModal.style.display = 'flex';
            } catch (err) {
                console.error('Invalid feedback payload', err);
            }
        }

        function goBack() {
            feedbackModal.style.display = 'none';
            document.querySelector('.feedback-items').style.display = 'block';
        }

        function closeFeedback() {
            feedbackModal.style.display = 'none';
            document.querySelector('.feedback-items').style.display = 'block';
        }

        // Close feedback modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === feedbackModal) {
                closeFeedback();
            }
        });

        // Close feedback modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && feedbackModal.style.display === 'flex') {
                closeFeedback();
            }
        });

        const bootstrapScript = document.createElement('script');
        bootstrapScript.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js';
        document.body.appendChild(bootstrapScript);
    </script>
</body>
</html>
