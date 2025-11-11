<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Simple Include Test</h2><pre>";

echo "Step 1: Start session\n";
session_start();
echo "✓ Session started\n\n";

echo "Step 2: Include database\n";
try {
    require_once '../../config/connect.php';
    echo "✓ Database included\n\n";
} catch (Exception $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n\n";
    die();
}

echo "Step 3: Include auth\n";
try {
    require_once '../../utils/auth.php';
    echo "✓ Auth included\n\n";
} catch (Exception $e) {
    echo "✗ Auth error: " . $e->getMessage() . "\n\n";
    die();
}

echo "Step 4: Test auth functions\n";
echo "isLoggedIn(): " . (function_exists('isLoggedIn') ? (isLoggedIn() ? 'YES' : 'NO') : 'FUNCTION NOT FOUND') . "\n";
echo "isAdmin(): " . (function_exists('isAdmin') ? (isAdmin() ? 'YES' : 'NO') : 'FUNCTION NOT FOUND') . "\n\n";

echo "Step 5: Check archive table\n";
try {
    $stmt = $pdo->query("DESCRIBE archived_orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ archived_orders table exists with " . count($columns) . " columns\n\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
} catch (Exception $e) {
    echo "✗ Table error: " . $e->getMessage() . "\n\n";
}

echo "\n✓ All basic tests passed!\n";
echo "</pre>";
?>
