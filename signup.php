<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $fullname = $_POST['fullname'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match.');</script>";
        exit;
    }

    // Hash the password before storing
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Check if the username or email already exists
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
    $checkStmt->execute([$username, $email]);
    $userExists = (int)$checkStmt->fetchColumn();

    if ($userExists > 0) {
        echo "<script>alert('Username or Email already exists. Please choose a different one.');</script>";
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO users (fullname, username, email, password) VALUES (?, ?, ?, ?)");
        $stmt->execute([$fullname, $username, $email, $hashed_password]);

        echo "<script>alert('Account created successfully! Please log in.');</script>";
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up Page</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: url('images/mountains2.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .signup-container {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .input-field {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid rgba(228, 230, 232, 0.8);
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .input-field:focus {
            outline: none;
            border-color: rgba(0, 149, 246, 0.8);
            background: rgba(255, 255, 255, 0.95);
        }

        .signup-btn {
            width: 100%;
            padding: 10px;
            background-color: #0095f6;
            border: none;
            border-radius: 5px;
            color: white;
            font-size: 16px;
            cursor: pointer;
            margin-top: 15px;
        }

        .signup-btn:hover {
            background-color: #007bb5;
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .login-text {
            margin-top: 20px;
            color: #4a5568;
        }

        .login-text a {
            color: #0095f6;
            text-decoration: none;
        }

        .login-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <img src="logo.png" alt="Logo" class="logo">
        <h1>NewsYork</h1>
        <form id="signup-form" action="auth.php" method="POST">
            <input type="email" id="signup-email" name="email" class="input-field" placeholder="Email" required>
            <input type="text" id="signup-fullname" name="fullname" class="input-field" placeholder="Full Name" required>
            <input type="text" id="signup-username" name="username" class="input-field" placeholder="Username" required>
            <input type="password" id="signup-password" name="password" class="input-field" placeholder="Password" required>
            <input type="password" id="confirm-password" name="confirm_password" class="input-field" placeholder="Confirm Password" required>
            <button type="submit" name="signup" class="signup-btn">Sign Up</button>
        </form>
        <p class="login-text">Already have an account? <a href="index.php">Log in</a></p>
    </div>
</body>
</html>