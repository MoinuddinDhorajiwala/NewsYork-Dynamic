<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get leaderboard type from request
$type = isset($_GET['type']) ? $_GET['type'] : 'weekly';

// Determine which points to use for sorting
$points_column = ($type === 'weekly') ? 'weekly_points' : 'total_points';

// Get top 10 users
$stmt = $conn->prepare("
    SELECT u.username, t.$points_column as points
    FROM trivia t
    JOIN users u ON t.user_id = u.id
    WHERE t.$points_column > 0
    ORDER BY t.$points_column DESC
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();

$leaderboard = [];
while ($row = $result->fetch_assoc()) {
    $leaderboard[] = [
        'username' => $row['username'],
        'points' => $row['points']
    ];
}

echo json_encode(['success' => true, 'leaderboard' => $leaderboard]);
?>