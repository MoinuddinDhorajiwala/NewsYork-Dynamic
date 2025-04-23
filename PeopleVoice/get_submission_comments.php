<?php
header('Content-Type: application/json');
require_once '../db.php';

// Get submission ID from request
$submissionId = isset($_GET['submission_id']) ? intval($_GET['submission_id']) : 0;

if ($submissionId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid submission ID'
    ]);
    exit;
}

// Prepare and execute query
try {
    $stmt = $conn->prepare(
        "SELECT c.*, u.username, u.profile_picture
         FROM submission_comments c 
         LEFT JOIN users u ON c.user_id = u.id 
         WHERE c.submission_id = ? 
         ORDER BY c.created_at DESC"
    );
    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    while ($comment = $result->fetch_assoc()) {
        $comments[] = $comment;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching comments'
    ]);
    exit;
}

// Format dates and sanitize output
foreach ($comments as &$comment) {
    $comment['created_at'] = date('M j, Y g:i A', strtotime($comment['created_at']));
    $comment['comment_text'] = htmlspecialchars($comment['comment_text']);
    $comment['username'] = htmlspecialchars($comment['username'] ?? 'Anonymous');
    
    // Handle profile picture path
    if ($comment['profile_picture']) {
        $profilePicPath = '../uploads/profile_pictures/' . $comment['profile_picture'];
        if (file_exists($profilePicPath)) {
            $comment['profile_picture'] = 'uploads/profile_pictures/' . $comment['profile_picture'];
        } else {
            $comment['profile_picture'] = '../images/user-circle-solid-216.png';
        }
    } else {
        $comment['profile_picture'] = '../images/user-circle-solid-216.png';
    }
}

echo json_encode([
    'success' => true,
    'comments' => $comments
]);

$conn->close();