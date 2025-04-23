<?php
require_once 'db.php';

// Set maximum execution time to 300 seconds (5 minutes)
set_time_limit(3600);

// Process articles in batches
$batch_size = 100;
$offset = 0;

// NewsAPI key - Replace with your actual API key
$api_key = '26fd62fc2f794a7e8b34dccab29576f8';

while (true) {
    // Fetch articles in batches
    $query = "SELECT DISTINCT article_url, title FROM articles LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $batch_size, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        break; // No more articles to process
    }

    while ($row = $result->fetch_assoc()) {
        try {
            // Use both title and URL for better categorization
            $content = strtolower($row['title'] . ' ' . $row['article_url']);
            
            // Enhanced keyword matching for better categorization
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

            // Update the articles table
            $updateStmt = $conn->prepare("UPDATE articles SET category = ? WHERE article_url = ?");
            $updateStmt->bind_param("ss", $category, $row['article_url']);
            $updateStmt->execute();
            $updateStmt->close();
            
            echo "Updated category for: " . htmlspecialchars($row['article_url']) . " to: " . htmlspecialchars($category) . "<br>";
            
        } catch (Exception $e) {
            echo "Error processing article: " . htmlspecialchars($row['article_url']) . "<br>";
            continue;
        }
        
        // Reduced delay since we're not calling API anymore
        usleep(100000); // 0.1 second delay
    }
    
    $offset += $batch_size;
    echo "Processed batch. Moving to next set...<br>";
    flush();
    ob_flush();
}

echo "Category update process completed!";
$conn->close();
?>