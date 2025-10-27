<?php
// Database connection settings
$host = 'localhost';
$port = '3306';
$dbname = 'wrsoms';
$username = 'root';
$password = ''; // Replace with your phpMyAdmin password

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());

}
$pdo->exec("SET time_zone = '-07:00';"); // Set to PST
?>
