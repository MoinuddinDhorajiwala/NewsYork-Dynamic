<?php
require 'db.php';

// Set a longer execution time for this script
set_time_limit(300); // Increased from 3 to 300 seconds

// Description length parameters
$min_description_length = 1000;
$max_description_length = 5000;
$default_description = "No description available for this article. Click to read more.";

// Function to fetch article content from URL
function fetchArticleContent($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 seconds timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    
    $html = curl_exec($ch);
    
    if(curl_errno($ch)) {
        error_log("cURL Error: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if($status != 200) {
        error_log("HTTP Error: Status $status for URL $url");
        return null;
    }
    
    return $html;
}

// Function to extract meaningful text from HTML content
function extractArticleText($html, $title) {
    if(empty($html)) {
        return null;
    }
    
    // Create DOM document
    $dom = new DOMDocument();
    
    // Suppress warnings from malformed HTML
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    
    // Initialize text extraction
    $paragraphs = [];
    
    // First try to find article content in common article containers
    $contentSelectors = [
        '//article', 
        '//div[contains(@class, "article")]',
        '//div[contains(@class, "content")]',
        '//div[contains(@class, "post")]',
        '//main',
        '//div[contains(@class, "story")]',
        '//div[contains(@class, "body")]'
    ];
    
    $xpath = new DOMXPath($dom);
    $contentNode = null;
    
    // Try to find the main content container
    foreach($contentSelectors as $selector) {
        $nodes = $xpath->query($selector);
        if($nodes->length > 0) {
            $contentNode = $nodes->item(0);
            break;
        }
    }
    
    // If we found a content container, extract paragraphs from it
    if($contentNode) {
        $pNodes = $xpath->query('.//p', $contentNode);
        foreach($pNodes as $p) {
            $text = trim($p->textContent);
            if(strlen($text) > 30 && !preg_match('/^(copyright|©|all rights reserved)/i', $text)) {
                $paragraphs[] = $text;
            }
        }
    }
    
    // If we couldn't find paragraphs in content containers, try all paragraphs
    if(empty($paragraphs)) {
        $allParagraphs = $dom->getElementsByTagName('p');
        foreach($allParagraphs as $p) {
            $text = trim($p->textContent);
            if(strlen($text) > 30 && !preg_match('/^(copyright|©|all rights reserved)/i', $text)) {
                $paragraphs[] = $text;
            }
        }
    }
    
    // If we still don't have paragraphs, try to get meta description
    if(empty($paragraphs)) {
        $metaNodes = $xpath->query('//meta[@name="description" or @property="og:description"]/@content');
        foreach($metaNodes as $meta) {
            $content = $meta->nodeValue;
            if(!empty($content) && strlen($content) > 30) {
                $paragraphs[] = $content;
            }
        }
    }
    
    // If we have paragraphs, create a description
    if(!empty($paragraphs)) {
        // Remove paragraphs that are too similar to the title (likely headings)
        $titleWords = preg_split('/\s+/', strtolower($title));
        $filteredParagraphs = [];
        
        foreach($paragraphs as $p) {
            $similarity = 0;
            $pWords = preg_split('/\s+/', strtolower($p));
            
            foreach($titleWords as $word) {
                if(in_array($word, $pWords) && strlen($word) > 3) {
                    $similarity++;
                }
            }
            
            // If less than 70% similar to title, keep it
            if($similarity < (count($titleWords) * 0.7)) {
                $filteredParagraphs[] = $p;
            }
        }
        
        // If we have filtered paragraphs, use them
        if(!empty($filteredParagraphs)) {
            // Sort paragraphs by length (descending)
            usort($filteredParagraphs, function($a, $b) {
                return strlen($b) - strlen($a);
            });
            
            // Take at least 3 paragraphs or until we reach max length
            $description = '';
            $count = 0;
            $min_paragraphs = 3; // Ensure we have at least 3 paragraphs when available
            
            // First pass: try to get at least min_paragraphs
            foreach($filteredParagraphs as $p) {
                if(strlen($description) + strlen($p) <= $GLOBALS['max_description_length'] && $count < $min_paragraphs) {
                    $description .= $p . ' ';
                    $count++;
                } else if($count >= $min_paragraphs) {
                    break;
                }
            }
            
            return trim($description);
        }
    }
    
    return null;
}

// Check if a specific keyword was requested
$keyword = $_GET['keyword'] ?? '';

if (!empty($keyword)) {
    // Fetch news for a specific keyword
    $apiKey = '26fd62fc2f794a7e8b34dccab29576f8';
    $apiUrl = "https://newsapi.org/v2/everything?q=" . urlencode($keyword) . "&apiKey=" . $apiKey . "&language=en&pageSize=20";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $apiResponse = curl_exec($ch);
    
    $articlesAdded = 0;
    
    if (!curl_errno($ch)) {
        $apiData = json_decode($apiResponse, true);
        
        if ($apiData && $apiData['status'] === 'ok' && !empty($apiData['articles'])) {
            // Insert new articles into database
            $stmt = $conn->prepare("INSERT IGNORE INTO articles (title, description, article_url, image_url, category) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($apiData['articles'] as $article) {
                if (empty($article['title']) || empty($article['url'])) {
                    continue;
                }
                
                $category = 'search';
                $stmt->bind_param("sssss", 
                    $article['title'],
                    $article['description'],
                    $article['url'],
                    $article['urlToImage'],
                    $category
                );
                
                if ($stmt->execute()) {
                    $articlesAdded++;
                }
            }
        }
    }
    
    curl_close($ch);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'articles_added' => $articlesAdded,
        'keyword' => $keyword
    ]);
    exit;
}

// Continue with your existing fetch_news.php code for general fetching
header('Content-Type: application/json');

// Enable detailed error logging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
error_log("Fetching news from external API started");

// API Key for NewsAPI.org
$api_key = '26fd62fc2f794a7e8b34dccab29576f8';

// Categories to fetch
$categories = ['general', 'politics', 'business', 'sports', 'entertainment', 'health', 'technology'];

// Add country parameter to make request more specific
$country = 'us'; // Change this as needed (us, gb, ca, etc.)

// Function to fetch news from external API with detailed error handling
function fetchNewsFromAPI($category, $api_key, $country = 'us') {
    $url = "https://newsapi.org/v2/top-headlines?category=" . urlencode($category) . 
           "&country=" . urlencode($country) . 
           "&apiKey=" . $api_key;
    
    error_log("Fetching from URL: " . $url);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Enable SSL verification for security
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set a timeout
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; NewsAggregator/1.0)'); // Set user agent
    
    $response = curl_exec($ch);
    
    if(curl_errno($ch)) {
        error_log("cURL Error (" . curl_errno($ch) . "): " . curl_error($ch));
        curl_close($ch);
        return ['error' => true, 'message' => curl_error($ch)];
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    
    // Log the response for debugging
    error_log("API Response HTTP Code: " . $http_code);
    if ($http_code != 200) {
        error_log("API Error Response: " . $response);
        return ['error' => true, 'message' => "HTTP Error: $http_code", 'response' => $decoded];
    }
    
    if (!$decoded) {
        error_log("JSON Decode Error: " . json_last_error_msg());
        return ['error' => true, 'message' => "JSON parsing error: " . json_last_error_msg()];
    }
    
    if (isset($decoded['status']) && $decoded['status'] !== 'ok') {
        error_log("API Error: " . ($decoded['message'] ?? 'Unknown error'));
        return ['error' => true, 'message' => $decoded['message'] ?? 'Unknown API error', 'response' => $decoded];
    }
    
    return $decoded;
}

// Function to store article in database with better error handling and improved description extraction
// Updated to match your table structure and extract better descriptions
function storeArticle($conn, $article) {
    global $min_description_length, $max_description_length, $default_description;
    
    // Extract article data with more robust null/empty checking
    $article_url = isset($article['url']) && !empty($article['url']) ? $article['url'] : null;
    $title = isset($article['title']) && !empty($article['title']) ? $article['title'] : null;
    $image_url = isset($article['urlToImage']) && !empty($article['urlToImage']) ? $article['urlToImage'] : null;
    
    // Get initial description from API response
    $initial_description = isset($article['description']) ? $article['description'] : null;
    
    // Determine category from content
    $content = strtolower($title . ' ' . $initial_description . ' ' . $article_url);
    
    if (preg_match('/(politics|political|election|government|policy|congress|senate|parliament|democracy|diplomatic|minister|president)/i', $content)) {
        $category = 'politics';
    } elseif (preg_match('/(business|economy|market|finance|trade|stock|investment|startup|company|corporate|entrepreneur|industry)/i', $content)) {
        $category = 'business';
    } elseif (preg_match('/(sports?|football|soccer|basketball|tennis|olympics|athletic|nba|nfl|mlb|championship|tournament|player|game|match)/i', $content)) {
        $category = 'sports';
    } elseif (preg_match('/(entertainment|movie|film|music|celebrity|culture|art|fashion|lifestyle|gaming|game|tv|show|series|actor|actress|hollywood)/i', $content)) {
        $category = 'entertainment';
    } elseif (preg_match('/(health|medical|wellness|fitness|disease|covid|pandemic|medicine|doctor|hospital|diet|vaccine|treatment)/i', $content)) {
        $category = 'health';
    } elseif (preg_match('/(tech|technology|digital|software|ai|artificial intelligence|cyber|computing|internet|robot|device|gadget|smartphone|app)/i', $content)) {
        $category = 'technology';
    } else {
        $category = 'general';
    }
    
    if (!$article_url || !$title) {
        error_log("Skipping article with missing URL or title");
        return false;
    }
    
    // Check if the initial description is too short or missing
    $needs_better_description = empty($initial_description) || 
                               strlen($initial_description) < $min_description_length || 
                               preg_match('/\.\.\.$/|…$/|\[…\]$/|\[\.\.\.\]$/|\[more\]$/|\[continue\]$/i', $initial_description) || 
                               substr_count($initial_description, '.') < 2;
    
    // If we need a better description, try to fetch and extract it from the article URL
    $description = $initial_description;
    if ($needs_better_description && filter_var($article_url, FILTER_VALIDATE_URL)) {
        try {
            error_log("Fetching better description for: " . $title);
            $html = fetchArticleContent($article_url);
            
            if ($html) {
                $extracted_text = extractArticleText($html, $title);
                
                if ($extracted_text && strlen($extracted_text) > $min_description_length) {
                    // Truncate if too long
                    if (strlen($extracted_text) > $max_description_length) {
                        $description = substr($extracted_text, 0, $max_description_length);
                        // Ensure we don't cut in the middle of a word
                        $description = preg_replace('/\s+\S*$/', '', $description) . '...'; 
                    } else {
                        $description = $extracted_text;
                    }
                    error_log("Successfully extracted better description for: " . $title);
                }
            }
        } catch (Exception $e) {
            error_log("Error extracting description: " . $e->getMessage());
        }
    }
    
    // If we still don't have a good description, use the default
    if (empty($description)) {
        $description = $default_description;
        error_log("Using default description for: " . $title);
    }
    
    try {
        // Check if article exists
        $stmt = $conn->prepare("SELECT id FROM articles WHERE article_url = ?");
        if (!$stmt) {
            error_log("DB Prepare Error (SELECT): " . $conn->error);
            return false;
        }
        
        $stmt->bind_param("s", $article_url);
        if (!$stmt->execute()) {
            error_log("DB Execute Error (SELECT): " . $stmt->error);
            $stmt->close();
            return false;
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            
            // Updated INSERT query to include category
            $stmt = $conn->prepare("
                INSERT INTO articles 
                (article_url, upvotes, downvotes, total_votes, title, image_url, description, category) 
                VALUES (?, 0, 0, 0, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                error_log("DB Prepare Error (INSERT): " . $conn->error);
                return false;
            }
            
            $stmt->bind_param("sssss", $article_url, $title, $image_url, $description, $category);
            
            if ($stmt->execute()) {
                error_log("Article inserted: " . $title . " (Category: " . $category . ")");
                $stmt->close();
                return true;
            } else {
                error_log("DB Insert Error: " . $stmt->error);
                $stmt->close();
                return false;
            }
        } else {
            // Article already exists
            error_log("Article already exists: " . $article_url);
            $stmt->close();
            return true;
        }
    } catch (Exception $e) {
        error_log("Exception in storeArticle: " . $e->getMessage());
        return false;
    }
}

// Test the database connection
function testDBConnection($conn) {
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return false;
    }
    
    try {
        $result = $conn->query("SHOW TABLES LIKE 'articles'");
        if ($result === false) {
            error_log("Error checking tables: " . $conn->error);
            return false;
        }
        
        if ($result->num_rows == 0) {
            error_log("Table 'articles' does not exist!");
            return false;
        }
        
        // Check if the table has the expected structure
        $result = $conn->query("DESCRIBE articles");
        if ($result === false) {
            error_log("Error describing table: " . $conn->error);
            return false;
        }
        
        $expectedColumns = ['id', 'article_url', 'upvotes', 'downvotes', 'total_votes', 'title', 'image_url', 'description', 'created_at'];
        $foundColumns = [];
        
        while ($row = $result->fetch_assoc()) {
            $foundColumns[] = $row['Field'];
        }
        
        $missingColumns = array_diff($expectedColumns, $foundColumns);
        if (!empty($missingColumns)) {
            error_log("Table missing columns: " . implode(', ', $missingColumns));
        }
        
        error_log("Database connection successful and articles table exists");
        return true;
    } catch (Exception $e) {
        error_log("Exception testing DB: " . $e->getMessage());
        return false;
    }
}

// Main process
try {
    $fetchedCount = 0;
    $errorCount = 0;
    $detailedErrors = [];
    
    // Test DB connection first
    if (!testDBConnection($conn)) {
        echo json_encode([
            'success' => false,
            'message' => "Database connection or table issue detected",
            'fetched_count' => 0,
            'error_count' => 1
        ]);
        exit;
    }
    if (isset($_GET['keyword']) && !empty($_GET['keyword'])) {
        $keyword = $_GET['keyword'];
    }
    // First, test the API with a single request
    $testResponse = fetchNewsFromAPI('general', $api_key, $country);
    if (isset($testResponse['error']) && $testResponse['error']) {
        echo json_encode([
            'success' => false,
            'message' => "API test failed: " . $testResponse['message'],
            'details' => $testResponse,
            'fetched_count' => 0,
            'error_count' => 1
        ]);
        exit;
    }
    
    foreach ($categories as $category) {
        $newsData = fetchNewsFromAPI($category, $api_key, $country);
        
        if (isset($newsData['error']) && $newsData['error']) {
            error_log("Failed to fetch news for category: " . $category . " - " . $newsData['message']);
            $errorCount++;
            $detailedErrors[] = [
                'category' => $category,
                'error' => $newsData['message'],
                'details' => $newsData
            ];
            continue;
        }
        
        if (!isset($newsData['articles']) || !is_array($newsData['articles'])) {
            error_log("No articles found for category: " . $category);
            $errorCount++;
            $detailedErrors[] = [
                'category' => $category,
                'error' => 'No articles in response',
                'response' => $newsData
            ];
            continue;
        }
        
        $categoryCount = 0;
        foreach ($newsData['articles'] as $article) {
            if (storeArticle($conn, $article)) {
                $fetchedCount++;
                $categoryCount++;
            } else {
                $errorCount++;
            }
        }
        
        error_log("Processed $categoryCount articles for category: $category");
    }
    
    echo json_encode([
        'success' => ($fetchedCount > 0),
        'message' => "Fetched and processed $fetchedCount articles with $errorCount errors",
        'fetched_count' => $fetchedCount,
        'error_count' => $errorCount,
        'detailed_errors' => $detailedErrors
    ]);
    
} catch (Exception $e) {
    error_log("Fatal Error in fetch_news.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => "Fatal error: " . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>