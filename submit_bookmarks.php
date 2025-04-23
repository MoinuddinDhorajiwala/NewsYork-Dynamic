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
if (!isset($_POST['article_url']) || !isset($_POST['title']) || empty(trim($_POST['title']))) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get parameters
$articleUrl = trim($_POST['article_url']);
$title = trim($_POST['title']);
$description = isset($_POST['description']) ? trim($_POST['description']) : null;
$imageUrl = isset($_POST['image_url']) ? trim($_POST['image_url']) : null;
$category = isset($_POST['category']) ? trim($_POST['category']) : null;
$source = isset($_POST['source']) ? trim($_POST['source']) : null;
$publishDate = isset($_POST['publish_date']) ? trim($_POST['publish_date']) : null;

try {
    // Check if bookmark already exists
    $checkStmt = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND article_url = ?");
    $checkStmt->bind_param("is", $userId, $articleUrl);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Article already bookmarked']);
        exit;
    }
   
    // Prepare and execute the insert statement
    $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, article_url, title, description, image_url, category, source, publish_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $userId, $articleUrl, $title, $description, $imageUrl, $category, $source, $publishDate);
    
    if ($stmt->execute()) {
        // Get the new bookmark ID
        $bookmarkId = $conn->insert_id;
        
        // Return success response with bookmark data
        echo json_encode([
            'success' => true, 
            'message' => 'Bookmark added successfully',
            'bookmark' => [
                'id' => $bookmarkId,
                'user_id' => $userId,
                'article_url' => $articleUrl,
                'title' => $title,
                'description' => $description,
                'image_url' => $imageUrl,
                'category' => $category,
                'source' => $source,
                'publish_date' => $publishDate,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>