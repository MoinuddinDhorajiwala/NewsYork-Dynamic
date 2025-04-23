<?php
header('Content-Type: application/json');
require 'db.php';

try {
    if (!isset($_GET['article_id'])) {
        throw new Exception('Article ID is required');
    }
    
    $article_id = $_GET['article_id'];
    $query = "SELECT SUM(vote) AS total_votes FROM votes WHERE article_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $article_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    echo json_encode(['total_votes' => $row['total_votes'] ?? 0]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>
