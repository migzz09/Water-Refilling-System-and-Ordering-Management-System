<?php
session_start();
require_once 'connect.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['customer_id'])) {
    header("Location: index.php#login");
    exit;
}

// Fetch user details from customers table
$user_id = $_SESSION['customer_id'];
$stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, customer_contact, street, barangay, city, province FROM customers WHERE customer_id = ?");
$stmt->execute([$user_id]);
$user_address = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'first_name' => '', 'middle_name' => '', 'last_name' => '', 'customer_contact' => '',
    'street' => '', 'barangay' => '', 'city' => '', 'province' => ''
];

// Use session address if set, but merge with user details for name/contact
$selected_address = array_merge(
    $user_address,
    $_SESSION['temp_address'] ?? []
);

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

// Function to get vehicle type based on city
function getVehicleType($city) {
    return ($city === 'Taguig') ? 'Tricycle' : 'Car';
}

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

// Handle address form submission (via AJAX)
$errors = [];
$success = null;
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_address'])) {
    $street = filter_input(INPUT_POST, 'street', FILTER_SANITIZE_STRING) ?: '';
    $barangay = filter_input(INPUT_POST, 'barangay', FILTER_SANITIZE_STRING) ?: '';
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING) ?: '';
    $province = filter_input(INPUT_POST, 'province', FILTER_SANITIZE_STRING) ?: 'Metro Manila';

    // Validation
    if (empty($street)) {
        $errors[] = "Street is required.";
    }
    if (empty($barangay) || !in_array($barangay, $ncr_cities[$city] ?? [])) {
        $errors[] = "Invalid barangay for selected city.";
    }
    if (empty($city) || !array_key_exists($city, $ncr_cities)) {
        $errors[] = "Invalid city.";
    }
    if ($province !== 'Metro Manila') {
        $errors[] = "Province must be Metro Manila.";
    }

    if (empty($errors)) {
        // Store address in session, preserving user details
        $_SESSION['temp_address'] = [
            'first_name' => $user_address['first_name'],
            'middle_name' => $user_address['middle_name'],
            'last_name' => $user_address['last_name'],
            'customer_contact' => $user_address['customer_contact'],
            'street' => $street,
            'barangay' => $barangay,
            'city' => $city,
            'province' => $province
        ];
        $success = "Address saved for this order.";
    }

    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'errors' => $errors]);
    exit;
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {
    $delivery_date = filter_input(INPUT_POST, 'delivery_date', FILTER_SANITIZE_STRING) ?: '';

    // Validation
    if (empty($cart)) {
        $errors[] = "Your cart is empty. Please add items to your cart before placing an order.";
    }
    if (empty($selected_address['first_name']) || empty($selected_address['last_name'])) {
        $errors[] = "First name and last name are required in your account.";
    }
    if (empty($selected_address['customer_contact']) || !preg_match('/^09\d{9}$/', $selected_address['customer_contact'])) {
        $errors[] = "Valid contact number (e.g., 09XXXXXXXXX) is required in your account.";
    }
    if (empty($selected_address['street'])) {
        $errors[] = "Street is required.";
    }
    if (empty($selected_address['barangay']) || !in_array($selected_address['barangay'], $ncr_cities[$selected_address['city']] ?? [])) {
        $errors[] = "Invalid barangay for selected city.";
    }
    if (empty($selected_address['city']) || !array_key_exists($selected_address['city'], $ncr_cities)) {
        $errors[] = "Invalid city.";
    }
    if ($selected_address['province'] !== 'Metro Manila') {
        $errors[] = "Province must be Metro Manila.";
    }
    if (empty($delivery_date) || strtotime($delivery_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Delivery date must be today or later.";
    }

    // Inventory check for Purchase New Container/s
    if (empty($errors)) {
        foreach ($cart as $item) {
            if ($item['order_type_id'] === 2) { // Purchase New Container/s
                $stmt = $pdo->prepare("SELECT stock, container_type FROM inventory WHERE container_id = ?");
                $stmt->execute([$item['id']]);
                $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$inventory || $inventory['stock'] < $item['quantity']) {
                    $errors[] = "Not enough {$inventory['container_type']} containers in stock for your order.";
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Calculate total quantity and amount
            $total_quantity = array_sum(array_column($cart, 'quantity'));
            $total_amount = array_sum(array_map(function($item) {
                return $item['price'] * $item['quantity'];
            }, $cart));

            // Assign batch
            $batch_info = assignBatch($pdo, $selected_address['city'], $delivery_date, $total_quantity);
            $batch_id = $batch_info['batch_id'];
            $batch_number = $batch_info['batch_number'];
            $vehicle_type = getVehicleType($selected_address['city']);

            // Insert into orders table (using first item's order_type_id as fallback)
            $reference_id = generateReferenceId($pdo);
            $stmt = $pdo->prepare("INSERT INTO orders (reference_id, customer_id, order_type_id, batch_id, order_date, delivery_date, order_status_id, total_amount) VALUES (?, ?, ?, ?, NOW(), ?, 1, ?)");
            $stmt->execute([$reference_id, $user_id, $cart[0]['order_type_id'], $batch_id, $delivery_date, $total_amount]);

            // Insert into order_details table
            foreach ($cart as $item) {
                $subtotal = $item['price'] * $item['quantity'];
                $stmt = $pdo->prepare("INSERT INTO order_details (reference_id, batch_number, container_id, water_type_id, order_type_id, quantity, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$reference_id, $batch_number, $item['id'], $item['water_type_id'], $item['order_type_id'], $item['quantity'], $subtotal]);

                // Deduct inventory for Purchase New Container/s
                if ($item['order_type_id'] === 2) {
                    $stmt = $pdo->prepare("UPDATE inventory SET stock = stock - ? WHERE container_id = ?");
                    $stmt->execute([$item['quantity'], $item['id']]);
                }
            }

            $pdo->commit();

            // Prepare receipt data
            $_SESSION['receipt_data'] = [
                'reference_id' => $reference_id,
                'customer_name' => $selected_address['first_name'] . ' ' . ($selected_address['middle_name'] ? $selected_address['middle_name'] . ' ' : '') . $selected_address['last_name'],
                'customer_contact' => $selected_address['customer_contact'],
                'address' => "{$selected_address['street']}, {$selected_address['barangay']}, {$selected_address['city']}, {$selected_address['province']}",
                'items' => $cart,
                'total_amount' => $total_amount,
                'delivery_date' => $delivery_date,
                'order_date' => date('Y-m-d H:i:s'),
                'vehicle_type' => $vehicle_type,
                'batch_number' => $batch_number,
                'batch_id' => $batch_id
            ];

            // Clear the cart and temp address
            $_SESSION['cart'] = [];
            unset($_SESSION['cart']);
            unset($_SESSION['temp_address']);

            $success = "Order placed successfully in Batch #$batch_number ($vehicle_type)!";

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - WaterWorld Water Station</title>
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
            display: flex;
            align-items: center;
        }
        .logo img {
            height: 2.5rem;
            margin-right: 0.75rem;
        }
        nav ul {
            list-style: none;
            display: flex;
            gap: 1.5rem;
            align-items: center;
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
        .dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e5e5e5;
            border-radius: 5px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            min-width: 220px;
            z-index: 1000;
            margin-top: 5px;
        }
        .profile:hover .dropdown {
            display: block;
        }
        .dropdown a, .dropdown .welcome {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            text-decoration: none;
            color: #333;
            font-size: 0.9rem;
            font-weight: 400;
            transition: background 0.3s;
        }
        .dropdown a:hover {
            background: #f0f0f0;
        }
        .dropdown a img {
            height: 1.8rem;
            width: 1.8rem;
            margin-right: 8px;
        }
        .welcome {
            color: #008CBA;
            font-weight: 500;
        }
        .checkout-container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .checkout-container h1 {
            font-size: 2rem;
            color: #008CBA;
            margin-bottom: 1rem;
            text-align: center;
        }
        .address-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .address-box {
            background: #f9fbfc;
            border: 2px solid #e5e5e5;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: border-color 0.3s, background 0.3s;
        }
        .address-box:hover {
            border-color: #008CBA;
            background: #e3f2fd;
        }
        .address-box.selected {
            border-color: #008CBA;
            background: #e3f2fd;
        }
        .address-box h4 {
            color: #008CBA;
            margin-bottom: 0.5rem;
        }
        .address-box p {
            color: #666;
            font-size: 0.9rem;
        }
        .form-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        .form-container h3 {
            color: #008CBA;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        .form-container label {
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: block;
        }
        .form-container input,
        .form-container select {
            padding: 0.8rem;
            border: 2px solid #e5e5e5;
            border-radius: 8px;
            font-size: 1rem;
            width: 100%;
        }
        .form-container input:focus,
        .form-container select:focus {
            border-color: #008CBA;
            outline: none;
        }
        .cart-items {
            background: #f9fbfc;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e5e5e5;
        }
        .cart-item:last-child {
            border-bottom: none;
        }
        .cart-total {
            font-weight: bold;
            color: #008CBA;
            margin-top: 1rem;
            text-align: right;
        }
        .batch-info {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #008CBA;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        .place-order-btn {
            background: linear-gradient(90deg, #008CBA, #00aaff);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .place-order-btn:hover {
            transform: translateY(-2px);
        }
        .place-order-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        .error, .success {
            text-align: center;
            margin-bottom: 1rem;
        }
        .error {
            color: #d32f2f;
        }
        .success {
            color: #4CAF50;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1002;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .modal-content h2 {
            color: #008CBA;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        .modal-content .cart-item {
            border-bottom: 1px solid #e5e5e5;
            padding: 1rem 0;
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
            background: linear-gradient(90deg, #008CBA, #00aaff);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s;
        }
        .modal-content button:hover {
            transform: translateY(-2px);
        }
        .modal-buttons {
            display: flex;
            gap: 1rem;
        }
        .modal-buttons button {
            flex: 1;
        }
        .cancel-btn {
            background: #e5e5e5;
            color: #333;
        }
        .cancel-btn:hover {
            background: #d0d0d0;
            transform: translateY(-2px);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            font-weight: bold;
            display: block;
            margin-bottom: 0.5rem;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e5e5e5;
            border-radius: 8px;
            font-size: 1rem;
        }
        .form-group select {
            appearance: none;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #008CBA;
            outline: none;
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
            color: #0077b3;
            text-decoration: underline;
        }
        footer {
            background: #008CBA;
            color: white;
            text-align: center;
            padding: 2rem 5%;
            margin-top: 3rem;
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
        @media (max-width: 768px) {
            .address-options {
                grid-template-columns: 1fr;
            }
            .checkout-container {
                margin: 2rem 1rem;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="images/ww_logo.png" alt="WaterWorld Logo">
            WaterWorld
        </div>
        <nav>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="product.php">Products</a></li>
                <li><a href="order_tracking.php">Track</a></li>
                <li><a href="feedback.php">Feedback</a></li>
                <li class="profile" style="position: relative;">
                    <div style="display: flex; align-items: center; cursor: pointer;" onclick="toggleDropdown(this)">
                        <img src="images/profile_pic.png" alt="Profile" style="height: 2.5rem; width: 2.5rem;">
                    </div>
                    <div class="dropdown">
                        <div class="welcome">Welcome <?php echo htmlspecialchars($_SESSION['username']); ?>!</div>
                        <a href="user_settings.php">
                            <img src="images/user_settings.png" alt="Settings">
                            User Settings
                        </a>
                        <a href="usertransaction_history.php">
                            <img src="images/usertransaction_history.png" alt="History">
                            Transaction History
                        </a>
                        <a href="logout.php">
                            <img src="images/logout.png" alt="Logout">
                            Logout
                        </a>
                    </div>
                </li>
            </ul>
        </nav>
    </header>

    <div class="checkout-container">
        <h1>Checkout</h1>

        <?php if (isset($success)): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="">
                <h3>Select Delivery Address</h3>
                <div class="address-options">
                    <div class="address-box <?php echo ($selected_address['street'] === $user_address['street'] && $selected_address['barangay'] === $user_address['barangay'] && $selected_address['city'] === $user_address['city'] && $selected_address['province'] === $user_address['province']) ? 'selected' : ''; ?>" onclick="openAddressModal('account')">
                        <h4>Account Address</h4>
                        <?php if ($user_address['street']): ?>
                            <p><?php echo htmlspecialchars($user_address['street'] . ', ' . $user_address['barangay'] . ', ' . $user_address['city'] . ', ' . $user_address['province']); ?></p>
                        <?php else: ?>
                            <p>No address saved in account</p>
                        <?php endif; ?>
                    </div>
                    <div class="address-box <?php echo ($selected_address['street'] !== $user_address['street'] || $selected_address['barangay'] !== $user_address['barangay'] || $selected_address['city'] !== $user_address['city'] || $selected_address['province'] !== $user_address['province']) && isset($_SESSION['temp_address']) ? 'selected' : ''; ?>" onclick="openAddressModal('new')">
                        <h4>New Address</h4>
                        <?php if (isset($_SESSION['temp_address']) && ($selected_address['street'] !== $user_address['street'] || $selected_address['barangay'] !== $user_address['barangay'] || $selected_address['city'] !== $user_address['city'] || $selected_address['province'] !== $user_address['province'])): ?>
                            <p><?php echo htmlspecialchars($_SESSION['temp_address']['street'] . ', ' . $_SESSION['temp_address']['barangay'] . ', ' . $_SESSION['temp_address']['city'] . ', ' . $_SESSION['temp_address']['province']); ?></p>
                        <?php else: ?>
                            <p>Enter a new delivery address</p>
                        <?php endif; ?>
                    </div>
                </div>

                <h3>Order Details</h3>
                <div class="cart-items">
                    <?php if (empty($cart)): ?>
                        <p>Your cart is empty.</p>
                    <?php else: ?>
                        <?php foreach ($cart as $item): ?>
                            <div class="cart-item">
                                <span><?php echo htmlspecialchars($item['quantity'] . ' x ' . $item['name'] . ' (' . $item['water_type_name'] . ', ' . $item['order_type_name'] . ')'); ?></span>
                                <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                        <div class="cart-total">
                            <span>Total: ₱<?php echo number_format(array_sum(array_map(function($item) { return $item['price'] * $item['quantity']; }, $cart)), 2); ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <label for="delivery_date">Delivery Date</label>
                <input type="date" name="delivery_date" required min="<?php echo date('Y-m-d'); ?>">

                <div class="batch-info">
                    <strong>Note:</strong> Tricycle (Taguig only): Max 5 containers per batch | Car (Outside Taguig): Max 10 containers per batch<br>
                    Maximum 3 batches per vehicle type per day. Orders exceeding capacity will be moved to next available batch.<br>
                    <strong>Inventory:</strong> Refill orders do not deduct from stock. Purchase New Container/s orders deduct from available stock.
                </div>

                <button type="submit" name="add_order" class="place-order-btn" <?php echo empty($cart) ? 'disabled' : ''; ?>>Place Order</button>
            </form>
        </div>

        <!-- Address Modal -->
        <div id="addressModal" class="modal">
            <div class="modal-content">
                <h2>Update Delivery Address</h2>
                <div class="error" id="addressErrorMessages"></div>
                <div class="success" id="addressSuccessMessage"></div>
                <form id="addressForm">
                    <div class="form-group">
                        <label for="street">Street</label>
                        <input type="text" id="street" name="street" required>
                    </div>
                    <div class="form-group">
                        <label for="city">City</label>
                        <select id="city" name="city" required onchange="updateBarangays()">
                            <option value="">Select City</option>
                            <?php foreach (array_keys($ncr_cities) as $city): ?>
                                <option value="<?php echo htmlspecialchars($city); ?>"><?php echo htmlspecialchars($city); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="barangay">Barangay</label>
                        <select id="barangay" name="barangay" required>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="province">Province</label>
                        <input type="text" id="province" name="province" value="Metro Manila" readonly>
                    </div>
                    <div class="modal-buttons">
                        <button type="submit" class="save-btn">Save Address</button>
                        <button type="button" class="cancel-btn" onclick="closeAddressModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Receipt Modal -->
        <div id="receiptModal" class="modal" style="display: <?php echo isset($_SESSION['receipt_data']) ? 'flex' : 'none'; ?>;">
            <div class="modal-content">
                <h2>Your Order Summary</h2>
                <?php if (isset($_SESSION['receipt_data']) && !empty($_SESSION['receipt_data']['items'])): ?>
                    <?php foreach ($_SESSION['receipt_data']['items'] as $item): ?>
                        <div class="cart-item">
                            <span><?php echo htmlspecialchars($item['quantity'] . ' x ' . $item['name'] . ' (' . $item['water_type_name'] . ', ' . $item['order_type_name'] . ')'); ?></span>
                            <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="cart-total">
                        <span>Total: ₱<?php echo number_format($_SESSION['receipt_data']['total_amount'], 2); ?></span>
                    </div>
                <?php endif; ?>
                <p><strong>Reference ID:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['reference_id']) : ''; ?></p>
                <p><strong>Customer Name:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['customer_name']) : ''; ?></p>
                <p><strong>Contact Number:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['customer_contact']) : ''; ?></p>
                <p><strong>Address:</strong> <?php echo isset($_SESSION['receipt_data']) ? htmlspecialchars($_SESSION['receipt_data']['address']) : ''; ?></p>
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
            <a href="product.php">Back to Products</a>
        </div>
    </div>

    <footer>
        <p>&copy; 2025 WaterWorld Water Station. All rights reserved.</p>
        <div class="socials">
            <a href="#">Facebook</a>
            <a href="#">Twitter</a>
            <a href="#">Instagram</a>
        </div>
    </footer>

    <script>
        const ncrCities = <?php echo json_encode($ncr_cities); ?>;
        let currentAddressType = '';

        function toggleDropdown(element) {
            const dropdown = element.querySelector('.dropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        function openAddressModal(type) {
            currentAddressType = type;
            const modal = document.getElementById('addressModal');
            const form = document.getElementById('addressForm');
            const userAddress = <?php echo json_encode($user_address); ?>;
            const tempAddress = <?php echo json_encode($_SESSION['temp_address'] ?? []); ?>;
            const address = type === 'account' ? userAddress : (tempAddress || {});

            // Pre-fill form
            document.getElementById('street').value = address.street || '';
            document.getElementById('city').value = address.city || '';
            document.getElementById('barangay').value = address.barangay || '';
            document.getElementById('province').value = 'Metro Manila';

            // Update barangays
            updateBarangays();
            if (address.city) {
                document.getElementById('barangay').value = address.barangay || '';
            }

            // Clear previous messages
            document.getElementById('addressErrorMessages').innerHTML = '';
            document.getElementById('addressSuccessMessage').innerHTML = '';

            modal.classList.add('active');
        }

        function closeAddressModal() {
            document.getElementById('addressModal').classList.remove('active');
        }

        function updateBarangays() {
            const citySelect = document.getElementById('city');
            const barangaySelect = document.getElementById('barangay');
            barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
            if (citySelect.value && ncrCities[citySelect.value]) {
                ncrCities[citySelect.value].forEach(barangay => {
                    const option = document.createElement('option');
                    option.value = barangay;
                    option.textContent = barangay;
                    barangaySelect.appendChild(option);
                });
            }
        }

        document.getElementById('addressForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('update_address', '1');
            const response = await fetch('checkout.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            const errorDiv = document.getElementById('addressErrorMessages');
            const successDiv = document.getElementById('addressSuccessMessage');
            errorDiv.innerHTML = '';
            successDiv.innerHTML = '';
            if (result.errors.length > 0) {
                errorDiv.innerHTML = result.errors.map(error => `<p>${error}</p>`).join('');
            } else {
                successDiv.innerHTML = result.success;
                setTimeout(() => {
                    closeAddressModal();
                    window.location.reload(); // Refresh to update address boxes
                }, 1000);
            }
        });
    </script>
</body>
</html>