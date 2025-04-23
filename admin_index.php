<?php
session_start();
require 'db.php';

// Array of authorized admin user IDs
$admin_user_ids = [18, 2, 3]; // Replace with actual admin user IDs

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        if (password_verify($password, $user['password'])) {
            // Check if the user is an admin
            if (in_array($user['id'], $admin_user_ids)) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = true;
                header("Location: admin_dashboard.php");
                exit;
            } else {
                echo "<script>alert('❌ Access denied. Admin privileges required.');</script>";
            }
        } else {
            echo "<script>alert('❌ Incorrect password. Try again.');</script>";
        }
    } else {
        echo "<script>alert('❌ User not found.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - NewsYork</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <img src="logo.png" alt="Logo" class="logo">
        <h1>NewsYork Admin</h1>
        <form id="login-form" action="admin_index.php" method="POST">
            <input type="email" id="login-email" name="email" class="input-field" placeholder="Admin Email" required>
            <input type="password" id="login-password" name="password" class="input-field" placeholder="Password" required>
            <button type="submit" name="login" class="login-btn">Admin Log In</button>
        </form>
        <p class="signup-text"><a href="index.php">Back to User Login</a></p>
    </div>
</body>
</html>