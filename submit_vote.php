<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User not logged in'
    ]);
    exit;
}

// Validate inputs
if (!isset($_POST['article_url']) || !isset($_POST['vote_type'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input'
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$articleUrl = $_POST['article_url'];
$voteType = $_POST['vote_type'];

// Validate vote type
if ($voteType !== 'upvote' && $voteType !== 'downvote') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid vote type'
    ]);
    exit;
}

try {
    // Start transaction to ensure data consistency
    $conn->begin_transaction();
    
    // Check if user already voted on this article
    $checkStmt = $conn->prepare("SELECT vote_type FROM votes WHERE user_id = ? AND article_url = ?");
    $checkStmt->bind_param("is", $userId, $articleUrl);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // User already voted
        $existingVote = $result->fetch_assoc()['vote_type'];
        
        if ($existingVote === $voteType) {
            // Remove vote if clicking the same button
            $deleteStmt = $conn->prepare("DELETE FROM votes WHERE user_id = ? AND article_url = ?");
            $deleteStmt->bind_param("is", $userId, $articleUrl);
            $deleteStmt->execute();
            $action = 'removed';
        } else {
            // Update vote if changing vote type
            $updateStmt = $conn->prepare("UPDATE votes SET vote_type = ? WHERE user_id = ? AND article_url = ?");
            $updateStmt->bind_param("sis", $voteType, $userId, $articleUrl);
            $updateStmt->execute();
            $action = 'updated';
        }
    } else {
        // Insert new vote
        $insertStmt = $conn->prepare("INSERT INTO votes (user_id, article_url, vote_type) VALUES (?, ?, ?)");
        $insertStmt->bind_param("iss", $userId, $articleUrl, $voteType);
        $insertStmt->execute();
        $action = 'added';
    }
    
    // Get updated vote counts - count both upvotes and downvotes
    $upvoteStmt = $conn->prepare("SELECT COUNT(*) as upvotes FROM votes WHERE article_url = ? AND vote_type = 'upvote'");
    $upvoteStmt->bind_param("s", $articleUrl);
    $upvoteStmt->execute();
    $upvoteResult = $upvoteStmt->get_result();
    $upvotes = $upvoteResult->fetch_assoc()['upvotes'];
    
    $downvoteStmt = $conn->prepare("SELECT COUNT(*) as downvotes FROM votes WHERE article_url = ? AND vote_type = 'downvote'");
    $downvoteStmt->bind_param("s", $articleUrl);
    $downvoteStmt->execute();
    $downvoteResult = $downvoteStmt->get_result();
    $downvotes = $downvoteResult->fetch_assoc()['downvotes'];
    
    // Calculate total score (upvotes minus downvotes)
    $totalVotes = $upvotes - $downvotes;
    
    // Update the vote count in the articles table
    $updateArticleStmt = $conn->prepare("UPDATE articles SET total_votes = ? WHERE article_url = ?");
    $updateArticleStmt->bind_param("is", $totalVotes, $articleUrl);
    $updateArticleStmt->execute();
    
    // Commit the transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'new_total' => $totalVotes
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
