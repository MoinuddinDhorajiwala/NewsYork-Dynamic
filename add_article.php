<?php
require 'db.php';
header('Content-Type: application/json');

// Enable error logging
error_log("Article submission attempt started: " . json_encode($_POST));

try {
    if (!isset($_POST['article_url']) || empty($_POST['article_url'])) {
        throw new Exception('Missing article URL');
    }

    $article_url = $_POST['article_url'];
    $title = $_POST['article_title'] ?? '';
    $image = $_POST['article_image'] ?? '';
    $description = $_POST['article_description'] ?? '';

    // Debug log
    error_log("Processing article: URL=$article_url, Title=$title");

    // Check if article exists
    $stmt = $conn->prepare("SELECT id FROM articles WHERE article_url = ?");
    $stmt->bind_param("s", $article_url);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Insert new article with the correct column names
        $stmt = $conn->prepare("
            INSERT INTO articles 
            (article_url, title, image_url, description, upvotes, downvotes, total_votes) 
            VALUES (?, ?, ?, ?, 0, 0, 0)
        ");
        $stmt->bind_param("ssss", $article_url, $title, $image, $description);
        
        if ($stmt->execute()) {
            error_log("Article inserted successfully: " . $article_url);
            echo json_encode(['success' => true, 'message' => 'Article added successfully']);
        } else {
            error_log("DB Error: " . $stmt->error);
            throw new Exception('Database error: ' . $stmt->error);
        }
    } else {
        // Article already exists
        error_log("Article already exists: " . $article_url);
        echo json_encode(['success' => true, 'message' => 'Article already exists']);
    }
    
} catch (Exception $e) {
    error_log("Error adding article: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>