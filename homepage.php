<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['guest'])) {
        header("Location: index.php");
        exit;
    }
} else {
    // User is logged in, check if we need to fetch news
    $lastFetch = $_SESSION['last_news_fetch'] ?? 0;
    $currentTime = time();
    
    // Fetch news if it hasn't been fetched in the last hour (3600 seconds)
    if ($currentTime - $lastFetch > 3600) {
        $_SESSION['last_news_fetch'] = $currentTime;
        // Add script to fetch news in background
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                fetchNewsInBackground();
            });
        </script>';
    }
}
?>
<?php
$category = $_GET['category'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Base query with votes calculation
$sql = "SELECT a.*, 
        (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'upvote') - 
        (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'downvote') as total_votes 
        FROM articles a";

// Build WHERE conditions
$whereConditions = [];
$params = [];
$types = "";

if (!empty($searchTerm)) {
    $whereConditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $types .= "ss";
}

if (!empty($category)) {
    $whereConditions[] = "a.category = ?";
    $params[] = $category;
    $types .= "s";
}

// Add WHERE clause if conditions exist
if (!empty($whereConditions)) {
    $sql .= " WHERE " . implode(" AND ", $whereConditions);
}

// Add ORDER BY clause
$sql .= " ORDER BY total_votes DESC, created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// If a category is specified, output JavaScript to trigger the category fetch
if (!empty($category)) {
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            fetchNewsByCategory("' . htmlspecialchars($category) . '");
        });
    </script>';
}

// The query has already been executed above, no need to execute it again here
// Check if there's a search term
$searchTerm = $_GET['search'] ?? '';
if (!empty($searchTerm)) {
    // Output JavaScript to trigger the search in the background
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            fetchNewsByKeyword("' . htmlspecialchars($searchTerm) . '");
        });
    </script>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NewsYork - A News Agreggator</title>
    <link rel="icon" href="images/logo.jpg">
    <link rel="stylesheet" href="homepagestyle.css">
    <script src="news.js"></script>
    <script>
            
        // Function to fetch news in the background
        function fetchNewsInBackground() {
            console.log("Fetching news in background...");
            fetch('fetch_news.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                console.log('News fetching completed');
                // No need to refresh the page as the news will be available on next load
            })
            .catch(error => console.error('Error fetching news:', error));
        }
        
        function fetchArticles() {
            fetch('get_articles.php')
                .then(response => response.json())
                .then(data => {
                    const newsContainer = document.getElementById('news-container');
                    newsContainer.innerHTML = ''; // Clear existing articles
                    data.articles.forEach(article => {
                        const articleElement = document.createElement('div');
                        articleElement.innerHTML = `
                            <h2>${article.title}</h2>
                            <p>${article.content}</p>
                            <p>Votes: ${article.votes}</p>
                        `;
                        newsContainer.appendChild(articleElement);
                    });
                })
                .catch(error => console.error('Error fetching articles:', error));
        }
         
        // Function to fetch news by keyword
        function fetchNewsByKeyword(keyword) {
        console.log("Fetching news for keyword: " + keyword);
        fetch('search_news.php?keyword=' + encodeURIComponent(keyword), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Keyword news fetching completed:', data);
            if (data.success && data.articles_added > 0) {
                // Refresh the page to show the new articles
                window.location.reload();
            }
        })
        .catch(error => console.error('Error fetching keyword news:', error));
    }
    function displayArticles(articles) {
        const newsContainer = document.querySelector('#news-container');
        if (!newsContainer) return;

        newsContainer.innerHTML = '';
        
        if (!articles || articles.length === 0) {
            newsContainer.innerHTML = '<div class="no-results">No articles found for this category.</div>';
            return;

        }
        
        // Sort articles by votes and creation date
        articles.sort((a, b) => {
            if (b.total_votes !== a.total_votes) {
                return b.total_votes - a.total_votes;
            }
            return new Date(b.created_at || 0) - new Date(a.created_at || 0);
        });

        articles.forEach(article => {
            const articleElement = document.createElement('div');
            articleElement.className = 'article';
            const imageSrc = article.image_url || 'images/default-image.jpg';
            
            articleElement.innerHTML = `
                <img src="${imageSrc}" alt="News Image" onerror="this.src='default-cover.jpeg'">
                <h3>${article.title || 'No title'}</h3>
                <p>${trimDescription(article.description)}</p>
                <a href="full_view.php?id=${article.id}" class="read-more">Read more</a>
                <div class="news-options">
                    <div class="vote-container">
                        <button class="upvote" data-url="${encodeURIComponent(article.article_url)}">
                            <span class="arrow">↑</span>
                        </button>
                        <span class="vote-count">${article.total_votes || 0}</span>
                        <button class="downvote" data-url="${encodeURIComponent(article.article_url)}">
                            <span class="arrow">↓</span>
                        </button>
                    </div>
                    <button class="discuss" data-article-id="${article.id}">Comments</button>
                    <button class="share">Share</button>
                    <button class="bookmark" 
                        data-url="${encodeURIComponent(article.article_url)}"
                        data-title="${encodeURIComponent(article.title)}"
                        data-description="${encodeURIComponent(article.description)}"
                        data-image="${encodeURIComponent(article.image_url)}">
                        ${article.is_bookmarked ? '★' : '☆'}
                    </button>
                </div>
            `;
            
            newsContainer.appendChild(articleElement);
        });
    }
    // Function to fetch news by category is now in news.js
    document.addEventListener('DOMContentLoaded', function() {
            // Settings sidebar functionality
            const settingsButton = document.getElementById('settings-button');
            const settingsSidebar = document.getElementById('settings-sidebar');
            const overlay = document.getElementById('overlay');
            
            if (settingsButton) {
                settingsButton.addEventListener('click', function() {
                    if (settingsSidebar) {
                        settingsSidebar.style.right = '0';
                        if (overlay) overlay.style.display = 'block';
                    }
                });
            }
        });
            // Add event listeners for category links
            document.querySelectorAll('.category-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const category = this.getAttribute('data-category');
                    console.log('Category clicked:', category);
                    fetchNewsByCategory(category);
                });
            });
    // Function to display articles
   
    </script>
</head>
<body>
    <!-- Hidden input field to store user_id -->
    <input type="hidden" id="userId" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">

    <!-- Rest of your HTML remains unchanged -->
    <header>
        <div class="left-header">
            <button id="toggle-sidebar">☰</button>
            <div class="logo">
                <img src="images/logo.jpg" alt="Logo" width="40">
            </div>
        </div>
        <nav class="top-nav">
        <?php
        // Fetch unique categories from articles table
        $categoryQuery = "SELECT DISTINCT category FROM articles WHERE category IS NOT NULL AND category != '' ORDER BY category";
        $categoryResult = $conn->query($categoryQuery);
        
        // In the navigation section, update the category link generation
        if ($categoryResult && $categoryResult->num_rows > 0) {
            while ($cat = $categoryResult->fetch_assoc()) {
                $category = htmlspecialchars($cat['category']);
                echo "<a href='#' data-category='" . $category . "' onclick='fetchNewsByCategory(\"" . $category . "\"); return false;' class='category-link'>" . ucfirst($category) . "</a>";
            }
        } else {
            // Update fallback categories to use direct links
            $defaultCategories = ['business', 'entertainment', 'health', 'sports', 'technology'];
            foreach ($defaultCategories as $category) {
                echo "<a href='#' data-category='" . $category . "' onclick='fetchNewsByCategory(\"" . $category . "\"); return false;' class='category-link'>" . ucfirst($category) . "</a>";
            }
        }
        ?>
        </nav>
        <!-- In the header section, update your existing search bar -->
        <div class="search-bar">
            <form method="GET" action="" onsubmit="fetchNewsByKeyword(document.getElementById('search-input').value); return true;">
                <input type="text" name="search" id="search-input" placeholder="Search news..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <button type="submit" class="search-button">
                    <img src="images/search-icon.png" alt="Search" width="16" height="16">
                </button>
            </form>
        </div>
        <div class="user-profile">
            <div class="settings-icon">
                <button id="settings-button" style="background: none; border: none; cursor: pointer;">
                    <img src="images/cog-solid-24.png" alt="Settings" width="25">
                </button>
            </div>
        </div>
    </header>

    <aside class="sidebar" id="sidebar">
        <ul>
            <li><a href="homepage.php">Home</a></li>
            <li><a href="trivia.php">Trivia</a></li>
            <li><a href="peoplesvoice.php">People's Voice</a></li>
            <li><a href="submission.php">Submissions</a></li>
            <li><a href="bookmark.php">Bookmarks</a></li>
            <li><a href="editorialguidelines.php">Editorial Guidelines</a></li>
            <li><a href="aboutus.php">About Us</a></li>
            <li><a href="privacysecurity.php">Privacy & Security</a></li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>

    <main>
        <section id="news-container">
            <?php
            try {
                $searchTerm = $_GET['search'] ?? '';
                
                if (!empty($searchTerm)) {
                    // First, fetch from NewsAPI
                    $apiKey = '26fd62fc2f794a7e8b34dccab29576f8';
                    $apiUrl = "https://newsapi.org/v2/everything?q=" . urlencode($searchTerm) . "&apiKey=" . $apiKey . "&language=en";
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $apiUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $apiResponse = curl_exec($ch);
                    
                    if (curl_errno($ch)) {
                        error_log('Curl error: ' . curl_error($ch));
                    } else {
                        $apiData = json_decode($apiResponse, true);
                        
                        if ($apiData && $apiData['status'] === 'ok' && !empty($apiData['articles'])) {
                            // Insert new articles into database
                            $stmt = $conn->prepare("INSERT IGNORE INTO articles (title, description, article_url, image_url) VALUES (?, ?, ?, ?)");
                            
                            foreach ($apiData['articles'] as $article) {
                                $stmt->bind_param("ssss", 
                                    $article['title'],
                                    $article['description'],
                                    $article['url'],
                                    $article['urlToImage']
                                );
                                $stmt->execute();
                            }
                        }
                    }
                    curl_close($ch);
                }
                
                // Now fetch from database with search term
                $query = "SELECT a.*, 
                            (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'upvote') - 
                            (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'downvote') as total_votes
                         FROM articles a";
                
                if (!empty($searchTerm)) {
                    $query .= " WHERE title LIKE ? OR description LIKE ?";
                    $searchParam = "%{$searchTerm}%";
                }
                
                $query .= " ORDER BY total_votes DESC, id DESC LIMIT 100";
                
                $stmt = $conn->prepare($query);
                if (!empty($searchTerm)) {
                    $stmt->bind_param("ss", $searchParam, $searchParam);
                }
                
                if (!$stmt->execute()) {
                    throw new Exception($conn->error);
                }
                
                $result = $stmt->get_result();
                
                if ($result->num_rows === 0) {
                    echo '<div class="no-results">No Results Found</div>';
                    
                    // If no search results, fetch general news
                    if (!empty($searchTerm)) {
                        $generalQuery = "SELECT a.*, 
                                        (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'upvote') - 
                                        (SELECT COUNT(*) FROM votes v WHERE v.article_url = a.article_url AND v.vote_type = 'downvote') as total_votes
                                        FROM articles a 
                                        ORDER BY total_votes DESC, id DESC 
                                        LIMIT 20";
                        
                        $generalResult = $conn->query($generalQuery);
                        
                        if ($generalResult && $generalResult->num_rows > 0) {
                            echo '<h2 class="general-news-heading">General News</h2>';
                            
                            while ($article = $generalResult->fetch_assoc()) {
                                $imageSrc = $article['image_url'] ?? 'images/default-image.jpg';
                                ?>
                                <div class="article">
                                    <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="News Image" onerror="this.src='images/default-image.jpg'">
                                    <h3><?php echo htmlspecialchars($article['title'] ?? 'No title'); ?></h3>
                                    <p><?php echo htmlspecialchars(substr($article['description'] ?? 'No description', 0, 150) . (strlen($article['description']) > 150 ? '...' : '')); ?></p>
                                    <a href="full_view.php?id=<?php echo (int)$article['id']; ?>" class="read-more">Read more</a>
                                    <div class="news-options">
                                        <div class="vote-container">
                                            <button class="upvote" data-url="<?php echo htmlspecialchars(urlencode($article['article_url'])); ?>">
                                                <span class="arrow">↑</span>
                                            </button>
                                            <span class="vote-count"><?php echo (int)$article['total_votes']; ?></span>
                                            <button class="downvote" data-url="<?php echo htmlspecialchars(urlencode($article['article_url'])); ?>">
                                                <span class="arrow">↓</span>
                                            </button>
                                        </div>
                                        <button class="discuss" data-article-id="<?php echo (int)$article['id']; ?>">Comments</button>
                                        <button class="share">Share</button>
                                        <button class="bookmark" onclick="handleBookmark(this)" data-url="<?php echo htmlspecialchars(urlencode($article['article_url'])); ?>" data-title="<?php echo htmlspecialchars($article['title']); ?>" data-description="<?php echo htmlspecialchars($article['description']); ?>" data-image="<?php echo htmlspecialchars($imageSrc); ?>">
                                    <?php
                                    if (isset($_SESSION['user_id'])) {
                                        $bookmarkCheck = $conn->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND article_url = ? LIMIT 1");
                                        $bookmarkCheck->bind_param("is", $_SESSION['user_id'], $article['article_url']);
                                        $bookmarkCheck->execute();
                                        echo $bookmarkCheck->get_result()->num_rows > 0 ? '★' : '☆';
                                        $bookmarkCheck->close();
                                    } else {
                                        echo '☆';
                                    }
                                    ?>
                                </button>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                    }
                } else {
                    while ($article = $result->fetch_assoc()) {
                        $imageSrc = $article['image_url'] ?? 'images/default-image.jpg';
                        ?>
                        <div class="article">
                            <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="News Image" onerror="this.src='default-cover.jpeg'">
                            <h3><?php echo htmlspecialchars($article['title'] ?? 'No title'); ?></h3>
                              <p><?php echo htmlspecialchars(substr($article['description'] ?? 'No description', 0, 150) . (strlen($article['description']) > 150 ? '...' : '')); ?></p>
                            <a href="full_view.php?id=<?php echo (int)$article['id']; ?>" class="read-more">Read more</a>
                            <div class="news-options">
                                <div class="vote-container">
                                    <button class="upvote" data-url="<?php echo htmlspecialchars(urlencode($article['article_url'])); ?>">
                                        <span class="arrow">↑</span>
                                    </button>
                                    <span class="vote-count"><?php echo (int)$article['total_votes']; ?></span>
                                    <button class="downvote" data-url="<?php echo htmlspecialchars(urlencode($article['article_url'])); ?>">
                                        <span class="arrow">↓</span>
                                    </button>
                                </div>
                                <button class="discuss" data-article-id="<?php echo (int)$article['id']; ?>">Comments</button>
                                <button class="share">Share</button>
                                <button class="bookmark" onclick="handleBookmark(this)" data-url="<?php echo htmlspecialchars(urlencode($article['article_url'])); ?>" data-title="<?php echo htmlspecialchars($article['title']); ?>" data-description="<?php echo htmlspecialchars($article['description']); ?>" data-image="<?php echo htmlspecialchars($imageSrc); ?>">
                                    <?php
                                    if (isset($_SESSION['user_id'])) {
                                        $bookmarkCheck = $conn->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND article_url = ? LIMIT 1");
                                        $bookmarkCheck->bind_param("is", $_SESSION['user_id'], $article['article_url']);
                                        $bookmarkCheck->execute();
                                        echo $bookmarkCheck->get_result()->num_rows > 0 ? '★' : '☆';
                                        $bookmarkCheck->close();
                                    } else {
                                        echo '☆';
                                    }
                                    ?>
                                </button>
                            </div>
                        </div>
                        <?php
                    }
                }
            } catch (Exception $e) {
                echo '<div class="error">Error loading news: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
            <div id="comment-modal" class="overlay">
    <div class="comment-modal-content">
        <div class="comment-modal-header">
            <h2>Comments</h2>
            <button id="close-comment-modal" class="close-button">&times;</button>
        </div>
        <div id="comments-list" class="comments-list">
            <!-- Comments will be loaded here -->
        </div>
        <form id="comment-form" class="comment-form">
            <input type="hidden" id="comment-article-url" name="article_url">
            <textarea id="comment-text" name="comment_text" placeholder="Write a comment..." required></textarea>
            <button type="submit" class="submit-comment-btn">Post Comment</button>
        </form>
    </div>
</div>
        </section>
    </main>

    <!-- Add settings sidebar here -->
    <div class="settings-sidebar" id="settings-sidebar">
        <div class="settings-header">
            <button class="back-arrow">←</button>
            <span>Settings</span>
        </div>
        <div class="settings-options">
            <a href="profile.php" class="settings-option">User Profile Settings</a>
            <a href="logout.php" class="settings-option">Log Out</a>
        </div>
    </div>
    <div class="settings-overlay" id="settings-overlay"></div>

    <!-- Login prompt overlay -->
    <div id="login-prompt-overlay">
        <div class="login-prompt-card">
            <h2>Login Required</h2>
            <p>You need to be logged in to perform this action.</p>
            <div class="login-prompt-buttons">
                <a href="index.php" class="login-btn">Login</a>
                <a href="signup.php" class="signup-btn">Sign Up</a>
            </div>
        </div>
    </div>
       
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Settings sidebar functionality
            const settingsButton = document.getElementById('settings-button');
            const settingsSidebar = document.getElementById('settings-sidebar');
            const overlay = document.getElementById('overlay');
            
            if (settingsButton) {
                settingsButton.addEventListener('click', function() {
                    if (settingsSidebar) {
                        settingsSidebar.style.right = '0';
                        if (overlay) overlay.style.display = 'block';
                    }
                });
            }
            
            // Close settings sidebar
            const closeSettings = document.getElementById('close-settings');
            if (closeSettings) {
                closeSettings.addEventListener('click', function() {
                    if (settingsSidebar) {
                        settingsSidebar.style.right = '-250px';
                        if (overlay) overlay.style.display = 'none';
                    }
                });
            }
            
            // Close settings when clicking overlay
            if (overlay) {
                overlay.addEventListener('click', function() {
                    if (settingsSidebar) settingsSidebar.style.right = '-250px';
                    overlay.style.display = 'none';
                });
            }
            
            // Fix share button functionality
            document.querySelectorAll('.share').forEach(button => {
                button.addEventListener('click', function() {
                    const article = this.closest('.article');
                    const articleUrl = article.querySelector('a').href;
                    
                    // Copy to clipboard
                    navigator.clipboard.writeText(articleUrl).then(function() {
                        // Show success message
                        const notification = document.createElement('div');
                        notification.className = 'notification';
                        notification.textContent = 'URL copied to clipboard!';
                        notification.style.position = 'fixed';
                        notification.style.bottom = '20px';
                        notification.style.right = '20px';
                        notification.style.backgroundColor = '#4CAF50';
                        notification.style.color = 'white';
                        notification.style.padding = '10px 20px';
                        notification.style.borderRadius = '5px';
                        notification.style.zIndex = '1000';
                        document.body.appendChild(notification);
                        
                        // Remove notification after 3 seconds
                        setTimeout(function() {
                            notification.remove();
                        }, 3000);
                    }).catch(function(err) {
                        console.error('Could not copy text: ', err);
                        alert('Failed to copy URL');
                    });
                });
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            // Check existing votes and bookmarks when page loads
            const userId = document.getElementById('userId')?.value;
            if (userId) {
                // Fetch user's bookmarks
                fetch('get_bookmarks.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const bookmarkedUrls = data.bookmarks.map(bookmark => bookmark.article_url);
                            // Update bookmark buttons
                            document.querySelectorAll('.bookmark').forEach(button => {
                                const articleUrl = decodeURIComponent(button.dataset.url);
                                if (bookmarkedUrls.includes(articleUrl)) {
                                    button.textContent = '★';
                                    button.classList.add('active');
                                }
                            });
                        }
                    })
                    .catch(error => console.error('Error fetching bookmarks:', error));
            }
            document.querySelectorAll('.article').forEach(article => {
                const upvoteBtn = article.querySelector('.upvote');
                const downvoteBtn = article.querySelector('.downvote');
                const voteCountElement = article.querySelector('.vote-count');
                
                if (upvoteBtn && downvoteBtn) {
                    const articleUrl = decodeURIComponent(upvoteBtn.dataset.url);
                    
                    // Check if user has already voted
                    fetch(`check_vote.php?article_url=${encodeURIComponent(articleUrl)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update vote count
                                if (voteCountElement) {
                                    voteCountElement.textContent = data.total_votes;
                                }
                                
                                // Update button states
                                if (data.user_vote === 'upvote') {
                                    upvoteBtn.classList.add('active');
                                } else if (data.user_vote === 'downvote') {
                                    downvoteBtn.classList.add('active');
                                }
                            }
                        })
                        .catch(error => console.error('Error checking vote:', error));
                }
            });
            // Handle voting
            document.querySelectorAll('.upvote, .downvote').forEach(button => {
                button.addEventListener('click', async function() {
                    const userId = document.getElementById('userId')?.value;
                    if (!userId) {
                        document.getElementById('login-prompt-overlay').style.display = 'flex';
                        return;
                    }
                    
                    const voteType = this.classList.contains('upvote') ? 'upvote' : 'downvote';
                    const articleUrl = decodeURIComponent(this.dataset.url);
                    const article = this.closest('.article');
                    
                    try {
                        const formData = new FormData();
                        formData.append('article_url', articleUrl);
                        formData.append('vote_type', voteType);
                        
                        const response = await fetch('submit_vote.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        console.log('Vote response:', data);
                        
                        if (!data.success) {
                            throw new Error(data.message || 'Vote failed');
                        }
                        
                        // Update vote count
                        const voteCountElement = article.querySelector('.vote-count');
                        if (voteCountElement) {
                            voteCountElement.textContent = data.new_total;
                        }
                        
                        // Update button states
                        const upvoteBtn = article.querySelector('.upvote');
                        const downvoteBtn = article.querySelector('.downvote');
                        
                        if (upvoteBtn) upvoteBtn.classList.remove('active');
                        if (downvoteBtn) downvoteBtn.classList.remove('active');
                        
                        if (data.action !== 'removed') {
                            this.classList.add('active');
                        }
                        
                    } catch (error) {
                        console.error('Vote error:', error);
                        alert('Error: ' + (error.message || 'Failed to submit vote'));
                    }
                });
            });
            
            // Handle bookmark button clicks
            document.querySelectorAll('.bookmark').forEach(button => {
                button.addEventListener('click', async function() {
                    const userId = document.getElementById('userId')?.value;
                    if (!userId) {
                        document.getElementById('login-prompt-overlay').style.display = 'flex';
                        return;
                    }

                    const articleUrl = decodeURIComponent(this.dataset.url);
                    const title = this.dataset.title;
                    const description = this.dataset.description;
                    const imageUrl = this.dataset.image;
                    const isBookmarked = this.classList.contains('active');

                    try {
                        const formData = new FormData();
                        formData.append('article_url', articleUrl);
                        formData.append('title', title);
                        formData.append('description', description);
                        formData.append('image_url', imageUrl);
                        formData.append('is_bookmarked', isBookmarked ? '0' : '1');

                        const response = await fetch('save_bookmark.php', {
                            method: 'POST',
                            body: formData
                        });

                        const data = await response.json();
                        if (data.success) {
                            // Toggle bookmark state
                            this.textContent = isBookmarked ? '☆' : '★';
                            this.classList.toggle('active');

                            // Show notification
                            const notification = document.createElement('div');
                            notification.className = 'notification';
                            notification.textContent = isBookmarked ? 'Bookmark removed!' : 'Article bookmarked!';
                            notification.style.position = 'fixed';
                            notification.style.bottom = '20px';
                            notification.style.right = '20px';
                            notification.style.backgroundColor = isBookmarked ? '#f44336' : '#4CAF50';
                            notification.style.color = 'white';
                            notification.style.padding = '10px 20px';
                            notification.style.borderRadius = '5px';
                            notification.style.zIndex = '1000';
                            document.body.appendChild(notification);

                            setTimeout(() => notification.remove(), 3000);
                        } else {
                            throw new Error(data.message || 'Failed to update bookmark');
                        }
                    } catch (error) {
                        console.error('Bookmark error:', error);
                        alert('Error: ' + (error.message || 'Failed to update bookmark'));
                    }
                });
            });

            // Close login prompt
            const loginPrompt = document.getElementById('login-prompt-overlay');
            if (loginPrompt) {
                loginPrompt.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                });
            }
            
            // Handle comment button clicks
            document.querySelectorAll('.discuss').forEach(button => {
                button.addEventListener('click', function() {
                    const articleId = this.dataset.articleId;
                    const articleUrl = this.closest('.article').querySelector('.upvote').dataset.url;
                    const decodedUrl = decodeURIComponent(articleUrl);
                    
                    // Show comment modal
                    const commentModal = document.getElementById('comment-modal');
                    const commentsList = document.getElementById('comments-list');
                    const commentForm = document.getElementById('comment-form');
                    
                    // Set the article URL in the form
                    document.getElementById('comment-article-url').value = decodedUrl;
                    
                    // Clear previous comments
                    commentsList.innerHTML = '<div class="loading-comments">Loading comments...</div>';
                    
                    // Show the modal
                    commentModal.style.display = 'flex';
                    
                    // Fetch comments for this article
                    fetch(`get_comments.php?article_url=${encodeURIComponent(decodedUrl)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (data.comments.length === 0) {
                                    commentsList.innerHTML = '<div class="no-comments">No comments yet. Be the first to comment!</div>';
                                } else {
                                    commentsList.innerHTML = '';
                                    data.comments.forEach(comment => {
                                        const commentElement = document.createElement('div');
                                        commentElement.className = 'comment';
                                        commentElement.dataset.id = comment.id;
                                        commentElement.innerHTML = `
                                            <div class="comment-header">
                                                <img src="${comment.profile_image}" alt="${comment.username}" class="comment-avatar">
                                                <div class="comment-info">
                                                    <div class="comment-username">${comment.username}</div>
                                                    <div class="comment-time">${comment.time_ago}</div>
                                                </div>
                                                <button class="report-comment" data-id="${comment.id}">Report</button>
                                            </div>
                                            <div class="comment-text">${comment.comment_text}</div>
                                        `;
                                        commentsList.appendChild(commentElement);
                                    });
                                    
                                    // Add event listeners to report buttons
                                    addReportListeners();
                                }
                            } else {
                                commentsList.innerHTML = `<div class="error-message">Error: ${data.message || 'Failed to load comments'}</div>`;
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching comments:', error);
                            commentsList.innerHTML = '<div class="error-message">Error loading comments</div>';
                        });
                });
            });
            
            // Function to add report listeners to comment report buttons
            function addReportListeners() {
                document.querySelectorAll('.report-comment').forEach(button => {
                    button.addEventListener('click', async function() {
                        const userId = document.getElementById('userId')?.value;
                        if (!userId) {
                            document.getElementById('login-prompt-overlay').style.display = 'flex';
                            return;
                        }
                        
                        const commentId = this.dataset.id;
                        const commentElement = this.closest('.comment');
                        
                        if (confirm('Are you sure you want to report this comment?')) {
                            try {
                                const formData = new FormData();
                                formData.append('comment_id', commentId);
                                
                                const response = await fetch('report_comment.php', {
                                    method: 'POST',
                                    body: formData
                                });
                                
                                const data = await response.json();
                                
                                if (data.success) {
                                    alert(data.message);
                                    
                                    if (data.removed) {
                                        // Remove the comment from the DOM if it was deleted
                                        commentElement.remove();
                                    } else {
                                        // Disable the report button to prevent multiple reports from same user
                                        this.disabled = true;
                                        this.textContent = 'Reported';
                                    }
                                } else {
                                    throw new Error(data.message || 'Failed to report comment');
                                }
                            } catch (error) {
                                console.error('Report error:', error);
                                alert('Error: ' + (error.message || 'Failed to report comment'));
                            }
                        }
                    });
                });
            }
            
            // Handle comment form submission
            const commentForm = document.getElementById('comment-form');
            if (commentForm) {
                commentForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const userId = document.getElementById('userId')?.value;
                    if (!userId) {
                        document.getElementById('login-prompt-overlay').style.display = 'flex';
                        return;
                    }
                    
                    const commentText = document.getElementById('comment-text').value.trim();
                    const articleUrl = document.getElementById('comment-article-url').value;
                    
                    if (!commentText) {
                        alert('Please enter a comment');
                        return;
                    }
                    
                    try {
                        const formData = new FormData();
                        formData.append('article_url', articleUrl);
                        formData.append('comment_text', commentText);
                        
                        const response = await fetch('submit_comment.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            // Clear the comment text
                            document.getElementById('comment-text').value = '';
                            
                            // Add the new comment to the list
                            const commentsList = document.getElementById('comments-list');
                            
                            // Remove "no comments" message if it exists
                            const noComments = commentsList.querySelector('.no-comments');
                            if (noComments) {
                                commentsList.innerHTML = '';
                            }
                            
                            const commentElement = document.createElement('div');
                            commentElement.className = 'comment';
                            commentElement.dataset.id = data.comment.id;
                            commentElement.innerHTML = `
                                <div class="comment-header">
                                    <img src="${data.comment.profile_image}" alt="${data.comment.username}" class="comment-avatar">
                                    <div class="comment-info">
                                        <div class="comment-username">${data.comment.username}</div>
                                        <div class="comment-time">just now</div>
                                    </div>
                                    <button class="report-comment" data-id="${data.comment.id}">Report</button>
                                </div>
                                <div class="comment-text">${data.comment.comment_text}</div>
                            `;
                            
                            // Add the new comment at the top
                            commentsList.insertBefore(commentElement, commentsList.firstChild);
                            
                            // Add report listener to the new comment
                            addReportListeners();
                        } else {
                            throw new Error(data.message || 'Failed to submit comment');
                        }
                    } catch (error) {
                        console.error('Comment error:', error);
                        alert('Error: ' + (error.message || 'Failed to submit comment'));
                    }
                });
            }
            
            // Close comment modal when clicking outside
            const commentModal = document.getElementById('comment-modal');
            if (commentModal) {
                commentModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                });
            }
            
            // Close comment modal with close button
            const closeCommentModal = document.getElementById('close-comment-modal');
            if (closeCommentModal) {
                closeCommentModal.addEventListener('click', function() {
                    document.getElementById('comment-modal').style.display = 'none';
                });
            }
        });
        
        // Add event listener for search form
        const searchForm = document.querySelector('.search-bar form');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                const searchInput = document.getElementById('search-input');
                if (searchInput && searchInput.value.trim()) {
                    // Let the form submit normally, but also fetch additional news
                    fetchNewsByKeyword(searchInput.value.trim());
                }
            });
        }
    </script>
    <script src="script.js"></script>
    </body>
</html>
