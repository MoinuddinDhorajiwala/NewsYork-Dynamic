<?php
// Start session and include database connection
session_start();
require 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if article URL is provided
if (!isset($_GET['article_url'])) {
    echo json_encode(['success' => false, 'message' => 'Article URL is required']);
    exit;
}

$articleUrl = $_GET['article_url'];

try {
    // Prepare and execute the query to get comments with user information
    // Only fetch comments with less than 50 reports
    $stmt = $conn->prepare("
        SELECT c.id, c.user_id, c.comment_text, c.created_at, c.reports,
               u.username, u.profile_picture
        FROM comments c
        JOIN users u ON c.user_id = u.id
        WHERE c.article_url = ? AND c.reports < 50
        ORDER BY c.created_at DESC
    ");
    
    $stmt->bind_param("s", $articleUrl);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        // Format the timestamp
        $timestamp = strtotime($row['created_at']);
        $timeAgo = formatTimeAgo($timestamp);
        
        $comments[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'profile_image' => $row['profile_picture'] ?? 'uploads/user-circle-solid-216.png',
            'comment_text' => $row['comment_text'],
            'created_at' => $row['created_at'],
            'time_ago' => $timeAgo,
            'reports' => $row['reports']
        ];
    }
    
    echo json_encode(['success' => true, 'comments' => $comments]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Helper function to format time ago
function formatTimeAgo($timestamp) {
    $currentTime = time();
    $timeDiff = $currentTime - $timestamp;
    
    if ($timeDiff < 60) {
        return "just now";
    } elseif ($timeDiff < 3600) {
        $minutes = floor($timeDiff / 60);
        return $minutes . ($minutes == 1 ? " minute ago" : " minutes ago");
    } elseif ($timeDiff < 86400) {
        $hours = floor($timeDiff / 3600);
        return $hours . ($hours == 1 ? " hour ago" : " hours ago");
    } elseif ($timeDiff < 2592000) {
        $days = floor($timeDiff / 86400);
        return $days . ($days == 1 ? " day ago" : " days ago");
    } elseif ($timeDiff < 31536000) {
        $months = floor($timeDiff / 2592000);
        return $months . ($months == 1 ? " month ago" : " months ago");
    } else {
        $years = floor($timeDiff / 31536000);
        return $years . ($years == 1 ? " year ago" : " years ago");
    }
}
?>