<?php
require_once 'db.php';

function markArticleAsViewed($userId, $articleUrl, $interactionType) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT IGNORE INTO viewed_articles (user_id, article_url, interaction_type) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $articleUrl, $interactionType);
    return $stmt->execute();
}

function hasUserInteractedWithArticle($userId, $articleUrl) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT 1 FROM viewed_articles WHERE user_id = ? AND article_url = ? LIMIT 1");
    $stmt->bind_param("is", $userId, $articleUrl);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

function getUnseenArticles($userId, $category = '', $searchTerm = '') {
    global $conn;
    
    $query = "SELECT a.*, 
        (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'upvote') - 
        (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'downvote') as total_votes 
        FROM articles a 
        WHERE a.article_url NOT IN (
            SELECT article_url FROM viewed_articles WHERE user_id = ?
        )";
    
    $params = array($userId);
    $types = "i";
    
    if (!empty($category)) {
        $query .= " AND a.category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    if (!empty($searchTerm)) {
        $query .= " AND (a.title LIKE ? OR a.description LIKE ?)";
        $searchParam = "%{$searchTerm}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ss";
    }
    
    $query .= " ORDER BY total_votes DESC, created_at DESC LIMIT 100";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    return $stmt->get_result();
}