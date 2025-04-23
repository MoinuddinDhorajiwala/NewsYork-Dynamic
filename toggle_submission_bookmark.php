<?php
require_once 'auth.php';
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not logged in']));
}

$submission_id = $_POST['submission_id'] ?? null;

if (!$submission_id) {
    die(json_encode(['success' => false, 'message' => 'Invalid submission']));
}

// Check if already bookmarked
$stmt = $conn->prepare("SELECT 1 FROM submission_bookmarks WHERE user_id = ? AND submission_id = ?");
$stmt->bind_param("ii", $_SESSION['user_id'], $submission_id);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    // Remove bookmark
    $stmt = $conn->prepare("DELETE FROM submission_bookmarks WHERE user_id = ? AND submission_id = ?");
    $stmt->bind_param("ii", $_SESSION['user_id'], $submission_id);
} else {
    // Add bookmark
    $stmt = $conn->prepare("INSERT INTO submission_bookmarks (user_id, submission_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $_SESSION['user_id'], $submission_id);
}

$success = $stmt->execute();
echo json_encode(['success' => $success]);