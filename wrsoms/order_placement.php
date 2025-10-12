<?php
session_start();
require_once 'connect.php';

// Check if user is logged in
if (!isset($_SESSION['customer_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// Debug: Log the current session customer_id
error_log("Session customer_id: " . $_SESSION['customer_id']);

// Fetch customer details
try {
    $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, customer_contact, street, barangay, city, province FROM customers WHERE customer_id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) {
        die("Customer not found for customer_id: " . $_SESSION['customer_id']);
    }
} catch (PDOException $e) {
    die("Error fetching customer details: " . $e->getMessage());
}

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

// Function to assign batch based on city, delivery date, and vehicle capacity
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
    $order_type_id = filter_input(INPUT_POST, 'order_type_id', FILTER_VALIDATE_INT);
    $container_id = filter_input(INPUT_POST, 'container_id', FILTER_VALIDATE_INT);
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $delivery_date = filter_input(INPUT_POST, 'delivery_date', FILTER_SANITIZE_STRING) ?: '';

    if (!$order_type_id) $errors[] = "Invalid order type.";
    if (!$container_id) $errors[] = "Invalid container selection.";
    if (!$quantity || $quantity < 1) $errors[] = "Quantity must be at least 1.";
    if (empty($delivery_date) || strtotime($delivery_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Delivery date must be today or later.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT container_type, price FROM containers WHERE container_id = ?");
            $stmt->execute([$container_id]);
            $container = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$container) {
                throw new Exception("Container not found.");
            }

            $subtotal = $container['price'] * $quantity;
            $reference_id = generateReferenceId($pdo);
            $batch_info = assignBatch($pdo, $customer['city'], $delivery_date, $quantity);
            $batch_id = $batch_info['batch_id'];
            $batch_number = $batch_info['batch_number'];
            $vehicle_type = ($customer['city'] === 'Taguig') ? 'Tricycle' : 'Car';

            // Debug: Log order details before insertion
            error_log("Inserting order: reference_id=$reference_id, customer_id={$_SESSION['customer_id']}, order_type_id=$order_type_id, batch_id=$batch_id, batch_number=$batch_number, delivery_date=$delivery_date, subtotal=$subtotal");

            $stmt = $pdo->prepare("INSERT INTO orders (reference_id, customer_id, order_type_id, batch_id, order_date, delivery_date, order_status_id, total_amount) VALUES (?, ?, ?, ?, NOW(), ?, 1, ?)");
            $stmt->execute([$reference_id, $_SESSION['customer_id'], $order_type_id, $batch_id, $delivery_date, $subtotal]);

            $stmt = $pdo->prepare("INSERT INTO order_details (reference_id, batch_number, container_id, quantity, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$reference_id, $batch_number, $container_id, $quantity, $subtotal]);

            $stmt = $pdo->prepare("SELECT type_name FROM order_types WHERE order_type_id = ?");
            $stmt->execute([$order_type_id]);
            $order_type = $stmt->fetchColumn();

            $_SESSION['receipt_data'] = [
                'reference_id' => $reference_id,
                'customer_name' => $customer['first_name'] . ' ' . ($customer['middle_name'] ? $customer['middle_name'] . ' ' : '') . $customer['last_name'],
                'customer_contact' => $customer['customer_contact'],
                'address' => "{$customer['street']}, {$customer['barangay']}, {$customer['city']}, {$customer['province']}",
                'order_type' => $order_type,
                'container' => $container['container_type'],
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'delivery_date' => $delivery_date,
                'order_date' => date('Y-m-d H:i:s'),
                'vehicle_type' => $vehicle_type,
                'batch_number' => $batch_number
            ];

            $pdo->commit();
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
        .batch-info {
            background-color: #e7f3ff;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #007bff;
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

        <!-- Customer Details Display -->
        <div class="form-container">
            <h2>Customer Details</h2>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($customer['first_name'] . ' ' . ($customer['middle_name'] ? $customer['middle_name'] . ' ' : '') . $customer['last_name']); ?></p>
            <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($customer['customer_contact']); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars("{$customer['street']}, {$customer['barangay']}, {$customer['city']}, {$customer['province']}"); ?></p>
        </div>

        <!-- Add New Order Form -->
        <div class="form-container">
            <h2>Add New Order</h2>
            <form method="POST" action="">
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
                    Maximum 3 batches per vehicle type per day. Orders exceeding capacity will be moved to next available batch.
                </div>

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
                    <p><strong>Batch Number:</strong> <?php echo htmlspecialchars($_SESSION['receipt_data']['batch_number']); ?></p>
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
        <?php if (isset($_SESSION['receipt_data'])): ?>
            document.getElementById('receiptModal').style.display = 'flex';
        <?php endif; ?>
    </script>
</body>
</html>
