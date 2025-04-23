<?php
require 'db.php';
header('Content-Type: application/json');

function fetchArticles($conn) {
    try {
        $category = isset($_GET['category']) ? $_GET['category'] : '';
        
        // Base query with votes calculation
        $query = "SELECT a.*, 
                    (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'upvote') as total_votes
                 FROM articles a";
        
        // Add category filter if specified
        if (!empty($category)) {
            $query .= " WHERE a.category = ?";
        }
        
        // Add ordering and limit
        $query .= " ORDER BY total_votes DESC, id DESC LIMIT 100";
        
        // Prepare and execute statement
        $stmt = $conn->prepare($query);
        if (!empty($category)) {
            $stmt->bind_param('s', $category);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result) {
            throw new Exception($conn->error);
        }
        
        $articles = [];
        while ($row = $result->fetch_assoc()) {
            // Ensure all required fields are present
            $articles[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
                'article_url' => $row['article_url'],
                'image_url' => $row['image_url'],
                'total_votes' => (int)$row['total_votes'],
                'category' => $row['category']
            ];
        }
        
        return $articles;
    } catch (Exception $e) {
        error_log("Error in fetchArticles: " . $e->getMessage());
        throw $e;
    }
}

try {
    $articles = fetchArticles($conn);
    
    echo json_encode([
        'success' => true,
        'articles' => $articles
    ]);
} catch (Exception $e) {
    error_log("Fatal Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()  // Show actual error message
    ]);
}
?>