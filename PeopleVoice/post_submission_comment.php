<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../db.php';
require_once '../auth.php';

// Get JSON data from request body
$data = json_decode(file_get_contents('php://input'), true);

// Validate input data
if (!isset($data['submission_id']) || !isset($data['comment_text'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit;
}

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User must be logged in to comment'
    ]);
    exit;
}

$submissionId = intval($data['submission_id']);
$commentText = trim($data['comment_text']);
$userId = $_SESSION['user_id'];

// Validate submission ID and comment text
if ($submissionId <= 0 || empty($commentText)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid submission ID or empty comment'
    ]);
    exit;
}

try {
    // Insert the comment
    $stmt = $conn->prepare(
        "INSERT INTO submission_comments (submission_id, user_id, comment_text) 
         VALUES (?, ?, ?)"
    );
    $stmt->execute([$submissionId, $userId, $commentText]);
    
    // Get the newly inserted comment with user details
    $stmt = $conn->prepare(
        "SELECT c.*, u.username, u.profile_picture 
         FROM submission_comments c 
         LEFT JOIN users u ON c.user_id = u.id 
         WHERE c.id = LAST_INSERT_ID()"
    );
    $stmt->execute();
    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Format the comment for response
    $profilePicturePath = null;
    if ($comment['profile_picture']) {
        $fullPath = __DIR__ . '/../uploads/profile_pictures/' . $comment['profile_picture'];
        if (file_exists($fullPath)) {
            $profilePicturePath = '/newsyork/uploads/profile_pictures/' . $comment['profile_picture'];
        }
    }
    if (!$profilePicturePath) {
        $profilePicturePath = '/newsyork/images/user-circle-solid-216.png';
    }

    $formattedComment = [
        'id' => $comment['id'],
        'user_id' => $comment['user_id'],
        'username' => $comment['username'],
        'profile_picture' => $profilePicturePath,
        'comment_text' => $comment['comment_text'],
        'created_at' => date('M j, Y g:i A', strtotime($comment['created_at']))
    ];

    echo json_encode([
        'success' => true,
        'comment' => $formattedComment
    ]);

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Error in post_submission_comment.php: " . $e->getMessage() . "\n");
    error_log("Error occurred in file: " . $e->getFile() . " on line " . $e->getLine() . "\n");
    error_log("Stack trace:\n" . $e->getTraceAsString() . "\n");
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to post comment. Please try again later.'
    ]);
}

$conn = null;