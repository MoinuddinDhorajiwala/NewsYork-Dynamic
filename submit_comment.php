<?php
// Start session and include database connection
session_start();
require 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Check if required parameters are provided
if (!isset($_POST['article_url']) || !isset($_POST['comment_text']) || empty(trim($_POST['comment_text']))) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get parameters
$articleUrl = $_POST['article_url'];
$commentText = trim($_POST['comment_text']);

try {
    // Prepare and execute the insert statement
    $stmt = $conn->prepare("INSERT INTO comments (article_url, user_id, comment_text, reports, created_at) VALUES (?, ?, ?, 0, NOW())");
    $stmt->bind_param("sis", $articleUrl, $userId, $commentText);
    
    if ($stmt->execute()) {
        // Get the new comment ID
        $commentId = $conn->insert_id;
        
        // Get user information for the response
        $userStmt = $conn->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
        $userStmt->bind_param("i", $userId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userData = $userResult->fetch_assoc();
        
        // Return success response with comment data
        echo json_encode([
            'success' => true, 
            'message' => 'Comment added successfully',
            'comment' => [
                'id' => $commentId,
                'user_id' => $userId,
                'username' => $userData['username'],
                'profile_image' => $userData['profile_picture'] ?? 'uploads/user-circle-solid-216.png',
                'comment_text' => $commentText,
                'created_at' => date('Y-m-d H:i:s'),
                'reports' => 0
            ]
        ]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>