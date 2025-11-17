<?php
header('Content-Type: application/json');
session_start();

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$ncr_cities = [
    'Taguig' => [
        'Bagumbayan','Bambang','Calzada','Central Bicutan','Central Signal Village',
        'Fort Bonifacio','Hagonoy','Ibayo-Tipas','Katuparan','Ligid-Tipas','Lower Bicutan',
        'Maharlika Village','Napindan','New Lower Bicutan','North Daang Hari','North Signal Village',
        'Palingon','Pinagsama','San Miguel','Santa Ana','South Daang Hari','South Signal Village',
        'Tanyag','Tuktukan','Upper Bicutan','Ususan','Wawa','Western Bicutan',
        'Comembo','Cembo','South Cembo','East Rembo','West Rembo','Pembo','Pitogo'
    ],
    'Quezon City' => ['Bagong Pag-asa','Batasan Hills','Commonwealth','Holy Spirit','Payatas'],
    'Manila' => ['Tondo','Binondo','Ermita','Malate','Paco'],
    'Makati' => ['Bangkal','Bel-Air','Magallanes','Pio del Pilar','San Lorenzo'],
    'Pasig' => ['Bagong Ilog','Oranbo','San Antonio','Santa Lucia','Ugong'],
    'Pateros' => ['Aguho','Martyrs','San Roque','Santa Ana']
];

$errors = [];

$address_id = isset($input['address_id']) ? (int)$input['address_id'] : 0;
if ($address_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => ['Invalid address id']]);
    exit;
}

if (!isset($_SESSION['addresses'][$address_id])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'errors' => ['Address not found']]);
    exit;
}

$first_name = trim($input['first_name'] ?? '');
$middle_name = trim($input['middle_name'] ?? '');
$last_name = trim($input['last_name'] ?? '');
$customer_contact = trim($input['customer_contact'] ?? '');
$street = trim($input['street'] ?? '');
$barangay = trim($input['barangay'] ?? '');
$city = trim($input['city'] ?? '');
$province = trim($input['province'] ?? 'Metro Manila');

if ($first_name === '' || $last_name === '') {
    $errors[] = 'First name and last name are required.';
}
if ($customer_contact === '' || !preg_match('/^09\d{9}$/', $customer_contact)) {
    $errors[] = 'Valid contact number (e.g., 09XXXXXXXXX) is required.';
}
if ($street === '') {
    $errors[] = 'Street is required.';
}
if ($city === '' || !array_key_exists($city, $ncr_cities)) {
    $errors[] = 'Invalid or missing city.';
}
if ($barangay === '' || !in_array($barangay, $ncr_cities[$city] ?? [])) {
    $errors[] = 'Invalid barangay for selected city.';
}
if ($province !== 'Metro Manila') {
    $errors[] = 'Province must be Metro Manila.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$_SESSION['addresses'][$address_id] = array_merge($_SESSION['addresses'][$address_id], [
    'first_name' => $first_name,
    'middle_name' => $middle_name,
    'last_name' => $last_name,
    'customer_contact' => $customer_contact,
    'street' => $street,
    'barangay' => $barangay,
    'city' => $city,
    'province' => $province
]);

echo json_encode(['success' => true, 'address_id' => $address_id]);
?>
