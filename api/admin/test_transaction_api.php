<?php
session_start();

// Simulate admin login
$_SESSION['is_admin'] = 1;
$_SESSION['username'] = 'admin';

echo "Session set. Testing API...\n\n";

// Include the API
ob_start();
include 'transaction_history.php';
$output = ob_get_clean();

echo "API Response:\n";
echo $output;
?>
