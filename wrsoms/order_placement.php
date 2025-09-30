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
function assignBatch($pdo, $city) {
    $vehicle_type = ($city === 'Taguig') ? 'Tricycle' : 'Car';
    $capacity = ($vehicle_type === 'Tricycle') ? 5 : 10;

    $stmt = $pdo->prepare("
        SELECT b.batch_id
        FROM batches b
        LEFT JOIN orders o ON b.batch_id = o.batch_id
        WHERE b.vehicle_type = ?
        GROUP BY b.batch_id
        HAVING COUNT(o.reference_id) < ?
        ORDER BY b.batch_id ASC
        LIMIT 1
    ");
    $stmt->execute([$vehicle_type, $capacity]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($batch) {
        return $batch['batch_id'];
    } else {
        $stmt = $pdo->prepare("SELECT MAX(batch_id) + 1 AS new_batch_id FROM batches");
        $stmt->execute();
        $new_batch_id = $stmt->fetchColumn() ?: 3;

        $stmt = $pdo->prepare("INSERT INTO batches (batch_id, vehicle, vehicle_type, batch_status_id, notes) VALUES (?, ?, ?, 1, 'Auto-created batch')");
        $vehicle_name = ($vehicle_type === 'Tricycle') ? 'Tricycle #' . rand(100, 999) : 'Car #' . rand(100, 999);
        $stmt->execute([$new_batch_id, $vehicle_name, $vehicle_type]);
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
                $batch_id = assignBatch($pdo, $city);

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
    <title>Place an Order</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h1, h2, h3 {
            color: #333;
            text-align: center;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .form-container {
            background-color: #fff;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-container label {
            display: block;
            margin: 10px 0 5px;
        }
        .form-container select, .form-container input {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-container button {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .form-container button:hover {
            background-color: #0056b3;
        }
        .success, .error {
            text-align: center;
            margin: 10px 0;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
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
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .modal-content h2 {
            margin-top: 0;
        }
        .modal-content .warning {
            color: red;
            font-weight: bold;
            margin: 10px 0;
        }
        .modal-content button {
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: block;
            margin: 10px auto;
        }
        .modal-content button:hover {
            background-color: #0056b3;
        }
        .back-button {
            display: block;
            text-align: center;
            margin: 20px 0;
        }
        .back-button a {
            color: #007bff;
            text-decoration: none;
        }
        .back-button a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Place an Order</h1>

        <!-- Success or Error Messages -->
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

        <!-- Add New Order Form -->
        <div class="form-container">
            <h2>Add New Order</h2>
            <form method="POST" action="">
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

                <button type="submit" name="add_order">Add Order</button>
            </form>
        </div>

        <!-- Receipt Modal -->
        <?php if (isset($_SESSION['receipt_data'])): ?>
            <div id="receiptModal" class="modal">
                <div class="modal-content">
                    <h2>Order Receipt</h2>
                    <p><strong>Reference ID:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['reference_id']); ?></p>
                    <p><strong>Customer Name:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['customer_name']); ?></p>
                    <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['customer_contact']); ?></p>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['address']); ?></p>
                    <p><strong>Order Type:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['order_type']); ?></p>
                    <p><strong>Item:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['quantity'] . ' x ' . $_SESSION['receipt_data']['container']); ?></p>
                    <p><strong>Subtotal:</strong> ₱<?php echo number_format($_SESSION['receipt_data']['subtotal'], 2); ?></p>
                    <p><strong>Order Date:</strong> <?php echo $_SESSION['receipt_data']['order_date']; ?></p>
                    <p><strong>Delivery Date:</strong> <?php echo $_SESSION['receipt_data']['delivery_date']; ?></p>
                    <p><strong>Vehicle Type:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['vehicle_type']); ?></p>
                    <p><strong>Batch ID:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['batch_id']); ?></p>
                    <p class="warning">Please take a screenshot of this receipt for your records!</p>
                    <form method="POST" action="">
                        <button type="submit" name="close_receipt">Close</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="back-button">
            <a href="index.php">Back to Home</a>
        </div>
    </div>

    <script>
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