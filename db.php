<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'newsyork';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => "Connection failed: " . $conn->connect_error
    ]));
}

// Set charset to handle special characters
$conn->set_charset("utf8mb4");
?>