<?php
session_start();
require_once 'connect.php';

// NCR cities and barangays
$ncr_cities = [
    'Taguig' => [
        'Bagumbayan', 'Bambang', 'Calzada', 'Central Bicutan', 'Central Signal Village',
        'Fort Bonifacio', 'Hagonoy', 'Ibayo-Tipas', 'Katuparan', 'Ligid-Tipas',
        'Lower Bicutan', 'Maharlika Village', 'Napindan', 'New Lower Bicutan',
        'North Daang Hari', 'North Signal Village', 'Palingon', 'Pinagsama',
        'San Miguel', 'Santa Ana', 'South Daang Hari', 'South Signal Village',
        'Tanyag', 'Tuktukan', 'Upper Bicutan', 'Ususan', 'Wawa', 'Western Bicutan',
        'Comembo', 'Cembo', 'South Cembo', 'East Rembo', 'West Rembo', 'Pembo',
        'Pitogo', 'Post Proper Northside', 'Post Proper Southside', 'Rizal'
    ],
    'Quezon City' => [
        'Bagong Pag-asa', 'Batasan Hills', 'Commonwealth', 'Holy Spirit', 'Payatas'
    ],
    'Manila' => [
        'Tondo', 'Binondo', 'Ermita', 'Malate', 'Paco'
    ],
    'Makati' => [
        'Bangkal', 'Bel-Air', 'Magallanes', 'Pio del Pilar', 'San Lorenzo'
    ],
    'Pasig' => [
        'Bagong Ilog', 'Oranbo', 'San Antonio', 'Santa Lucia', 'Ugong'
    ],
    'Pateros' => [
        'Aguho', 'Martyrs', 'San Roque', 'Santa Ana'
    ]
];

// Function to generate a random 5-6 digit reference_id
function generateReferenceId($pdo) {
    $length = rand(5, 6);
    do {
        $reference_id = str_pad(rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE reference_id = ?");
        $stmt->execute([$reference_id]);
        $exists = $stmt->fetchColumn();
    } while ($exists);
    return $reference_id;
}

// Function to assign batch based on city and vehicle capacity
function assignBatch($pdo, $city, $delivery_date) {
    $vehicle_type = ($city === 'Taguig') ? 'Tricycle' : 'Car';
    $capacity = 5; // Universal capacity for shared batches (Tricycle's limit)
    $batch_date = $delivery_date; // Use delivery date for limit check

    // Debug: Log input parameters
    error_log("assignBatch called: city=$city, delivery_date=$batch_date, vehicle_type=$vehicle_type, capacity=$capacity");

    // Check number of batches for the delivery date
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM batches 
        WHERE DATE(batch_date) = ?
    ");
    $stmt->execute([$batch_date]);
    $batch_count = $stmt->fetchColumn();
    error_log("Batch count for $batch_date: $batch_count");

    // Debug: Log all batches for the delivery date
    $stmt = $pdo->prepare("
        SELECT batch_id, vehicle_type, DATE(batch_date) AS batch_date
        FROM batches 
        WHERE DATE(batch_date) = ?
    ");
    $stmt->execute([$batch_date]);
    $all_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("All batches for $batch_date: " . json_encode($all_batches));

    // If 3 batches already exist for the delivery date, check if any have space
    if ($batch_count >= 3) {
        $stmt = $pdo->prepare("
            SELECT b.batch_id, COALESCE(SUM(od.quantity), 0) AS total_quantity
            FROM batches b
            LEFT JOIN orders o ON b.batch_id = o.batch_id
            LEFT JOIN order_details od ON o.reference_id = od.reference_id
            WHERE DATE(b.batch_date) = ?
            GROUP BY b.batch_id
            HAVING COALESCE(SUM(od.quantity), 0) < ?
            ORDER BY b.batch_id ASC
            LIMIT 1
        ");
        $stmt->execute([$batch_date, $capacity]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("Checking for space in 3 batches: " . ($batch ? "Found batch_id={$batch['batch_id']}, total_quantity={$batch['total_quantity']}" : "No batch with space"));

        if ($batch) {
            return $batch['batch_id'];
        } else {
            throw new Exception("Cannot assign batch: Limit of 3 batches reached for $batch_date and all are full.");
        }
    }

    // Find existing batch with space for the delivery date
    $stmt = $pdo->prepare("
        SELECT b.batch_id, COALESCE(SUM(od.quantity), 0) AS total_quantity
        FROM batches b
        LEFT JOIN orders o ON b.batch_id = o.batch_id
        LEFT JOIN order_details od ON o.reference_id = od.reference_id
        WHERE DATE(b.batch_date) = ?
        GROUP BY b.batch_id
        HAVING COALESCE(SUM(od.quantity), 0) < ?
        ORDER BY b.batch_id ASC
        LIMIT 1
    ");
    $stmt->execute([$batch_date, $capacity]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("Checking existing batches: " . ($batch ? "Found batch_id={$batch['batch_id']}, total_quantity={$batch['total_quantity']}" : "No batch with space"));

    if ($batch) {
        return $batch['batch_id'];
    } else {
        // Create new batch for the delivery date
        $stmt = $pdo->prepare("
            INSERT INTO batches (vehicle, vehicle_type, batch_status_id, notes, batch_date) 
            VALUES (?, ?, 1, 'Auto-created batch', ?)
        ");
        $vehicle_name = ($vehicle_type === 'Tricycle') ? 'Tricycle #' . rand(100, 999) : 'Car #' . rand(100, 999);
        $stmt->execute([$vehicle_name, $vehicle_type, $batch_date]);
        $new_batch_id = $pdo->lastInsertId();
        error_log("Created new $vehicle_type batch: batch_id=$new_batch_id");
        return $new_batch_id;
    }
}

// Handle form submission
$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING) ?: '';
    $middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING) ?: null;
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING) ?: '';
    $customer_contact = filter_input(INPUT_POST, 'customer_contact', FILTER_SANITIZE_STRING) ?: '';
    $street = filter_input(INPUT_POST, 'street', FILTER_SANITIZE_STRING) ?: '';
    $barangay = filter_input(INPUT_POST, 'barangay', FILTER_SANITIZE_STRING) ?: '';
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING) ?: '';
    $province = filter_input(INPUT_POST, 'province', FILTER_SANITIZE_STRING) ?: '';
    $order_type_id = filter_input(INPUT_POST, 'order_type_id', FILTER_VALIDATE_INT);
    $container_id = filter_input(INPUT_POST, 'container_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $delivery_date = filter_input(INPUT_POST, 'delivery_date', FILTER_SANITIZE_STRING) ?: '';

    if (empty($first_name) || empty($last_name)) $errors[] = "First name and last name are required.";
    if (empty($customer_contact) || !preg_match('/^[0-9]{10,11}$/', $customer_contact)) $errors[] = "Invalid contact number (10-11 digits required).";
    if (empty($street)) $errors[] = "Street is required.";
    if (empty($barangay) || !in_array($barangay, $ncr_cities[$city] ?? [])) $errors[] = "Invalid barangay for selected city.";
    if (empty($city) || !array_key_exists($city, $ncr_cities)) $errors[] = "Invalid city.";
    if (empty($province) || $province !== 'Metro Manila') $errors[] = "Province must be Metro Manila.";
    if (!$order_type_id) $errors[] = "Invalid order type.";
    if (!$container_id) $errors[] = "Invalid container selection.";
    if (!$quantity || $quantity < 1) $errors[] = "Quantity must be at least 1.";
    if (empty($delivery_date) || strtotime($delivery_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Delivery date must be today or later.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE customer_contact = ?");
            $stmt->execute([$customer_contact]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer) {
                $customer_id = $customer['customer_id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO customers (first_name, middle_name, last_name, customer_contact, street, barangay, city, province, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$first_name, $middle_name, $last_name, $customer_contact, $street, $barangay, $city, $province]);
                $customer_id = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("SELECT container_type, price FROM containers WHERE container_id = ?");
            $stmt->execute([$container_id]);
            $container = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$container) {
                $errors[] = "Container not found.";
            } else {
                $subtotal = $container['price'] * $quantity;
                $reference_id = generateReferenceId($pdo);
                $batch_id = assignBatch($pdo, $city, $delivery_date);

                $stmt = $pdo->prepare("INSERT INTO orders (reference_id, customer_id, order_type_id, batch_id, order_date, delivery_date, order_status_id, total_amount) VALUES (?, ?, ?, ?, NOW(), ?, 1, ?)");
                $stmt->execute([$reference_id, $customer_id, $order_type_id, $batch_id, $delivery_date, $subtotal]);

                $stmt = $pdo->prepare("INSERT INTO order_details (reference_id, container_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
                $stmt->execute([$reference_id, $container_id, $quantity, $subtotal]);

                $stmt = $pdo->prepare("SELECT type_name FROM order_types WHERE order_type_id = ?");
                $stmt->execute([$order_type_id]);
                $order_type = $stmt->fetchColumn();

                $_SESSION['receipt_data'] = [
                    'reference_id' => $reference_id,
                    'customer_name' => $first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name,
                    'customer_contact' => $customer_contact,
                    'address' => "$street, $barangay, $city, $province",
                    'order_type' => $order_type,
                    'container' => $container['container_type'],
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'delivery_date' => $delivery_date,
                    'order_date' => date('Y-m-d H:i:s'),
                    'vehicle_type' => ($city === 'Taguig') ? 'Tricycle' : 'Car',
                    'batch_id' => $batch_id
                ];

                $success = "Order added successfully!";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Clear receipt data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_receipt'])) {
    unset($_SESSION['receipt_data']);
    header("Location: index.php");
    exit;
}

// Fetch containers and order types
$containers = $pdo->query("SELECT container_id, container_type, price FROM containers")->fetchAll(PDO::FETCH_ASSOC);
$order_types = $pdo->query("SELECT order_type_id, type_name FROM order_types")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place an Order - WaterWorld</title>
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

        section {
            opacity: 0;
            transform: translateY(30px);
            transition: all 1s ease;
        }

        section.show {
            opacity: 1;
            transform: translateY(0);
        }

        .order {
            padding: 4rem 5%;
            text-align: center;
        }

        .order h1 {
            font-size: 2.2rem;
            margin-bottom: 1rem;
            color: #008CBA;
            animation: slideDown 1.5s ease forwards;
        }

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 2rem auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-container h3 {
            grid-column: 1 / -1;
            color: #008CBA;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .form-container label {
            display: block;
            margin: 10px 0 5px;
            font-weight: 500;
            color: #333;
            text-align: left;
        }

        .form-container input,
        .form-container select {
            width: 100%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-container input:focus,
        .form-container select:focus {
            border-color: #008CBA;
            outline: none;
        }

        .form-container input[readonly] {
            background: #f0f0f0;
        }

        .form-container button {
            grid-column: 1 / -1;
            background: #008CBA;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s, transform 0.3s;
        }

        .form-container button:hover {
            background: #005f80;
            transform: scale(1.05);
        }

        .success {
            color: #2e7d32;
            margin: 1rem 0;
            font-weight: 500;
            text-align: center;
            grid-column: 1 / -1;
        }

        .error {
            color: #d32f2f;
            margin: 1rem 0;
            font-weight: 500;
            text-align: center;
            grid-column: 1 / -1;
        }

        .error ul {
            list-style: none;
            padding: 0;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
            position: relative;
            animation: slideUp 0.5s ease forwards;
        }

        .modal-content h2 {
            color: #008CBA;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            text-align: center;
        }

        .modal-content .cart-item {
            border-bottom: 1px solid #e5e5e5;
            padding: 1rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-content .cart-item:last-child {
            border-bottom: none;
        }

        .modal-content .cart-total {
            font-weight: bold;
            color: #008CBA;
            margin-top: 1rem;
            text-align: right;
        }

        .modal-content .warning {
            color: #d32f2f;
            font-weight: 500;
            margin: 1rem 0;
            text-align: center;
        }

        .modal-content button {
            background: #008CBA;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 30px;
            font-weight: bold;
            cursor: pointer;
            display: block;
            margin: 1rem auto;
            transition: background 0.3s, transform 0.3s;
        }

        .modal-content button:hover {
            background: #005f80;
            transform: scale(1.05);
        }

        .back-button {
            text-align: center;
            margin: 2rem 0;
        }

        .back-button a {
            color: #008CBA;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
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
            transition: color 0.3s;
        }

        footer .socials a:hover {
            color: #cceeff;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
                <li><a href="#">Contact</a></li>
            </ul>
        </nav>
    </header>

    <section class="order show">
        <h1>Place Your Order</h1>

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

        <div class="form-container">
            <h3>Customer Details</h3>
            <label for="first_name">First Name</label>
            <input type="text" name="first_name" required>

            <label for="middle_name">Middle Name (Optional)</label>
            <input type="text" name="middle_name">

            <label for="last_name">Last Name</label>
            <input type="text" name="last_name" required>

            <label for="customer_contact">Contact Number</label>
            <input type="text" name="customer_contact" required>

            <label for="street">Street</label>
            <input type="text" name="street" required>

            <label for="city">City</label>
            <select name="city" id="city" required onchange="updateBarangays()">
                <option value="">Select City</option>
                <?php foreach (array_keys($ncr_cities) as $city): ?>
                    <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="barangay">Barangay</label>
            <select name="barangay" id="barangay" required>
                <option value="">Select Barangay</option>
            </select>

            <label for="province">Province</label>
            <input type="text" name="province" value="Metro Manila" readonly>

            <h3>Order Details</h3>
            <label for="order_type_id">Order Type</label>
            <select name="order_type_id" required>
                <option value="">Select Order Type</option>
                <?php foreach ($order_types as $type): ?>
                    <option value="<?php echo $type['order_type_id']; ?>"><?php echo htmlspecialchars($type['type_name']); ?></option>
                <?php endforeach; ?>
            </select>

            <label for="container_id">Container</label>
            <select name="container_id" required>
                <option value="">Select Container</option>
                <?php foreach ($containers as $container): ?>
                    <option value="<?php echo $container['container_id']; ?>">
                        <?php echo htmlspecialchars($container['container_type'] . ' (₱' . $container['price'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="quantity">Quantity</label>
            <input type="number" name="quantity" min="1" required>

            <label for="delivery_date">Delivery Date</label>
            <input type="date" name="delivery_date" required>

            <button type="submit" name="add_order">Add to Cart</button>
        </div>

        <?php if (isset($_SESSION['receipt_data'])): ?>
            <div id="receiptModal" class="modal">
                <div class="modal-content">
                    <h2>Your Order Summary</h2>
                    <div class="cart-item">
                        <span><strong>Item:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['quantity'] . ' x ' . $_SESSION['receipt_data']['container']); ?></span>
                        <span>₱<?php echo number_format($_SESSION['receipt_data']['subtotal'], 2); ?></span>
                    </div>
                    <div class="cart-total">
                        <span>Total: ₱<?php echo number_format($_SESSION['receipt_data']['subtotal'], 2); ?></span>
                    </div>
                    <p><strong>Reference ID:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['reference_id']); ?></p>
                    <p><strong>Customer Name:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['customer_name']); ?></p>
                    <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['customer_contact']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['address']); ?></p>
                    <p><strong>Order Type:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['order_type']); ?></p>
                    <p><strong>Order Date:</strong> <?php echo $_SESSION['receipt_data']['order_date']; ?></p>
                    <p><strong>Delivery Date:</strong> <?php echo $_SESSION['receipt_data']['delivery_date']; ?></p>
                    <p><strong>Vehicle Type:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['vehicle_type']); ?></p>
                    <p><strong>Batch ID:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['batch_id']); ?></p>
                    <p class="warning">Please take a screenshot of this receipt for your records!</p>
                    <form method="POST" action="">
                        <button type="submit" name="close_receipt">Confirm Order</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

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

        function updateBarangays() {
            const citySelect = document.getElementById('city');
            const barangaySelect = document.getElementById('barangay');
            const cities = <?php echo json_encode($ncr_cities); ?>;
            const selectedCity = citySelect.value;

            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';

            if (selectedCity && cities[selectedCity]) {
                cities[selectedCity].forEach(barangay => {
                    const option = document.createElement('option');
                    option.value = barangay;
                    option.textContent = barangay;
                    barangaySelect.appendChild(option);
                });
            }
        }

        <?php if (isset($_SESSION['receipt_data'])): ?>
            document.getElementById('receiptModal').style.display = 'flex';
        <?php endif; ?>
    </script>
</body>
</html>