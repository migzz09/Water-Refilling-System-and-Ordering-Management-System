<?php
// Test archive query to see actual SQL errors
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../config/connect.php';

echo "<h2>Testing Archive Query</h2>";
echo "<pre>";

try {
    echo "Step 1: Testing basic connection...\n";
    $test = $pdo->query("SELECT 1")->fetch();
    echo "✓ Database connected\n\n";
    
    echo "Step 2: Checking delivery_status table...\n";
    $stmt = $pdo->query("SELECT * FROM delivery_status");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Delivery statuses:\n";
    print_r($statuses);
    echo "\n";
    
    echo "Step 3: Checking for completed orders...\n";
    $dateFilter = date('Y-m-d');
    echo "Looking for orders on: $dateFilter\n\n";
    
    // First, let's see what orders exist today
    $stmt = $pdo->prepare("
        SELECT 
            o.reference_id,
            o.delivery_date,
            b.batch_id,
            d.delivery_status_id,
            ds.status_name
        FROM orders o
        LEFT JOIN batches b ON o.batch_id = b.batch_id
        LEFT JOIN deliveries d ON b.batch_id = d.batch_id
        LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
        WHERE o.delivery_date = ?
        LIMIT 5
    ");
    $stmt->execute([$dateFilter]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Orders found today:\n";
    print_r($orders);
    echo "\n";
    
    echo "Step 4: Looking for completed orders (delivery_status_id = 3)...\n";
    $stmt = $pdo->prepare("
        SELECT 
            o.reference_id,
            o.delivery_date,
            d.delivery_status_id,
            ds.status_name
        FROM orders o
        LEFT JOIN batches b ON o.batch_id = b.batch_id
        LEFT JOIN deliveries d ON b.batch_id = d.batch_id
        LEFT JOIN delivery_status ds ON d.delivery_status_id = ds.delivery_status_id
        WHERE d.delivery_status_id = 3
        AND o.delivery_date = ?
    ");
    $stmt->execute([$dateFilter]);
    $completed = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Completed orders:\n";
    print_r($completed);
    echo "\n";
    
    echo "✓ Query successful!\n";
    echo "Found " . count($completed) . " completed orders\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
ob_end_flush();
?>
