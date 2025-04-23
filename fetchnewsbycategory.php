<?php
session_start();
require 'db.php';

// Add debugging log
error_log("fetchnewsbycategory.php called with category: " . ($_GET['category'] ?? 'none'));

header('Content-Type: application/json');

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

// Get the category from the request
$category = $_GET['category'] ?? '';

if (empty($category)) {
    echo json_encode(['success' => false, 'message' => 'No category specified']);
    exit;
}

try {
    // First, try to fetch from external API if we have few articles in this category
    $countQuery = "SELECT COUNT(*) as count FROM articles WHERE category = ?";
    $countStmt = $conn->prepare($countQuery);
    $countStmt->bind_param("s", $category);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $countRow = $countResult->fetch_assoc();
    
    // If we have fewer than 5 articles for this category, fetch more from the API
    if ($countRow['count'] < 5) {
        // API key for NewsAPI
        $apiKey = '26fd62fc2f794a7e8b34dccab29576f8';
        $apiUrl = "https://newsapi.org/v2/top-headlines?category=" . urlencode($category) . "&apiKey=" . $apiKey . "&language=en";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $apiResponse = curl_exec($ch);
        
        if (!curl_errno($ch)) {
            $apiData = json_decode($apiResponse, true);
            
            if ($apiData && $apiData['status'] === 'ok' && !empty($apiData['articles'])) {
                // Insert new articles into database
                $insertStmt = $conn->prepare("INSERT IGNORE INTO articles (title, description, article_url, image_url, category) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($apiData['articles'] as $article) {
                    // Skip articles without title or URL
                    if (empty($article['title']) || empty($article['url'])) {
                        continue;
                    }
                    
                    $title = $article['title'];
                    $article_url = $article['url'];
                    $image_url = $article['urlToImage'] ?? null;
                    $initial_description = $article['description'] ?? null;
                    
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
                    
                    $insertStmt->bind_param("sssss", 
                        $title,
                        $description,
                        $article_url,
                        $image_url,
                        $category
                    );
                    $insertStmt->execute();
                }
            }
        }
        curl_close($ch);
    }
    
    // Get hide_viewed parameter
    $hide_viewed = isset($_GET['hide_viewed']) ? filter_var($_GET['hide_viewed'], FILTER_VALIDATE_BOOLEAN) : false;

    // Now query the database for articles in this category
    $sql = "SELECT a.*, 
            (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'upvote') - 
            (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'downvote') as total_votes 
            FROM articles a 
            WHERE a.category = ? ";
    
    // Add condition to exclude viewed articles if hide_viewed is true and user is logged in
    if ($hide_viewed && isset($_SESSION['user_id'])) {
        $sql .= " AND NOT EXISTS (SELECT 1 FROM viewed_articles va WHERE va.article_url = a.article_url AND va.user_id = ?)";
    }
    
    $sql .= " ORDER BY total_votes DESC, created_at DESC";

    
    $stmt = $conn->prepare($sql);
    if ($hide_viewed && isset($_SESSION['user_id'])) {
        $stmt->bind_param("si", $category, $_SESSION['user_id']);
    } else {
        $stmt->bind_param("s", $category);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $articles = [];
    while ($article = $result->fetch_assoc()) {
        // Check if article is bookmarked by the current user
        $article['is_bookmarked'] = false;
        if (isset($_SESSION['user_id'])) {
            $bookmarkCheck = $conn->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND article_url = ? LIMIT 1");
            $bookmarkCheck->bind_param("is", $_SESSION['user_id'], $article['article_url']);
            $bookmarkCheck->execute();
            $article['is_bookmarked'] = $bookmarkCheck->get_result()->num_rows > 0;
            $bookmarkCheck->close();
        }
        
        $articles[] = $article;
    }
    
    echo json_encode([
        'success' => true, 
        'articles' => $articles,
        'category' => $category,
        'count' => count($articles)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>