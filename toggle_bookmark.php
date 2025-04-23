<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Clean any existing output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON content type header
header('Content-Type: application/json');

// Start fresh output buffer
ob_start();
session_start();
require 'db.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not logged in');
    }

    // Validate required POST parameters
    if (!isset($_POST['article_url']) || !isset($_POST['title']) || !isset($_POST['description'])) {
        throw new Exception('Missing required parameters');
    }

    $user_id = $_SESSION['user_id'];
    $article_url = $_POST['article_url'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $image_url = $_POST['image_url'] ?? '';

    // Check if article exists in articles table, if not, insert it
    $stmt = $conn->prepare("INSERT IGNORE INTO articles (title, description, article_url, image_url) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception('Failed to prepare article insert statement: ' . $conn->error);
    }
    $stmt->bind_param("ssss", $title, $description, $article_url, $image_url);
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert article: ' . $stmt->error);
    }
    $stmt->close();

    // Get article_id from articles table
    $stmt = $conn->prepare("SELECT id FROM articles WHERE article_url = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare article select statement: ' . $conn->error);
    }
    $stmt->bind_param("s", $article_url);
    if (!$stmt->execute()) {
        throw new Exception('Failed to select article: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $article = $result->fetch_assoc();
    $article_id = $article['id'];
    $stmt->close();

    $stmt = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND article_id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare bookmark check statement: ' . $conn->error);
    }
    $stmt->bind_param("ii", $user_id, $article_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to check bookmark: ' . $stmt->error);
    }
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows > 0) {
        // Bookmark exists, remove it
        $stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = ? AND article_id = ?");
        if (!$stmt) {
            throw new Exception('Failed to prepare bookmark delete statement: ' . $conn->error);
        }
        $stmt->bind_param("ii", $user_id, $article_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete bookmark: ' . $stmt->error);
        }
        $response = [
            'success' => true,
            'is_bookmarked' => false,
            'message' => 'Bookmark removed successfully'
        ];
    } else {
        // Bookmark doesn't exist, add it
        $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, article_id, title, description, image_url) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception('Failed to prepare bookmark insert statement: ' . $conn->error);
        }
        $stmt->bind_param("iisss", $user_id, $article_id, $title, $description, $image_url);
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert bookmark: ' . $stmt->error);
        }
        $response = [
            'success' => true,
            'is_bookmarked' => true,
            'message' => 'Article bookmarked successfully'
        ];
    }

    // Clean output buffer and send JSON response
    ob_clean();
    echo json_encode($response);

} catch (Exception $e) {
    // Log error and send error response
    error_log('Bookmark error: ' . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    // Close any open statement
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    // End output buffering
    ob_end_flush();
}