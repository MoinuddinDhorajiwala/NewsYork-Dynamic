<?php
require_once 'db.php';
require_once 'auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

// Check if article_url is provided
if (!isset($_POST['article_url'])) {
    echo json_encode(['success' => false, 'message' => 'Article URL is required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$article_url = urldecode($_POST['article_url']);

// Delete the bookmark
$stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = ? AND article_url = ?");
$stmt->bind_param("is", $user_id, $article_url);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Bookmark removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Bookmark not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Error removing bookmark']);
}

$stmt->close();
$conn->close();
?>