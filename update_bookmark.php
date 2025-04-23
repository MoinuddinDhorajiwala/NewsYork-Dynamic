<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$bookmark_id = filter_var($_POST['bookmark_id'] ?? 0, FILTER_VALIDATE_INT);
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

if (!$bookmark_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => 'Please provide a valid bookmark ID'
    ]);
    exit;
}

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

try {
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

    // Check if bookmark exists and belongs to the user
    $checkStmt = $conn->prepare("SELECT id FROM bookmarks WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $bookmark_id, $user_id);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bookmark not found or unauthorized']);
        exit;
    }

    // Check if the new URL already exists for this user (excluding current bookmark)
    $duplicateCheck = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND article_id = ? AND id != ?");
    $duplicateCheck->bind_param("iii", $user_id, $article_id, $bookmark_id);
    $duplicateCheck->execute();
    $duplicateResult = $duplicateCheck->get_result();

    if ($duplicateResult->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This article is already bookmarked']);
        exit;
    }

    // Update bookmark
    $stmt = $conn->prepare("UPDATE bookmarks SET article_id = ?, article_url = ?, title = ?, description = ?, image_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?");
    $stmt->bind_param("issssii", $article_id, $article_url, $title, $description, $image_url, $bookmark_id, $user_id);
    $success = $stmt->execute();

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Bookmark update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while updating the bookmark']);
}