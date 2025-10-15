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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $customer_contact = trim($_POST['customer_contact'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $city = trim($_POST['city'] ?? '');

    $errors = [];

    if (empty($username)) $errors[] = "Username is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($first_name)) $errors[] = "First name is required.";
    if (empty($last_name)) $errors[] = "Last name is required.";
    if (empty($customer_contact) || !preg_match('/^09\d{9}$/', $customer_contact)) {
        $errors[] = "Valid contact number (e.g., 09XXXXXXXXX) is required.";
    }
    if (empty($street)) $errors[] = "Street is required.";
    if (empty($barangay) || !in_array($barangay, $ncr_cities[$city] ?? [])) {
        $errors[] = "Valid barangay is required.";
    }
    if (empty($city) || !array_key_exists($city, $ncr_cities)) {
        $errors[] = "Valid NCR city is required.";
    }

    // Check for unique username
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username already exists.";
        }
    } catch (PDOException $e) {
        $errors[] = "Error checking username: " . $e->getMessage();
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO customers (username, password, first_name, last_name, customer_contact, street, barangay, city, province, date_created)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Metro Manila', NOW())
            ");
            $stmt->execute([$username, $password, $first_name, $last_name, $customer_contact, $street, $barangay, $city]);
            header("Location: login.php?success=Registration successful! Please log in.");
            exit;
        } catch (PDOException $e) {
            $errors[] = "Registration failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Water Refilling Station</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            width: 100%;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        input[type="text"], input[type="password"], select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            margin-bottom: 10px;
        }
        .success {
            color: green;
            margin-bottom: 10px;
            text-align: center;
        }
        .back-button {
            text-align: center;
            margin-top: 10px;
        }
        .back-button a {
            color: #007bff;
            text-decoration: none;
        }
        .back-button a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            input[type="submit"] {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Register</h1>
        <?php if (isset($_GET['success'])): ?>
            <div class="success"><?php echo htmlspecialchars($_GET['success']); ?></div>
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
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" name="first_name" id="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" name="last_name" id="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="customer_contact">Contact Number (e.g., 09XXXXXXXXX)</label>
                <input type="text" name="customer_contact" id="customer_contact" value="<?php echo isset($_POST['customer_contact']) ? htmlspecialchars($_POST['customer_contact']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="street">Street</label>
                <input type="text" name="street" id="street" value="<?php echo isset($_POST['street']) ? htmlspecialchars($_POST['street']) : ''; ?>" required>
            </div>
            <div class="form-group">
                <label for="city">City</label>
                <select name="city" id="city" required onchange="updateBarangays()">
                    <option value="">Select City</option>
                    <?php foreach ($ncr_cities as $city => $barangays): ?>
                        <option value="<?php echo htmlspecialchars($city); ?>" <?php echo isset($_POST['city']) && $_POST['city'] === $city ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($city); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="barangay">Barangay</label>
                <select name="barangay" id="barangay" required>
                    <option value="">Select Barangay</option>
                    <?php if (isset($_POST['city']) && array_key_exists($_POST['city'], $ncr_cities)): ?>
                        <?php foreach ($ncr_cities[$_POST['city']] as $barangay): ?>
                            <option value="<?php echo htmlspecialchars($barangay); ?>" <?php echo isset($_POST['barangay']) && $_POST['barangay'] === $barangay ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($barangay); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <input type="submit" value="Register">
        </form>
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
    </script>
</body>
</html>