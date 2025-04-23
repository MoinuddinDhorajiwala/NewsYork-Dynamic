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

$userId = $_SESSION['user_id'];

try {
    // Prepare and execute the query to get bookmarks with article information
    $stmt = $conn->prepare("
        SELECT id, user_id, article_id, article_url, title, description,
               image_url, created_at
        FROM bookmarks
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookmarks = [];
    while ($row = $result->fetch_assoc()) {
        // Format the timestamp
        $timestamp = strtotime($row['created_at']);
        $timeAgo = formatTimeAgo($timestamp);
        
        $bookmarks[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'article_id' => $row['article_id'],
            'article_url' => $row['article_url'],
            'title' => $row['title'],
            'description' => $row['description'],
            'image_url' => $row['image_url'],
            'created_at' => $row['created_at'],
            'time_ago' => $timeAgo
        ];
    }
    
    echo json_encode(['success' => true, 'bookmarks' => $bookmarks]);
    
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