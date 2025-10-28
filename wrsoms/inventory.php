<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch inventory data, filtering for Round and Rectangular
$stmt = $pdo->prepare("
    SELECT i.container_id, c.container_type, i.stock, c.price,
           CASE 
               WHEN i.stock <= 20 THEN 'low'
               WHEN i.stock <= 50 THEN 'medium'
               ELSE 'high'
           END as status
    FROM inventory i
    JOIN containers c ON i.container_id = c.container_id
    WHERE c.container_type IN ('Round', 'Rectangular')
    ORDER BY c.container_type, i.stock ASC
");
$stmt->execute();
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle restock request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_submit'])) {
    $container_id = filter_input(INPUT_POST, 'container_id', FILTER_VALIDATE_INT);
    $restock_quantity = filter_input(INPUT_POST, 'restock_quantity', FILTER_VALIDATE_INT);
    if ($container_id && $restock_quantity > 0) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE inventory SET stock = stock + ? WHERE container_id = ?");
            $stmt->execute([$restock_quantity, $container_id]);
            $pdo->commit();
            $_SESSION['notification'] = "Successfully restocked " . $restock_quantity . " units of the selected container.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Restock failed: " . $e->getMessage();
        }
    }
    header("Location: inventory.php");
    exit;
}

// Analytics: Total stock value, low stock items, average stock
$total_stock_value = 0;
$low_stock_count = 0;
$total_containers = 0;
foreach ($inventory as $item) {
    $total_stock_value += $item['stock'] * $item['price'];
    if ($item['status'] === 'low') $low_stock_count++;
    $total_containers++;
}
$average_stock = $total_containers > 0 ? round($total_stock_value / $total_containers, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - WaterWorld</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f9fbfc;
            color: #333;
            line-height: 1.6;
            overflow-x: hidden;
        }

        header {
            background: #ffffffcc;
            backdrop-filter: blur(10px);
            padding: 1rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e5e5e5;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #008CBA;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
        }

        nav ul li a {
            text-decoration: none;
            color: #333;
            font-weight: 500;
            position: relative;
            padding-bottom: 4px;
            transition: color 0.3s;
        }

        nav ul li a::after {
            content: "";
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: #008CBA;
            transition: width 0.3s;
        }

        nav ul li a:hover {
            color: #008CBA;
        }

        nav ul li a:hover::after {
            width: 100%;
        }

        .welcome {
            color: #008CBA;
            font-size: 1rem;
            font-weight: 500;
            margin-left: 1rem;
        }

        section {
            opacity: 0;
            transform: translateY(30px);
            transition: all 1s ease;
        }

        section.show {
            opacity: 1;
            transform: translateY(0);
        }

        .inventory {
            padding: 4rem 5%;
            text-align: center;
        }

        .inventory h1 {
            font-size: 2.2rem;
            margin-bottom: 1rem;
            color: #008CBA;
            animation: slideDown 1.5s ease forwards;
        }

        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .analytic-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            text-align: center;
            animation: fadeIn 0.8s ease forwards;
        }

        .analytic-card h3 {
            color: #008CBA;
            margin-bottom: 1rem;
        }

        .analytic-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }

        .analytic-card .low-stock {
            color: #d32f2f;
        }

        .inventory-table-container {
            overflow-x: auto;
            margin: 2rem 0;
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }

        .inventory-table th {
            background: #f8f9fa;
            color: #333;
            font-weight: bold;
        }

        .inventory-table tr:hover {
            background: #f0f8ff;
        }

        .status-low {
            color: #d32f2f;
            font-weight: bold;
        }

        .status-medium {
            color: #ff9800;
            font-weight: bold;
        }

        .status-high {
            color: #4caf50;
            font-weight: bold;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 2rem;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            z-index: 1000;
            animation: slideInRight 0.5s ease;
        }

        .notification.success {
            background: #4caf50;
        }

        .notification.error {
            background: #d32f2f;
        }

        .restock-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin: 2rem 0;
            animation: fadeIn 0.8s ease forwards;
        }

        .restock-section h2 {
            color: #008CBA;
            margin-bottom: 1rem;
        }

        .restock-section form {
            display: flex;
            gap: 1rem;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
        }

        .restock-section select,
        .restock-section input[type="number"] {
            padding: 0.5rem;
            border: 1px solid #e5e5e5;
            border-radius: 5px;
            font-size: 1rem;
        }

        .restock-section button {
            background: #008CBA;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .restock-section button:hover {
            background: #005f80;
        }

        .back-button {
            text-align: center;
            margin: 2rem 0;
        }

        .back-button a {
            color: #008CBA;
            text-decoration: none;
            font-weight: 500;
        }

        .back-button a:hover {
            color: #005f80;
            text-decoration: underline;
        }

        footer {
            background: #008CBA;
            color: white;
            text-align: center;
            padding: 2rem 5%;
            margin-top: 2rem;
        }

        footer .socials {
            margin: 1rem 0;
        }

        footer .socials a {
            margin: 0 10px;
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
        }

        footer .socials a:hover {
            color: #cceeff;
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">WaterWorld</div>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="order_placement.php">Order</a></li>
                <li><a href="order_tracking.php">Track</a></li>
                <li><a href="inventory.php">Inventory</a></li>
                <li><a href="logout.php">Logout</a></li>
                <li class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</li>
            </ul>
        </nav>
    </header>

    <section class="inventory show">
        <h1>Inventory Management</h1>

        <?php if (isset($_SESSION['notification'])): ?>
            <div class="notification success"><?php echo htmlspecialchars($_SESSION['notification']); ?></div>
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="notification error"><?php echo htmlspecialchars($_SESSION['error']); ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="analytics-grid">
            <div class="analytic-card">
                <h3>Total Stock Value</h3>
                <div class="value">₱<?php echo number_format($total_stock_value, 2); ?></div>
            </div>
            <div class="analytic-card">
                <h3>Average Stock</h3>
                <div class="value">₱<?php echo number_format($average_stock, 2); ?></div>
            </div>
            <div class="analytic-card">
                <h3>Low Stock Items</h3>
                <div class="value <?php echo $low_stock_count > 0 ? 'low-stock' : ''; ?>"><?php echo $low_stock_count; ?></div>
            </div>
            <div class="analytic-card">
                <h3>Total Containers</h3>
                <div class="value"><?php echo $total_containers; ?></div>
            </div>
        </div>

        <div class="inventory-table-container">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th>Container Type</th>
                        <th>Current Stock</th>
                        <th>Price (₱)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['container_type']); ?></td>
                            <td><?php echo $item['stock']; ?></td>
                            <td><?php echo number_format($item['price'], 2); ?></td>
                            <td>
                                <span class="status-<?php echo $item['status']; ?>">
                                    <?php echo ucfirst($item['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="restock-section">
            <h2>Restock Inventory</h2>
            <form method="POST" action="">
                <select name="container_id" required>
                    <?php foreach ($inventory as $item): ?>
                        <option value="<?php echo $item['container_id']; ?>">
                            <?php echo htmlspecialchars($item['container_type']) . " (Stock: " . $item['stock'] . ")"; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="restock_quantity" min="1" placeholder="Quantity to Restock" required>
                <button type="submit" name="restock_submit">Restock</button>
            </form>
        </div>

        <div class="back-button">
            <a href="index.php">Back to Home</a>
        </div>
    </section>

    <footer>
        <p>&copy; 2025 WaterWorld Water Station. All rights reserved.</p>
        <div class="socials">
            <a href="#">Facebook</a>
            <a href="#">Twitter</a>
            <a href="#">Instagram</a>
        </div>
    </footer>

    <script>
        const sections = document.querySelectorAll("section");
        const revealOnScroll = () => {
            const triggerBottom = window.innerHeight * 0.85;
            sections.forEach(section => {
                const sectionTop = section.getBoundingClientRect().top;
                if (sectionTop < triggerBottom) {
                    section.classList.add("show");
                }
            });
        };
        window.addEventListener("scroll", revealOnScroll);
        revealOnScroll();
    </script>
