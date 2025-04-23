<?php
require 'db.php';
header('Content-Type: application/json');

// Set a longer execution time for this script
set_time_limit(300);

// Get the search keyword
$keyword = $_GET['keyword'] ?? '';

if (empty($keyword)) {
    echo json_encode([
        'success' => false,
        'message' => 'No search keyword provided'
    ]);
    exit;
}

// Fetch news for the keyword
$apiKey = '26fd62fc2f794a7e8b34dccab29576f8';
$apiUrl = "https://newsapi.org/v2/everything?q=" . urlencode($keyword) . "&apiKey=" . $apiKey . "&language=en&pageSize=20";

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
        'message' => 'No articles found for this keyword'
    ]);
    exit;
}

// First, check the structure of the articles table
$tableStructure = $conn->query("DESCRIBE articles");
$hasCategory = false;

while ($column = $tableStructure->fetch_assoc()) {
    if ($column['Field'] === 'category') {
        $hasCategory = true;
        break;
    }
}

// Insert articles into database based on table structure
if ($hasCategory) {
    $stmt = $conn->prepare("INSERT IGNORE INTO articles (title, description, article_url, image_url, category) VALUES (?, ?, ?, ?, ?)");
} else {
    $stmt = $conn->prepare("INSERT IGNORE INTO articles (title, description, article_url, image_url) VALUES (?, ?, ?, ?)");
}

foreach ($apiData['articles'] as $article) {
    if (empty($article['title']) || empty($article['url'])) {
        continue;
    }
    
    if ($hasCategory) {
        $category = 'search';
        $stmt->bind_param("sssss", 
            $article['title'],
            $article['description'],
            $article['url'],
            $article['urlToImage'],
            $category
        );
    } else {
        $stmt->bind_param("ssss", 
            $article['title'],
            $article['description'],
            $article['url'],
            $article['urlToImage']
        );
    }
    
    if ($stmt->execute()) {
        $articlesAdded++;
    }
}

// Return results
echo json_encode([
    'success' => true,
    'articles_added' => $articlesAdded,
    'keyword' => $keyword,
    'total_results' => count($apiData['articles'])
]);
?>