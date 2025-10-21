<?php
session_start();
require_once 'connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch user address from customers table
$user_id = $_SESSION['customer_id'];
$stmt = $pdo->prepare("SELECT first_name, last_name, customer_contact, street, barangay, city, province FROM customers WHERE customer_id = ?");
$stmt->execute([$user_id]);
$user_address = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'first_name' => '', 'last_name' => '', 'customer_contact' => '',
    'street' => '', 'barangay' => '', 'city' => '', 'province' => ''
];

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
function assignBatch($pdo, $city, $delivery_date, $quantity) {
    $vehicle_type = ($city === 'Taguig') ? 'Tricycle' : 'Car';
    $capacity = ($vehicle_type === 'Tricycle') ? 5 : 10;
    $batch_date = $delivery_date;

    if ($quantity > $capacity) {
        throw new Exception("Order quantity exceeds vehicle capacity for $vehicle_type (Max: $capacity containers). Please split your order into smaller quantities.");
    }

    // Find existing batch with enough space (prioritize lowest batch_number)
    $stmt = $pdo->prepare("
        SELECT b.batch_id, b.batch_number, COALESCE(SUM(od.quantity), 0) AS total_quantity
        FROM batches b
        LEFT JOIN orders o ON b.batch_id = o.batch_id
        LEFT JOIN order_details od ON o.reference_id = od.reference_id
        WHERE DATE(b.batch_date) = ? AND b.vehicle_type = ?
        GROUP BY b.batch_id, b.batch_number
        HAVING total_quantity + ? <= ?
        ORDER BY b.batch_number ASC, b.batch_id ASC
        LIMIT 1
    ");
    $stmt->execute([$batch_date, $vehicle_type, $quantity, $capacity]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {
        error_log("Assigned to existing batch: batch_id={$batch['batch_id']}, batch_number={$batch['batch_number']}, remaining_capacity=" . ($capacity - $batch['total_quantity']));
        return [
            'batch_id' => $batch['batch_id'],
            'batch_number' => $batch['batch_number']
        ];
    }

    // No existing batch has space, check if can create new
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM batches 
        WHERE DATE(batch_date) = ? AND vehicle_type = ?
    ");
    $stmt->execute([$batch_date, $vehicle_type]);
    $batch_count = $stmt->fetchColumn();

    if ($batch_count >= 3) {
        throw new Exception("Cannot assign batch: Limit of 3 batches reached for $vehicle_type on $batch_date and all are full. Please choose a different delivery date.");
    }

    // Create new batch with sequential batch_number (1,2,3)
    $new_batch_number = $batch_count + 1;
    
    $stmt = $pdo->prepare("
        INSERT INTO batches (vehicle, vehicle_type, batch_number, batch_status_id, notes, batch_date) 
        VALUES (?, ?, ?, 1, 'Auto-created batch', ?)
    ");
    $vehicle_name = $vehicle_type . ' #' . rand(100, 999);
    $stmt->execute([$vehicle_name, $vehicle_type, $new_batch_number, $batch_date]);
    $new_batch_id = $pdo->lastInsertId();
    
    error_log("Created new batch: batch_id=$new_batch_id, batch_number=$new_batch_number, vehicle_type=$vehicle_type");
    return [
        'batch_id' => $new_batch_id,
        'batch_number' => $new_batch_number
    ];
}

// Handle form submission
$errors = [];
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {
    $address_option = filter_input(INPUT_POST, 'address_option', FILTER_SANITIZE_STRING) ?: 'manual';
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

    // Validation
    if (empty($first_name) || empty($last_name)) $errors[] = "First name and last name are required.";
    if (empty($customer_contact) || !preg_match('/^09\d{9}$/', $customer_contact)) $errors[] = "Valid contact number (e.g., 09XXXXXXXXX) is required.";
    if ($address_option === 'manual') {
        if (empty($street)) $errors[] = "Street is required.";
        if (empty($barangay) || !in_array($barangay, $ncr_cities[$city] ?? [])) $errors[] = "Invalid barangay for selected city.";
        if (empty($city) || !array_key_exists($city, $ncr_cities)) $errors[] = "Invalid city.";
        if (empty($province) || $province !== 'Metro Manila') $errors[] = "Province must be Metro Manila.";
    } else {
        if (empty($user_address['street']) || empty($user_address['barangay']) || empty($user_address['city']) || empty($user_address['province'])) {
            $errors[] = "No valid address found in your account. Please enter an address manually.";
        }
        $first_name = $user_address['first_name'];
        $last_name = $user_address['last_name'];
        $customer_contact = $user_address['customer_contact'];
        $street = $user_address['street'];
        $barangay = $user_address['barangay'];
        $city = $user_address['city'];
        $province = $user_address['province'];
    }
    if (!$order_type_id) $errors[] = "Invalid order type.";
    if (!$container_id) $errors[] = "Invalid container selection.";
    if (!$quantity || $quantity < 1) $errors[] = "Quantity must be at least 1.";
    if (empty($delivery_date) || strtotime($delivery_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Delivery date must be today or later.";
    }

    // Inventory check for Purchase New Container/s or Both
    if (empty($errors) && in_array($order_type_id, [2, 3])) {
        $stmt = $pdo->prepare("SELECT stock, container_type FROM inventory WHERE container_id = ?");
        $stmt->execute([$container_id]);
        $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$inventory || $inventory['stock'] < $quantity) {
            $errors[] = "Not enough {$inventory['container_type']} containers in stock.";
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT customer_id FROM customers WHERE customer_contact = ?");
            $stmt->execute([$customer_contact]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer) {
                $customer_id = $customer['customer_id'];
                // Update customer address if manual
                if ($address_option === 'manual') {
                    $stmt = $pdo->prepare("UPDATE customers SET first_name = ?, middle_name = ?, last_name = ?, street = ?, barangay = ?, city = ?, province = ? WHERE customer_id = ?");
                    $stmt->execute([$first_name, $middle_name, $last_name, $street, $barangay, $city, $province, $customer_id]);
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO customers (username, password, first_name, middle_name, last_name, customer_contact, street, barangay, city, province, date_created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $username = $customer_contact; // Use contact as username for new customers
                $password = 'default' . rand(1000, 9999); // Temporary password
                $stmt->execute([$username, $password, $first_name, $middle_name, $last_name, $customer_contact, $street, $barangay, $city, $province]);
                $customer_id = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("SELECT container_type, price FROM containers WHERE container_id = ?");
            $stmt->execute([$container_id]);
            $container = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$container) {
                throw new Exception("Container not found.");
            }

            $subtotal = $container['price'] * $quantity;
            $reference_id = generateReferenceId($pdo);
            
            // Get batch assignment
            $batch_info = assignBatch($pdo, $city, $delivery_date, $quantity);
            $batch_id = $batch_info['batch_id'];
            $batch_number = $batch_info['batch_number'];
            $vehicle_type = ($city === 'Taguig') ? 'Tricycle' : 'Car';

            // Insert into orders table
            $stmt = $pdo->prepare("INSERT INTO orders (reference_id, customer_id, order_type_id, batch_id, order_date, delivery_date, order_status_id, total_amount) VALUES (?, ?, ?, ?, NOW(), ?, 1, ?)");
            $stmt->execute([$reference_id, $customer_id, $order_type_id, $batch_id, $delivery_date, $subtotal]);

            // Insert into order_details table
            $stmt = $pdo->prepare("INSERT INTO order_details (reference_id, batch_number, container_id, quantity, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$reference_id, $batch_number, $container_id, $quantity, $subtotal]);

            // Deduct inventory for Purchase New Container/s or Both
            if (in_array($order_type_id, [2, 3])) {
                $stmt = $pdo->prepare("UPDATE inventory SET stock = stock - ? WHERE container_id = ?");
                $stmt->execute([$quantity, $container_id]);
            }

            $pdo->commit();

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
                'vehicle_type' => $vehicle_type,
                'batch_number' => $batch_number,
                'batch_id' => $batch_id
            ];

            $success = "Order added successfully to Batch #$batch_number ($vehicle_type)!";

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
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
    <title>Place Your Order - WaterWorld</title>
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

        .form-container .radio-group {
            grid-column: 1 / -1;
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
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

        .batch-info {
            grid-column: 1 / -1;
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 8px;
            border-left: 4px solid #008CBA;
            margin-bottom: 1rem;
            font-size: 0.9rem;
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
                <li><a href="transaction_history.php">History</a></li>
                <li><a href="logout.php">Logout</a></li>
                <li class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</li>
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
            <form method="POST" action="">
                <h3>Customer Details</h3>
                <div class="radio-group">
                    <label><input type="radio" name="address_option" value="account" onclick="toggleAddressFields()" <?php echo $user_address['street'] ? '' : 'disabled'; ?>> Use my account address</label>
                    <label><input type="radio" name="address_option" value="manual" checked onclick="toggleAddressFields()"> Enter new address</label>
                </div>
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user_address['first_name']); ?>" required <?php echo $user_address['first_name'] ? 'readonly' : ''; ?>>

                <label for="middle_name">Middle Name (Optional)</label>
                <input type="text" name="middle_name" id="middle_name">

                <label for="last_name">Last Name</label>
                <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($user_address['last_name']); ?>" required <?php echo $user_address['last_name'] ? 'readonly' : ''; ?>>

                <label for="customer_contact">Contact Number (e.g., 09XXXXXXXXX)</label>
                <input type="text" name="customer_contact" id="customer_contact" value="<?php echo htmlspecialchars($user_address['customer_contact']); ?>" required <?php echo $user_address['customer_contact'] ? 'readonly' : ''; ?>>

                <label for="street">Street</label>
                <input type="text" name="street" id="street" value="<?php echo htmlspecialchars($user_address['street']); ?>" required <?php echo $user_address['street'] ? 'readonly' : ''; ?>>

                <label for="city">City</label>
                <select name="city" id="city" required onchange="updateBarangays()" <?php echo $user_address['city'] ? 'disabled' : ''; ?>>
                    <option value="">Select City</option>
                    <?php foreach (array_keys($ncr_cities) as $city): ?>
                        <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $city === $user_address['city'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($city); ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="barangay">Barangay</label>
                <select name="barangay" id="barangay" required <?php echo $user_address['barangay'] ? 'disabled' : ''; ?>>
                    <option value="">Select Barangay</option>
                    <?php if ($user_address['city'] && isset($ncr_cities[$user_address['city']])): ?>
                        <?php foreach ($ncr_cities[$user_address['city']] as $barangay): ?>
                            <option value="<?php echo htmlspecialchars($barangay); ?>" <?php echo $barangay === $user_address['barangay'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($barangay); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <label for="province">Province</label>
                <input type="text" name="province" id="province" value="Metro Manila" readonly>

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

                <label for="quantity">Quantity (Containers)</label>
                <input type="number" name="quantity" min="1" required>
                <div class="batch-info">
                    <strong>Note:</strong> Tricycle (Taguig only): Max 5 containers per batch | Car (Outside Taguig): Max 10 containers per batch<br>
                    Maximum 3 batches per vehicle type per day. Orders exceeding capacity will be moved to next available batch.<br>
                    <strong>Inventory:</strong> Refill orders do not deduct from stock. Purchase New Container/s and Both orders deduct from available stock.
                </div>

                <label for="delivery_date">Delivery Date</label>
                <input type="date" name="delivery_date" required>

                <button type="submit" name="add_order">Add Order</button>
            </form>
        </div>

        <div id="receiptModal" class="modal" style="display: <?php echo isset($_SESSION['receipt_data']) ? 'flex' : 'none'; ?>;">
            <div class="modal-content">
                <h2>Your Order Summary</h2>
                <div class="cart-item">
                    <span><strong>Item:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['quantity'] . ' x ' . $_SESSION['receipt_data']['container']) : ''; ?></span>
                    <span>₱<?php echo isset($_SESSION['receipt_data']) ? number_format($_SESSION['receipt_data']['subtotal'], 2) : '0.00'; ?></span>
                </div>
                <div class="cart-total">
                    <span>Total: ₱<?php echo isset($_SESSION['receipt_data']) ? number_format($_SESSION['receipt_data']['subtotal'], 2) : '0.00'; ?></span>
                </div>
                <p><strong>Reference ID:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['reference_id']) : ''; ?></p>
                <p><strong>Customer Name:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['customer_name']) : ''; ?></p>
                <p><strong>Contact Number:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['customer_contact']) : ''; ?></p>
                <p><strong>Address:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['address']) : ''; ?></p>
                <p><strong>Order Type:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['order_type']) : ''; ?></p>
                <p><strong>Order Date:</strong> <?php echo isset($_SESSION['receipt_data']) ? $_SESSION['receipt_data']['order_date'] : ''; ?></p>
                <p><strong>Delivery Date:</strong> <?php echo isset($_SESSION['receipt_data']) ? $_SESSION['receipt_data']['delivery_date'] : ''; ?></p>
                <p><strong>Vehicle Type:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['vehicle_type']) : ''; ?></p>
                <p><strong>Batch Number:</strong> Batch #<?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['batch_number']) : ''; ?></p>
                <p class="warning">Please take a screenshot of this receipt for your records!</p>
                <form method="POST" action="">
                    <button type="submit" name="close_receipt">Confirm Order</button>
                </form>
            </div>
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

        function toggleAddressFields() {
            const addressOption = document.querySelector('input[name="address_option"]:checked').value;
            const fields = ['first_name', 'last_name', 'customer_contact', 'street', 'city', 'barangay', 'province'];
            const userData = {
                first_name: <?php echo json_encode($user_address['first_name']); ?>,
                last_name: <?php echo json_encode($user_address['last_name']); ?>,
                customer_contact: <?php echo json_encode($user_address['customer_contact']); ?>,
                street: <?php echo json_encode($user_address['street']); ?>,
                city: <?php echo json_encode($user_address['city']); ?>,
                barangay: <?php echo json_encode($user_address['barangay']); ?>,
                province: <?php echo json_encode($user_address['province']); ?>
            };

            fields.forEach(field => {
                const element = document.getElementById(field);
                if (addressOption === 'account' && userData[field]) {
                    element.value = userData[field];
                    if (field === 'city' || field === 'barangay') {
                        element.disabled = true;
                    } else if (field !== 'middle_name') {
                        element.readOnly = true;
                    }
                } else {
                    if (field !== 'province' && field !== 'middle_name') {
                        element.value = '';
                        element.readOnly = false;
                        element.disabled = false;
                    }
                }
            });

            if (addressOption === 'account' && userData['city']) {
                updateBarangays();
                document.getElementById('barangay').value = userData['barangay'] || '';
            }
        }

        window.onload = function() {
            toggleAddressFields();
        };
    </script>
</body>
</html>
