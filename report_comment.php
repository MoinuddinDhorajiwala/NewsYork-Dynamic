<?php
// Start session and include database connection
session_start();
require 'db.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['comment_id'])) {
    echo json_encode(['success' => false, 'message' => 'Comment ID is required']);
    exit;
}

$commentId = (int)$_POST['comment_id'];
$userId = $_SESSION['user_id'];

try {
    // First, check if the user has already reported this comment
    $checkReportStmt = $conn->prepare("SELECT id FROM comment_reports WHERE comment_id = ? AND user_id = ?");
    $checkReportStmt->bind_param("ii", $commentId, $userId);
    $checkReportStmt->execute();
    $reportResult = $checkReportStmt->get_result();
    
    if ($reportResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reported this comment']);
        exit;
    }
    
    // Check if the comment exists and get current report count
    $checkStmt = $conn->prepare("SELECT reports FROM comments WHERE id = ?");
    $checkStmt->bind_param("i", $commentId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Comment not found']);
        exit;
    }
    
    $commentData = $result->fetch_assoc();
    $currentReports = $commentData['reports'];
    $newReportCount = $currentReports + 1;
    
    // Start transaction
    $conn->begin_transaction();
    
    // Add entry to comment_reports table
    $reportStmt = $conn->prepare("INSERT INTO comment_reports (comment_id, user_id, reported_at) VALUES (?, ?, NOW())");
    $reportStmt->bind_param("ii", $commentId, $userId);
    $reportStmt->execute();
    
    // Update the report count
    $updateStmt = $conn->prepare("UPDATE comments SET reports = ? WHERE id = ?");
    $updateStmt->bind_param("ii", $newReportCount, $commentId);
    $updateStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    $removed = false;
    $message = 'Comment reported successfully';
    
    // If reports reach or exceed 50, delete the comment
    if ($newReportCount >= 50) {
        $deleteStmt = $conn->prepare("DELETE FROM comments WHERE id = ?");
        $deleteStmt->bind_param("i", $commentId);
        $deleteStmt->execute();
        $removed = true;
        $message = 'Comment has been removed due to multiple reports';
    }
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'reports' => $newReportCount,
        'removed' => $removed
    ]);
    
} catch (Exception $e) {
    // If there was an error, rollback the transaction
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>