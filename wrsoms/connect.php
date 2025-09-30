<?php
// Database connection settings
$host = 'localhost';
$port = '4306';
$dbname = 'wrsoms';
$username = 'root';
$password = 'your_password'; // Replace with your phpMyAdmin password

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}