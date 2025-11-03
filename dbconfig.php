<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

$host = "localhost";
$user = "root";
$password = "";
$dbname = "oms";
$dsn = "mysql:host={$host}; dbname={$dbname};";

try {
    // Initialize PDO connection
    $pdo = new PDO($dsn, $user, $password);
    // Throw exceptions on errors
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set timezone
    $pdo->exec("SET time_zone = '+08:00';");
} catch (PDOException $e) {
    // Handle connection errors
    echo "Database connection failed: " . htmlspecialchars($e->getMessage());
    exit;
}