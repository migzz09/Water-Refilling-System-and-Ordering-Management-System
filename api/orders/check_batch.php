<?php
// API endpoint to check batch availability before payment
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$city = $input['city'] ?? '';
$deliveryDate = $input['deliveryDate'] ?? '';
$items = $input['items'] ?? [];

if (!$city || !$deliveryDate || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Missing city, delivery date, or items.']);
    exit;
}

// Calculate total quantity
$totalQuantity = 0;
foreach ($items as $item) {
    $totalQuantity += (int)($item['quantity'] ?? 0);
}

// Use the same logic as assignBatch in create.php
$vehicle_type = ($city === 'Taguig') ? 'Tricycle' : 'Car';
$capacity = ($vehicle_type === 'Tricycle') ? 5 : 10;
$batch_date = $deliveryDate;

if ($totalQuantity > $capacity) {
    echo json_encode(['success' => false, 'message' => "Order quantity exceeds vehicle capacity for $vehicle_type (Max: $capacity containers). Please split your order into smaller quantities."]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT b.batch_id, b.batch_number, COALESCE(SUM(od.quantity), 0) AS total_quantity
    FROM batches b
    LEFT JOIN orders o ON b.batch_id = o.batch_id
    LEFT JOIN order_details od ON o.reference_id = od.reference_id
    WHERE DATE(b.batch_date) = ? AND b.vehicle_type = ? AND b.batch_status_id = 1
    GROUP BY b.batch_id, b.batch_number
    HAVING total_quantity + ? <= ?
    ORDER BY b.batch_number ASC, b.batch_id ASC
    LIMIT 1
");
$stmt->execute([$batch_date, $vehicle_type, $totalQuantity, $capacity]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if ($batch) {
    echo json_encode(['success' => true, 'message' => 'Batch available.']);
    exit;
}

// No existing batch has space, check if can create new
$stmt = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE DATE(batch_date) = ? AND vehicle_type = ?");
$stmt->execute([$batch_date, $vehicle_type]);
$batch_count = $stmt->fetchColumn();

if ($batch_count >= 3) {
    echo json_encode(['success' => false, 'message' => "Cannot assign batch: Limit of 3 batches reached for $vehicle_type on $batch_date and all are full. Please choose a different delivery date."]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Batch available.']);
