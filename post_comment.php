<?php
require 'db.php';
session_start();
header('Content-Type: application/json');

// Security check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    // Validate inputs
    if (!isset($_POST['article_id']) || !isset($_POST['comment_text']) || empty($_POST['comment_text'])) {
        throw new Exception('Missing required fields');
    }
    
    $user_id = $_SESSION['user_id'];
    $article_id = $_POST['article_id'];
    $comment_text = trim($_POST['comment_text']);
    
    // Basic profanity filter (expand this list as needed)
    $banned_words = ['badword1', 'badword2', 'inappropriate', 'vulgar'];
    foreach ($banned_words as $word) {
        if (stripos($comment_text, $word) !== false) {
            throw new Exception('Your comment contains inappropriate language');
        }
    }
    
    // Insert comment
    $stmt = $conn->prepare("INSERT INTO comments (article_id, user_id, comment_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $article_id, $user_id, $comment_text);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to save comment: ' . $stmt->error);
    }
    
    $comment_id = $stmt->insert_id;
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment posted successfully',
        'comment_id' => $comment_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>