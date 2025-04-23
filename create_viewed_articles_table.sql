CREATE TABLE IF NOT EXISTS viewed_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    article_url VARCHAR(255) NOT NULL,
    interaction_type ENUM('bookmark', 'comment', 'vote', 'view') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_view (user_id, article_url, interaction_type)
);