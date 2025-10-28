<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: index.php#login");
    exit;
}

$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING) ?: '';
    $middle_name = filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_STRING) ?: null;
    $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING) ?: '';
    $customer_contact = filter_input(INPUT_POST, 'customer_contact', FILTER_SANITIZE_STRING) ?: '';
    $street = filter_input(INPUT_POST, 'street', FILTER_SANITIZE_STRING) ?: '';
    $barangay = filter_input(INPUT_POST, 'barangay', FILTER_SANITIZE_STRING) ?: '';
    $city = filter_input(INPUT_POST, 'city', FILTER_SANITIZE_STRING) ?: '';
    $province = filter_input(INPUT_POST, 'province', FILTER_SANITIZE_STRING) ?: 'Metro Manila';

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

    // Validation
    if (empty($first_name) || empty($last_name)) {
        $errors[] = "First name and last name are required.";
    }
    if (empty($customer_contact) || !preg_match('/^09\d{9}$/', $customer_contact)) {
        $errors[] = "Valid contact number (e.g., 09XXXXXXXXX) is required.";
    }
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
        // Store address in session
        $_SESSION['temp_address'] = [
            'first_name' => $first_name,
            'middle_name' => $middle_name,
            'last_name' => $last_name,
            'customer_contact' => $customer_contact,
            'street' => $street,
            'barangay' => $barangay,
            'city' => $city,
            'province' => $province
        ];
        $success = "Address saved for this order.";
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'errors' => $errors]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Address</title>
    <style>
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9fbfc;
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
        }
        .modal-content h2 {
            color: #008CBA;
            font-size: 1.8rem;
            margin-bottom: 1rem;
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
        .modal-buttons {
            display: flex;
            gap: 1rem;
        }
        .modal-buttons button {
            flex: 1;
            padding: 1rem;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
            cursor: pointer;
        }
        .save-btn {
            background: linear-gradient(90deg, #008CBA, #00aaff);
            color: white;
        }
        .save-btn:hover {
            transform: translateY(-2px);
        }
        .cancel-btn {
            background: #e5e5e5;
            color: #333;
        }
        .cancel-btn:hover {
            background: #d0d0d0;
        }
    </style>
</head>
<body>
    <div class="modal-content">
        <h2>Update Address</h2>
        <form id="addressForm">
            <div class="error" id="errorMessages"></div>
            <div class="success" id="successMessage"></div>
            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>
            <div class="form-group">
                <label for="middle_name">Middle Name (Optional)</label>
                <input type="text" id="middle_name" name="middle_name">
            </div>
            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>
            <div class="form-group">
                <label for="customer_contact">Contact Number (e.g., 09XXXXXXXXX)</label>
                <input type="text" id="customer_contact" name="customer_contact" required>
            </div>
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
                <button type="button" class="cancel-btn" onclick="window.close()">Cancel</button>
            </div>
        </form>
    </div>

    <script>
        const ncrCities = <?php echo json_encode($ncr_cities); ?>;
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

        // Pre-fill form with passed address data
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            document.getElementById('first_name').value = urlParams.get('first_name') || '';
            document.getElementById('middle_name').value = urlParams.get('middle_name') || '';
            document.getElementById('last_name').value = urlParams.get('last_name') || '';
            document.getElementById('customer_contact').value = urlParams.get('customer_contact') || '';
            document.getElementById('street').value = urlParams.get('street') || '';
            document.getElementById('city').value = urlParams.get('city') || '';
            document.getElementById('barangay').value = urlParams.get('barangay') || '';
            if (urlParams.get('city')) {
                updateBarangays();
                document.getElementById('barangay').value = urlParams.get('barangay') || '';
            }
        };

        document.getElementById('addressForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const response = await fetch('update_address.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            const errorDiv = document.getElementById('errorMessages');
            const successDiv = document.getElementById('successMessage');
            errorDiv.innerHTML = '';
            successDiv.innerHTML = '';
            if (result.errors.length > 0) {
                errorDiv.innerHTML = result.errors.map(error => `<p>${error}</p>`).join('');
            } else {
                successDiv.innerHTML = result.success;
                setTimeout(() => window.close(), 1000);
            }
        });
    </script>
</body>
</html>