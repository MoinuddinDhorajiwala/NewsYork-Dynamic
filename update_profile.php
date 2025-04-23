<?php
session_start();
require 'db.php';

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $fullname = $_POST['fullname'];
    $username = $_POST['username'];
    $bio = $_POST['bio'] ?? null; // Optional field
    $dob = $_POST['dob'] ?? null; // Optional field
    $location = $_POST['location'] ?? null; // Optional field

    // Update user data in the database
    $stmt = $conn->prepare("UPDATE users SET fullname = ?, username = ?, bio = ?, dob = ?, location = ? WHERE id = ?");
    $stmt->execute([$fullname, $username, $bio, $dob, $location, $user_id]);

    // Redirect back to the profile page
    header("Location: profile.php");
    exit;
} else {
    // If the request method is not POST, redirect to the profile page
    header("Location: profile.php");
    exit;
}
?>