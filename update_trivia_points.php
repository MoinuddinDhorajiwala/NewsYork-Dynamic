<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get points from request
$points = isset($_POST['points']) ? intval($_POST['points']) : 0;

if ($points <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid points']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Update points in database
$stmt = $conn->prepare("
    UPDATE trivia 
    SET weekly_points = weekly_points + ?, 
        total_points = total_points + ?,
        last_updated = CURRENT_TIMESTAMP
    WHERE user_id = ?
");
$stmt->bind_param("iii", $points, $points, $user_id);
$result = $stmt->execute();

if (!$result) {
    echo json_encode(['success' => false, 'message' => 'Failed to update points']);
    exit;
}

// Get updated points
$get_points = $conn->prepare("SELECT weekly_points, total_points FROM trivia WHERE user_id = ?");
$get_points->bind_param("i", $user_id);
$get_points->execute();
$points_result = $get_points->get_result();
$points_data = $points_result->fetch_assoc();

echo json_encode([
    'success' => true, 
    'weekly_points' => $points_data['weekly_points'],
    'total_points' => $points_data['total_points']
]);
?>