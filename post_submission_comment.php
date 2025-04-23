<?php
// Start session and include database connection
session_start();
require 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to post a comment']);
    exit;
}

// Check if required data is provided
if (!isset($data['submission_id']) || !isset($data['comment_text']) || trim($data['comment_text']) === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

$submissionId = (int)$data['submission_id'];
$userId = (int)$_SESSION['user_id'];
$commentText = trim($data['comment_text']);

try {
    // Prepare and execute the insert statement
    $stmt = $conn->prepare(
        "INSERT INTO submission_comments (submission_id, user_id, comment_text) 
        VALUES (?, ?, ?)"
    );
    $stmt->bind_param("iis", $submissionId, $userId, $commentText);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comment posted successfully']);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}