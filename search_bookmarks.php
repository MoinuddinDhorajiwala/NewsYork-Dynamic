<?php
require_once 'db.php';
require_once 'auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// Prepare the SQL query with search functionality
$sql = "SELECT b.*, 
    COALESCE(CASE WHEN v.vote_type = 'upvote' THEN 1 WHEN v.vote_type = 'downvote' THEN -1 ELSE 0 END, 0) as user_vote,
    SUM(CASE WHEN v2.vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
    SUM(CASE WHEN v2.vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes,
    COUNT(v2.id) as total_votes
FROM bookmarks b 
LEFT JOIN votes v ON v.article_url = b.article_url AND v.user_id = ?
LEFT JOIN votes v2 ON v2.article_url = b.article_url
WHERE b.user_id = ?";

if (!empty($keyword)) {
    $sql .= " AND (b.title LIKE ? OR b.description LIKE ?)";
}

$sql .= " GROUP BY b.id, b.title, b.description, b.image_url, b.created_at, v.vote_type
ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);

if (!empty($keyword)) {
    $searchPattern = "%$keyword%";
    $stmt->bind_param("iiss", $user_id, $user_id, $searchPattern, $searchPattern);
} else {
    $stmt->bind_param("ii", $user_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$bookmarks = [];

while ($row = $result->fetch_assoc()) {
    $bookmarks[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'description' => $row['description'],
        'article_url' => $row['article_url'],
        'image_url' => $row['image_url'] ?? 'images/default-image.jpg',
        'total_votes' => (int)$row['total_votes'],
        'user_vote' => (int)$row['user_vote']
    ];
}

echo json_encode([
    'success' => true,
    'bookmarks' => $bookmarks
]);

$stmt->close();
$conn->close();
?>