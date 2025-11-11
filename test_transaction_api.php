<?php
// Test transaction history API
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Transaction History API</h2>";

session_start();
$_SESSION['customer_id'] = 1; // Set a test customer ID
$_SESSION['username'] = 'test';

echo "<p>Session set with customer_id = 1</p>";

// Simulate the GET request
$_GET['search'] = '';

echo "<p>Requiring transaction_history.php...</p>";

require_once 'api/orders/transaction_history.php';
