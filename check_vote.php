<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if (!isset($_GET['article_url'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing article URL'
    ]);
    exit;
}

$articleUrl = urldecode($_GET['article_url']);
$userId = $_SESSION['user_id'] ?? null;

try {
    // Get user's vote if logged in
    $userVote = null;
    if ($userId) {
        $voteStmt = $conn->prepare("SELECT vote_type FROM votes WHERE user_id = ? AND article_url = ?");
        $voteStmt->bind_param("is", $userId, $articleUrl);
        $voteStmt->execute();
        $voteResult = $voteStmt->get_result();
        
        if ($voteResult->num_rows > 0) {
            $userVote = $voteResult->fetch_assoc()['vote_type'];
        }
    }
    
    // Get total votes
    $totalStmt = $conn->prepare("SELECT 
        (SELECT COUNT(*) FROM votes WHERE article_url = ? AND vote_type = 'upvote') - 
        (SELECT COUNT(*) FROM votes WHERE article_url = ? AND vote_type = 'downvote') as total_votes");
    $totalStmt->bind_param("ss", $articleUrl, $articleUrl);
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalVotes = $totalResult->fetch_assoc()['total_votes'];
    
    echo json_encode([
        'success' => true,
        'user_vote' => $userVote,
        'total_votes' => $totalVotes
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>