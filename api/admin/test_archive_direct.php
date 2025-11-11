<?php
// Direct test of archive_completed.php with error display
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Direct Archive Test</h2>";
echo "<pre>";

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['archive_type'] = 'manual';
$_POST['date_filter'] = date('Y-m-d');

echo "Simulating POST to archive_completed.php\n";
echo "POST data: ";
print_r($_POST);
echo "\n\n";

echo "Including archive_completed.php...\n";
echo "===========================================\n\n";

// Capture output from the include
ob_start();
try {
    include './archive_completed.php';
    $output = ob_get_clean();
    echo "Output:\n";
    echo $output;
} catch (Exception $e) {
    ob_end_clean();
    echo "Exception caught:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n</pre>";
?>
