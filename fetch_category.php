<?php
require 'db.php';
header('Content-Type: application/json');

// Set a longer execution time for this script
set_time_limit(300);

// Get the category
$category = $_GET['category'] ?? '';

if (empty($category)) {
    echo json_encode([
        'success' => false,
        'message' => 'No category provided'
    ]);
    exit;
}

// Map category to API parameter
$categoryMap = [
    'general' => 'general',
    'business' => 'business',
    'entertainment' => 'entertainment',
    'health' => 'health',
    'science' => 'science',
    'sports' => 'sports',
    'technology' => 'technology',
    'politics' => 'politics'
];

$apiCategory = $categoryMap[$category] ?? 'general';

// Fetch news for the category
$apiKey = '26fd62fc2f794a7e8b34dccab29576f8';
$apiUrl = "https://newsapi.org/v2/top-headlines?category=" . $apiCategory . "&apiKey=" . $apiKey . "&language=en&pageSize=20";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; NewsAggregator/1.0)');

$apiResponse = curl_exec($ch);
$articlesAdded = 0;

if (curl_errno($ch)) {
    echo json_encode([
        'success' => false,
        'message' => 'API request failed: ' . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}

$apiData = json_decode($apiResponse, true);
curl_close($ch);

if (!$apiData || $apiData['status'] !== 'ok') {
    echo json_encode([
        'success' => false,
        'message' => 'API returned an error: ' . ($apiData['message'] ?? 'Unknown error')
    ]);
    exit;
}

if (empty($apiData['articles'])) {
    echo json_encode([
        'success' => true,
        'articles_added' => 0,
        'message' => 'No articles found for this category'
    ]);
    exit;
}

// Insert articles into database
$stmt = $conn->prepare("INSERT IGNORE INTO articles (title, description, article_url, image_url) VALUES (?, ?, ?, ?)");

foreach ($apiData['articles'] as $article) {
    if (empty($article['title']) || empty($article['url'])) {
        continue;
    }
    
    $stmt->bind_param("ssss", 
        $article['title'],
        $article['description'],
        $article['url'],
        $article['urlToImage']
    );
    
    if ($stmt->execute()) {
        $articlesAdded++;
    }
}

// Return results
echo json_encode([
    'success' => true,
    'articles_added' => $articlesAdded,
    'category' => $category,
    'total_results' => count($apiData['articles'])
]);
?>