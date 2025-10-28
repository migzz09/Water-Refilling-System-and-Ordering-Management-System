<?php
session_start();

// Include connect.php to set up $pdo
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

$customer_id = $_SESSION['customer_id'];

// Fetch all delivered orders for the customer (default view)
// Joined with order_types table to get transaction type
$sql = "
    SELECT 
        o.order_date, 
        o.delivery_date, 
        o.total_amount, 
        od.quantity, 
        cont.container_type, 
        cont.price AS container_price, 
        od.subtotal,
        ds.status_name AS delivery_status,
        ot.order_type_id AS order_type_id
    FROM orders o
    LEFT JOIN order_details od ON o.reference_id = od.reference_id
    LEFT JOIN containers cont ON od.container_id = cont.container_id
    LEFT JOIN batches b ON o.batch_id = b.batch_id
    LEFT JOIN deliveries d ON b.batch_id = d.batch_id
    LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
    LEFT JOIN order_types ot ON o.order_type_id = ot.order_type_id
    WHERE o.customer_id = :customer_id
    AND (ds.delivery_status_id = 3 OR ds.status_name = 'Delivered')
    ORDER BY o.order_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute(['customer_id' => $customer_id]);
$default_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX search request
if (isset($_GET['search'])) {
    $search_query = trim($_GET['search']);
    $sql = "
        SELECT 
            o.order_date, 
            o.delivery_date, 
            o.total_amount, 
            od.quantity, 
            cont.container_type, 
            cont.price AS container_price, 
            od.subtotal,
            ds.status_name AS delivery_status,
            ot.order_type_id AS order_type_id
        FROM orders o
    LEFT JOIN order_details od ON o.reference_id = od.reference_id
    LEFT JOIN containers cont ON od.container_id = cont.container_id
    LEFT JOIN batches b ON o.batch_id = b.batch_id
    LEFT JOIN deliveries d ON b.batch_id = d.batch_id
    LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
    LEFT JOIN order_types ot ON o.order_type_id = ot.order_type_id
    WHERE o.customer_id = :customer_id
    AND (ds.delivery_status_id = 3 OR ds.status_name = 'Delivered')
    ";

    $params = ['customer_id' => $customer_id];

    if (!empty($search_query)) {
        $search_length = strlen($search_query);
        $conditions = [];
        for ($i = 0; $i < $search_length; $i++) {
            $digit = $search_query[$i];
            if (is_numeric($digit)) {
                $position = $i + 1;
                $conditions[] = "SUBSTRING(o.reference_id FROM $position FOR 1) = :digit_$i";
                $params["digit_$i"] = $digit;
            }
        }
        if (!empty($conditions)) {
            $sql .= " AND (" . implode(" OR ", $conditions) . ")";
        }
    }

    $sql .= " ORDER BY o.order_date DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode(['transactions' => $transactions]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - WaterWorld</title>
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

        .container {
            padding: 2rem 5%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .title {
            text-align: center;
            font-size: 2rem;
            font-weight: bold;
            color: #008CBA;
            margin-bottom: 2rem;
        }

        .search-bar {
            margin-bottom: 2rem;
            text-align: center;
        }

        .search-bar input {
            padding: 0.75rem;
            width: 60%;
            max-width: 600px;
            border: 1px solid #ccc;
            border-radius: 30px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .search-bar input:focus {
            border-color: #008CBA;
            outline: none;
        }

        .transaction-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-height: 70vh;
            overflow-y: auto;
            padding-right: 1rem;
        }

        .date-header {
            background: #f0f8fb;
            padding: 0.5rem 1rem;
            font-weight: bold;
            color: #008CBA;
            border-bottom: 1px solid #e5e5e5;
        }

        .transaction-item {
            background: #ffffffcc;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.3s;
            border-bottom: 1px solid #e5e5e5;
        }

        .transaction-item:hover {
            background: #f0f8fb;
        }

        .transaction-details {
            flex-grow: 1;
        }

        .transaction-type {
            font-weight: bold;
            color: #008CBA;
            margin-bottom: 0.25rem;
        }

        .transaction-time {
            font-size: 0.8rem;
            color: #666;
        }

        .transaction-amount {
            font-weight: bold;
            color: #008CBA;
        }

        .receipt-modal {
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

        .receipt-content {
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

        .receipt-title {
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 1.5rem;
            color: #008CBA;
        }

        .receipt-details p {
            display: flex;
            justify-content: space-between;
            margin: 0.75rem 0;
        }

        .receipt-details p strong {
            color: #333;
        }

        .receipt-details .total {
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
                <li><a href="usertransaction_history.php">History</a></li>
                <li><a href="logout.php">Logout</a></li>
                <li class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <div class="search-bar">
            <input type="text" id="searchInput" placeholder="Type a digit to filter reference IDs" value="">
        </div>
        <div class="title">Transaction History</div>
        <section class="transaction-list">
            <?php if (empty($default_transactions)): ?>
                <p>No delivered transactions found.</p>
            <?php else: ?>
                <?php
                    $currentDate = '';
                ?>
                <?php foreach ($default_transactions as $transaction): ?>
                    <?php
                        $transactionDate = date('Y-m-d', strtotime($transaction['order_date']));
                        $dateHeader = date('F d, Y', strtotime($transaction['order_date']));
                        $time = date('h:i a', strtotime($transaction['order_date']));
                        $transactionType = '';
                        switch ($transaction['order_type_id']) {
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
                        if ($currentDate !== $transactionDate) {
                            echo '<div class="date-header">' . htmlspecialchars($dateHeader) . '</div>';
                            $currentDate = $transactionDate;
                        }
                    ?>
                    <div class="transaction-item" onclick="showReceipt('<?php echo htmlspecialchars(json_encode($transaction)); ?>')">
                        <div class="transaction-details">
                            <div class="transaction-type"><?php echo htmlspecialchars($transactionType); ?></div>
                            <div class="transaction-time"><?php echo $time; ?></div>
                        </div>
                        <div class="transaction-amount">₱<?php echo number_format($transaction['total_amount'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <div id="receipt-modal" class="receipt-modal">
            <div class="receipt-content">
                <span class="close" onclick="closeReceipt()">&times;</span>
                <div class="receipt-title">Transaction Details</div>
                <div class="receipt-details">
                    <p><strong>Type:</strong> <span id="receipt-type"></span></p>
                    <p><strong>Amount:</strong> <span id="receipt-amount"></span></p>
                    <p><strong>Delivery Date:</strong> <span id="receipt-delivery-date"></span></p>
                    <p><strong>Container Type:</strong> <span id="receipt-container-type"></span></p>
                    <p><strong>Quantity:</strong> <span id="receipt-quantity"></span></p>
                    <p><strong>Price per Container:</strong> <span id="receipt-container-price"></span></p>
                    <p><strong>Subtotal:</strong> <span id="receipt-subtotal"></span></p>
                    <p class="total"><strong>Total Amount:</strong> <span id="receipt-total"></span></p>
                    <p><strong>Status:</strong> <span id="receipt-status"></span></p>
                </div>
                <button class="back-btn" onclick="goBack()">Back</button>
            </div>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');
        const transactionList = document.querySelector('.transaction-list');
        const receiptModal = document.getElementById('receipt-modal');
        const receiptType = document.getElementById('receipt-type');
        const receiptAmount = document.getElementById('receipt-amount');
        const receiptDeliveryDate = document.getElementById('receipt-delivery-date');
        const receiptContainerType = document.getElementById('receipt-container-type');
        const receiptQuantity = document.getElementById('receipt-quantity');
        const receiptContainerPrice = document.getElementById('receipt-container-price');
        const receiptSubtotal = document.getElementById('receipt-subtotal');
        const receiptTotal = document.getElementById('receipt-total');
        const receiptStatus = document.getElementById('receipt-status');

        searchInput.addEventListener('input', function() {
            const searchQuery = this.value.trim();
            fetch(`usertransaction_history.php?search=${encodeURIComponent(searchQuery)}`)
                .then(response => response.json())
                .then(data => {
                    transactionList.innerHTML = '';
                    if (data.transactions.length === 0) {
                        transactionList.innerHTML = '<p>No delivered transactions found.</p>';
                    } else {
                        let currentDate = '';
                        data.transactions.forEach(transaction => {
                            const transactionDate = new Date(transaction.order_date).toISOString().split('T')[0];
                            const dateHeader = new Date(transaction.order_date).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                            const time = new Date(transaction.order_date).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).toLowerCase();
                            let transactionType = '';
                            switch (transaction.order_type_id) {
                                case 1:
                                    transactionType = 'Refill';
                                    break;
                                case 2:
                                    transactionType = 'Buy Container';
                                    break;
                                case 3:
                                    transactionType = 'Refill and Buy Container';
                                    break;
                                default:
                                    transactionType = 'Unknown';
                            }
                            if (currentDate !== transactionDate) {
                                transactionList.innerHTML += `<div class="date-header">${dateHeader}</div>`;
                                currentDate = transactionDate;
                            }
                            const item = document.createElement('div');
                            item.className = 'transaction-item';
                            item.onclick = () => showReceipt(JSON.stringify(transaction));
                            item.innerHTML = `
                                <div class="transaction-details">
                                    <div class="transaction-type">${transactionType}</div>
                                    <div class="transaction-time">${time}</div>
                                </div>
                                <div class="transaction-amount">₱${parseFloat(transaction.total_amount).toFixed(2)}</div>
                            `;
                            transactionList.appendChild(item);
                        });
                    }
                })
                .catch(error => console.error('Error fetching data:', error));
        });

        function showReceipt(transactionData) {
            const transaction = JSON.parse(transactionData);
            let receiptTypeText = '';
            switch (transaction.order_type_id) {
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
            receiptType.textContent = receiptTypeText;
            receiptAmount.textContent = `₱${parseFloat(transaction.total_amount).toFixed(2)}`;
            receiptDeliveryDate.textContent = new Date(transaction.delivery_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            receiptContainerType.textContent = transaction.container_type;
            receiptQuantity.textContent = transaction.quantity;
            receiptContainerPrice.textContent = `₱${parseFloat(transaction.container_price).toFixed(2)}`;
            receiptSubtotal.textContent = `₱${parseFloat(transaction.subtotal).toFixed(2)}`;
            receiptTotal.textContent = `₱${parseFloat(transaction.total_amount).toFixed(2)}`;
            receiptStatus.textContent = transaction.delivery_status;
            transactionList.style.display = 'none';
            receiptModal.style.display = 'flex';
        }

        function goBack() {
            receiptModal.style.display = 'none';
            transactionList.style.display = 'flex';
        }

        function closeReceipt() {
            receiptModal.style.display = 'none';
            transactionList.style.display = 'flex';
        }

        // Close receipt when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === receiptModal) {
                closeReceipt();
            }
        });

        // Add close functionality with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && receiptModal.style.display === 'flex') {
                closeReceipt();
            }
        });

        // Reveal on scroll animation
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

</body>
</html>