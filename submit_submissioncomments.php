<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not logged in']));
}

$submission_id = $_POST['submission_id'] ?? null;
$comment_text = $_POST['comment_text'] ?? null;

if (!$submission_id || !$comment_text) {
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

// Check if submission exists
$checkSubmission = $conn->prepare("SELECT id FROM submissions WHERE id = ?");
$checkSubmission->bind_param("i", $submission_id);
$checkSubmission->execute();
if ($checkSubmission->get_result()->num_rows === 0) {
    die(json_encode(['success' => false, 'message' => 'Submission not found']));
}

// Insert comment
$stmt = $conn->prepare("INSERT INTO submission_comments (user_id, submission_id, comment_text) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $_SESSION['user_id'], $submission_id, $comment_text);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Comment posted successfully',
        'comment' => [
            'id' => $stmt->insert_id,
            'user_id' => $_SESSION['user_id'],
            'submission_id' => $submission_id,
            'comment_text' => $comment_text,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to post comment']);
}
?> 