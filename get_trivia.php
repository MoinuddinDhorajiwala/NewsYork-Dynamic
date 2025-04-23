<?php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get category from request
$category = isset($_GET['category']) ? $_GET['category'] : 'general';
$user_id = $_SESSION['user_id'];

// Check if it's a new day and reset counts if needed
$stmt = $conn->prepare("SELECT * FROM trivia WHERE user_id = ? AND last_reset_date < CURRENT_DATE");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Reset all category counts for the new day
    $reset_stmt = $conn->prepare("UPDATE trivia SET 
        politics_count = 0,
        business_count = 0,
        sports_count = 0,
        entertainment_count = 0,
        health_count = 0,
        technology_count = 0,
        last_reset_date = CURRENT_DATE
        WHERE user_id = ?");
    $reset_stmt->bind_param("i", $user_id);
    $reset_stmt->execute();
}

// Check if user has reached daily limit for this category
$count_column = $category . '_count';
$check_stmt = $conn->prepare("SELECT $count_column FROM trivia WHERE user_id = ?");
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$count_result = $check_stmt->get_result();
$count_data = $count_result->fetch_assoc();

if ($count_data[$count_column] >= 10) {
    echo json_encode(['success' => false, 'message' => "You've reached today's limit for $category questions. Try a different category or come back tomorrow!"]);
    exit;
}

// Map our categories to Open Trivia DB categories
$category_map = [
    'politics' => 24, // Politics
    'business' => 22, // Geography (closest match)
    'sports' => 21,   // Sports
    'entertainment' => 11, // Entertainment: Film
    'health' => 27,   // Animals (closest match)
    'technology' => 18 // Science: Computers
];

$category_id = isset($category_map[$category]) ? $category_map[$category] : '';
$category_param = $category_id ? "&category={$category_id}" : '';

// Fetch questions from Open Trivia DB API
$url = "https://opentdb.com/api.php?amount=10&type=multiple{$category_param}";
$response = file_get_contents($url);

if ($response === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch questions']);
    exit;
}

$data = json_decode($response, true);

if ($data['response_code'] !== 0) {
    echo json_encode(['success' => false, 'message' => 'API error']);
    exit;
}

// Format questions for our frontend
$questions = $data['results'];

// Increment the category count
$update_stmt = $conn->prepare("UPDATE trivia SET $count_column = $count_column + 1 WHERE user_id = ?");
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();

// Return questions as JSON
echo json_encode(['success' => true, 'questions' => $questions]);
?>