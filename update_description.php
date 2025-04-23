<?php
require_once 'db.php';

// Set maximum execution time to 3600 seconds (1 hour)
set_time_limit(3600);

// Process articles in batches
$batch_size = 100;
$offset = 0;
$updated_count = 0;
$skipped_count = 0;
$error_count = 0;

// Default description for articles with missing descriptions (only used as fallback)
$default_description = "No description available for this article. Click to read more.";

// Minimum acceptable description length (characters)
$min_description_length = 1000;

// Maximum description length to generate (characters)
$max_description_length = 5000;

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

echo "<h2>Description Update Process</h2>";
echo "<p>Updating missing or truncated descriptions for articles...</p>";

while (true) {
    // Fetch articles in batches
    $query = "SELECT id, article_url, title, description FROM articles LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $batch_size, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        break; // No more articles to process
    }

    echo "<p>Processing batch starting at offset: $offset</p>";
    
    while ($row = $result->fetch_assoc()) {
        try {
            $article_id = $row['id'];
            $article_url = $row['article_url'];
            $current_description = $row['description'];
            
            // Check if description is missing, too short, or contains truncation indicators
            $needs_update = false;
            $reason = "";
            
            if (empty($current_description)) {
                $needs_update = true;
                $reason = "empty description";
            } elseif (strlen($current_description) < $min_description_length) {
                $needs_update = true;
                $reason = "description too short (" . strlen($current_description) . " chars)";
            } elseif (preg_match('/\.\.\.$|…$|\[…\]$|\[\.\.\.\]$|\[more\]$|\[continue\]$/i', $current_description)) {
                $needs_update = true;
                $reason = "truncated description";
            } elseif (substr_count($current_description, '.') < 2) { // Check if fewer than 3 sentences (rough estimate of paragraphs)
                $needs_update = true;
                $reason = "fewer than 3 paragraphs";
            }
            
            if ($needs_update) {
                // Try to fetch and extract content from the article URL
                $success = false;
                
                try {
                    // Only attempt to fetch if we have a valid URL
                    if (filter_var($article_url, FILTER_VALIDATE_URL)) {
                        echo "<p>Fetching content for article ID: " . htmlspecialchars($article_id) . 
                             " (" . htmlspecialchars(substr($row['title'], 0, 50)) . "...)";
                        
                        // Fetch the HTML content
                        $html = fetchArticleContent($article_url);
                        
                        if ($html) {
                            // Extract meaningful text
                            $extracted_text = extractArticleText($html, $row['title']);
                            
                            if ($extracted_text && strlen($extracted_text) > $min_description_length) {
                                // Truncate if too long
                                if (strlen($extracted_text) > $max_description_length) {
                                    $new_description = substr($extracted_text, 0, $max_description_length);
                                    // Ensure we don't cut in the middle of a word
                                    $new_description = preg_replace('/\s+\S*$/', '', $new_description) . '...'; 
                                } else {
                                    $new_description = $extracted_text;
                                }
                                
                                $success = true;
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Error extracting content: " . $e->getMessage());
                }
                
                // If we couldn't extract a good description, use the default
                if (!$success) {
                    $new_description = $default_description;
                    echo "<p style='color: orange;'>Could not extract content, using default description for article ID: " . 
                         htmlspecialchars($article_id) . "</p>";
                    $error_count++;
                }
                
                // Update the articles table
                $updateStmt = $conn->prepare("UPDATE articles SET description = ? WHERE id = ?");
                $updateStmt->bind_param("si", $new_description, $article_id);
                $updateStmt->execute();
                $updateStmt->close();
                
                echo "<p style='color: green;'>Updated description for article ID: " . htmlspecialchars($article_id) . 
                     " (" . htmlspecialchars(substr($row['title'], 0, 50)) . "...) - Reason: " . $reason . "</p>";
                
                $updated_count++;
            } else {
                $skipped_count++;
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error processing article ID: " . htmlspecialchars($article_id ?? 'unknown') . 
                 " - Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            continue;
        }
        
        // Small delay to prevent server overload
        usleep(50000); // 0.05 second delay
    }
    
    $offset += $batch_size;
    echo "<p>Completed batch. Moving to next set...</p>";
    flush();
    ob_flush();
}

echo "<h3>Description update process completed!</h3>";
echo "<p>Total articles updated: $updated_count</p>";
echo "<p>Total articles skipped (already had good descriptions): $skipped_count</p>";
echo "<p>Total articles with extraction errors (used default description): $error_count</p>";

// Add some styling to make the output more readable
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
    h2, h3 { color: #2c3e50; }
    p { margin: 5px 0; }
    .stats { background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; }
</style>";

echo "<div class='stats'>
    <h3>Process Statistics</h3>
    <p>Total processed: " . ($updated_count + $skipped_count) . "</p>
    <p>Success rate: " . ($updated_count > 0 ? round((($updated_count - $error_count) / $updated_count) * 100, 2) : 0) . "%</p>
    <p>Processing time: " . round((microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"]) / 60, 2) . " minutes</p>
</div>";

$conn->close();

?>