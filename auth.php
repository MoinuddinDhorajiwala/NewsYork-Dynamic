<?php
session_start();
require 'db.php';

// Handle Signup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $fullname = $_POST['fullname'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $confirm_password = $_POST['confirm_password'];

    if ($_POST['password'] !== $confirm_password) {
        echo "Passwords do not match.";
        exit;
    }

    // Check if the username already exists
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
    $checkStmt->bind_param("s", $username);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    $row = $result->fetch_assoc();
    $userExists = $row['count'];

    if ($userExists > 0) {
        echo "Username already exists. Please choose a different one.";
        exit;
    }

    try {
        $stmt = $conn->prepare("INSERT INTO users (fullname, username, email, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $fullname, $username, $email, $password);
        $stmt->execute();

        // Get the newly created user's ID
        $user_id = $conn->insert_id;
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;

        header("Location: homepage.php");
        exit;
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header("Location: homepage.php");
        exit;
    } else {
        echo "Invalid email or password.";
        exit;
    }
}

// Handle Guest Access
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guest'])) {
    $_SESSION['guest'] = true;
    header("Location: homepage.php");
    exit;
}
?>