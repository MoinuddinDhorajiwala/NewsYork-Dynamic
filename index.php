<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // 🛠️ Debugging: Print stored hash & entered password
        echo "Stored Hash: " . $user['password'] . "<br>";
        echo "Entered Password: " . $password . "<br>";

        if (password_verify($password, $user['password'])) {
            echo "✅ Password Verified!";
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header("Location: homepage.php");
            exit;
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
    <title>Login Page</title>
    <link rel="stylesheet" href="styles.css">
    <style>
body {
    background: url('images/mountains.png') no-repeat center center fixed;
    background-size: cover;
    margin: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

.login-container {
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
        .guest{
    width: 100%;
    padding: 12px;
    background-color: #f8f9fa;
    border: 1px solid #e4e6e8;
    border-radius: 5px;
    color: #4a5568;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    margin: 15px 0;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.guest:hover {
    background-color: #e9ecef;
    border-color: #cbd5e0;
    color: #2d3748;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}
.login-btn, .signup-btn {
    width: 100%;
    padding: 10px;
    background-color: #0095f6;
    border: none;
    border-radius: 5px;
    color: white;
    font-size: 16px;
    cursor: pointer;
}

.login-btn:hover, .signup-btn:hover {
    background-color: #007bb5;
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}
    </style>
</head>
<body>
    <div class="login-container">
        <img src="logo.png" alt="Logo" class="logo">
        <h1>NewsYork</h1>
        <form id="login-form" action="auth.php" method="POST">
            <input type="email" id="login-email" name="email" class="input-field" placeholder="Email" required>
            <input type="password" id="login-password" name="password" class="input-field" placeholder="Password" required>
            <button type="submit" name="login" class="login-btn">Log In</button>
        </form>
        <div class="divider">OR</div>
        <form action="auth.php" method="POST">
            <button type="submit" name="guest" class="guest">Continue as Guest</button>
        </form>
        <p class="signup-text">Don't have an account? <a href="signup.php">Sign up</a></p>
    </div>

</body>
</html>