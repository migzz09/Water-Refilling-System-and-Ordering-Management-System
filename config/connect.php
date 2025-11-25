<?php
// Database connection settings
$host = 'localhost';
$port = '3306';
$dbname = 'wrsoms';
$username = 'root';
$password = ''; // Replace with your phpMyAdmin password

// Initialize global error variable
$GLOBALS['db_connection_error'] = null;

try {
    // Create PDO connection and explicitly set it in global scope
    $GLOBALS['pdo'] = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $GLOBALS['pdo']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $GLOBALS['pdo']->exec("SET time_zone = '+08:00'");
    
    // Also set local $pdo for backward compatibility with scripts that expect it
    $pdo = $GLOBALS['pdo'];
} catch (PDOException $e) {
    error_log("Database connection failed in connect.php: " . $e->getMessage());
    // Set global error so calling scripts can detect the failure
    $GLOBALS['db_connection_error'] = $e->getMessage();
    $GLOBALS['pdo'] = null;
    $pdo = null;
}

