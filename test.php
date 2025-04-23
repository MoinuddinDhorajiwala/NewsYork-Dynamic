<?php
require 'db.php';

$article_url = "https://example.com/article1";
$upvotes = 1;
$downvotes = 0;
$total_votes = 1;

$query = "INSERT INTO articles (article_url, upvotes, downvotes, total_votes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE upvotes = upvotes + 1, total_votes = total_votes + 1";
$stmt = $conn->prepare($query);
$stmt->bind_param("siii", $article_url, $upvotes, $downvotes, $total_votes);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "Article vote count updated successfully!";
} else {
    echo "Failed to update article vote count.";
}

$stmt->close();
$conn->close();
?>