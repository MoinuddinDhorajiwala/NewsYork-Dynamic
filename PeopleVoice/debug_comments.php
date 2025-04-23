<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "newsyork";

// Test submission ID
$submissionId = isset($_GET['submission_id']) ? intval($_GET['submission_id']) : 1;

try {
    // Step 1: Test database connection
    echo "Step 1: Testing database connection...\n";
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful!\n";

    // Step 2: Verify tables exist
    echo "\nStep 2: Verifying tables...\n";
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Available tables: " . implode(", ", $tables) . "\n";

    // Step 3: Check submission_comments table structure
    echo "\nStep 3: Checking submission_comments table structure...\n";
    $tableInfo = $conn->query("DESCRIBE submission_comments")->fetchAll(PDO::FETCH_ASSOC);
    echo "Table structure:\n";
    print_r($tableInfo);

    // Step 4: Test the actual query
    echo "\nStep 4: Testing query for submission_id: $submissionId\n";
    $stmt = $conn->prepare(
        "SELECT c.*, u.username, u.profile_image 
         FROM submission_comments c 
         LEFT JOIN users u ON c.user_id = u.id 
         WHERE c.submission_id = ?"
    );
    $stmt->execute([$submissionId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nQuery results:\n";
    print_r($comments);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error occurred in file: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

$conn = null;