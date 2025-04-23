<?php
// Start session if not already started
session_start();

// Include database connection
require_once 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Disable error display but keep logging
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Ensure we're in a try block to catch any errors
try {

// Check database connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if the request is actually a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if submission_votes table exists
$tableCheckQuery = "SHOW TABLES LIKE 'submission_votes'";
$tableResult = $conn->query($tableCheckQuery);
if ($tableResult->num_rows == 0) {
    // Create the table if it doesn't exist
    $createTableQuery = "CREATE TABLE submission_votes (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        submission_id INT(11) NOT NULL,
        vote_type INT(11) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_submission (user_id, submission_id)
    )";
    
    if (!$conn->query($createTableQuery)) {
        echo json_encode(['success' => false, 'message' => 'Database setup failed']);
        exit;
    }
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Check if submission_id and vote_type are provided
if (!isset($_POST['submission_id']) || !isset($_POST['vote_type'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Get submission ID and vote type
$submission_id = intval($_POST['submission_id']);
$vote_type = $_POST['vote_type'] === 'upvote' ? 1 : -1;

// Check if submission exists
$checkSubmission = $conn->prepare("SELECT id FROM submissions WHERE id = ?");
$checkSubmission->bind_param("i", $submission_id);
$checkSubmission->execute();
$submissionResult = $checkSubmission->get_result();

if ($submissionResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Submission not found']);
    exit;
}

// Check if user has already voted on this submission
$checkVote = $conn->prepare("SELECT vote_type FROM submission_votes WHERE user_id = ? AND submission_id = ?");
$checkVote->bind_param("ii", $user_id, $submission_id);
$checkVote->execute();
$voteResult = $checkVote->get_result();

if ($voteResult->num_rows > 0) {
    // User has already voted, update their vote
    $existingVote = $voteResult->fetch_assoc()['vote_type'];
    if ($existingVote == $vote_type) {
        // Remove vote if clicking the same button again (toggle behavior)
        $removeVote = $conn->prepare("DELETE FROM submission_votes WHERE user_id = ? AND submission_id = ?");
        $removeVote->bind_param("ii", $user_id, $submission_id);
        
        if (!$removeVote->execute()) {
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to remove vote', 
                'error' => $conn->error
            ]);
            exit;
        }
    } else {
        // Change vote direction
        $updateVote = $conn->prepare("UPDATE submission_votes SET vote_type = ? WHERE user_id = ? AND submission_id = ?");
        $updateVote->bind_param("iii", $vote_type, $user_id, $submission_id);
        
        if (!$updateVote->execute()) {
            echo json_encode([
                'success' => false, 
                'message' => 'Failed to update vote', 
                'error' => $conn->error
            ]);
            exit;
        }
    }
} else {
    // User hasn't voted yet, insert new vote
    $insertVote = $conn->prepare("INSERT INTO submission_votes (user_id, submission_id, vote_type) VALUES (?, ?, ?)");
    $insertVote->bind_param("iii", $user_id, $submission_id, $vote_type);
    
    if (!$insertVote->execute()) {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to insert vote', 
            'error' => $conn->error
        ]);
        exit;
    }
}

// Get updated vote count
$getVotes = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN vote_type = 1 THEN 1 WHEN vote_type = -1 THEN -1 ELSE 0 END), 0) as total_votes 
                           FROM submission_votes 
                           WHERE submission_id = ?");
$getVotes->bind_param("i", $submission_id);
$getVotes->execute();
$votesResult = $getVotes->get_result();
$totalVotes = $votesResult->fetch_assoc()['total_votes'];

// Return success response with updated vote count
    echo json_encode([
        'success' => true, 
        'message' => 'Vote recorded successfully', 
        'total_votes' => $totalVotes
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>