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
    // Set timezone to Philippine Time (UTC+8)
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    // Don't die() here as it breaks API JSON responses
    // Let the calling script handle the error
    throw $e;
}

