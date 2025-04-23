<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$article_url = trim($_POST['article_url'] ?? '');
$article_url = filter_var($article_url, FILTER_VALIDATE_URL);

// Additional URL validation
if ($article_url) {
    $parsed_url = parse_url($article_url);
    if (!isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
        $article_url = false;
    }
}
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$image_url = trim($_POST['image_url'] ?? '');
$is_bookmarked = $_POST['is_bookmarked'] ?? '1';

if (!$article_url) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Please provide a valid article URL with proper scheme and host'
    ]);
    exit;
}

// Normalize URL by removing trailing slashes and fragments
$article_url = rtrim(strtok($article_url, '#'), '/');

// Basic sanitization of other fields
$title = strip_tags($title);
$description = strip_tags($description);
$image_url = filter_var(trim($image_url), FILTER_VALIDATE_URL) ?: '';

// First, ensure the article exists in the articles table
$stmt = $conn->prepare("INSERT IGNORE INTO articles (title, description, article_url, image_url) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
$stmt->bind_param("ssss", $title, $description, $article_url, $image_url);
$stmt->execute();
$stmt->close();

// Get the article_id
$stmt = $conn->prepare("SELECT id FROM articles WHERE article_url = ?");
$stmt->bind_param("s", $article_url);
$stmt->execute();
$result = $stmt->get_result();
$article = $result->fetch_assoc();
$article_id = $article['id'];
$stmt->close();

try {
    if ($is_bookmarked === '1') {
        // Check if bookmark already exists
        $checkStmt = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND article_id = ?");
        $checkStmt->bind_param("ii", $user_id, $article_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            // If already bookmarked, treat it as a successful operation
            echo json_encode(['success' => true]);
            exit;
        }

        // Add bookmark with article metadata
        $stmt = $conn->prepare("INSERT INTO bookmarks (user_id, article_id, article_url, title, description, image_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $user_id, $article_id, $article_url, $title, $description, $image_url);
        $success = $stmt->execute();
    } else {
        // Remove bookmark
        $stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = ? AND article_id = ?");
        $stmt->bind_param("ii", $user_id, $article_id);
        $success = $stmt->execute();
    }

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    $errorMessage = $e->getMessage();
    if (strpos($errorMessage, 'Duplicate entry') !== false) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This article is already bookmarked']);
    } else {
        error_log('Bookmark error: ' . $errorMessage);
        echo json_encode(['success' => false, 'message' => 'An error occurred while saving the bookmark']);
    }
}