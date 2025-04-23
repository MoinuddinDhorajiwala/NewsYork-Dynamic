<?php
require_once 'db.php';
require_once 'auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user's bookmarks with article details
$stmt = $conn->prepare("SELECT b.*, 
    COALESCE(CASE WHEN v.vote_type = 'upvote' THEN 1 WHEN v.vote_type = 'downvote' THEN -1 ELSE 0 END, 0) as user_vote,
    SUM(CASE WHEN v2.vote_type = 'upvote' THEN 1 ELSE 0 END) as upvotes,
    SUM(CASE WHEN v2.vote_type = 'downvote' THEN 1 ELSE 0 END) as downvotes,
    COUNT(v2.id) as total_votes
FROM bookmarks b 
LEFT JOIN votes v ON v.article_url = b.article_url AND v.user_id = ?
LEFT JOIN votes v2 ON v2.article_url = b.article_url
WHERE b.user_id = ? 
GROUP BY b.id, b.title, b.description, b.image_url, b.created_at, v.vote_type
ORDER BY b.created_at DESC");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NewsYork - My Bookmarks</title>
    <link rel="icon" href="images/logo.jpg">
    <link rel="stylesheet" href="homepagestyle.css">
    <script src="news.js"></script>
</head>
<body>
    <header>
        <div class="left-header">
            <button id="toggle-sidebar">☰</button>
            <div class="logo">
                <img src="bookmark/bookmarkicon.png" alt="Logo" width="40";">
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
                echo "<a href='homepage.php?category=" . $category . "'>" . ucfirst($category) . "</a>";
            }
        } else {
            // Update fallback categories to use direct links
            $defaultCategories = ['business', 'entertainment', 'health', 'sports', 'technology'];
            foreach ($defaultCategories as $category) {
                echo "<a href='homepage.php?category=" . $category . "'>" . ucfirst($category) . "</a>";
            }
        }
        ?>
        </nav>
        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search bookmarks" oninput="searchBookmarks(event)">
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

            <?php if ($result->num_rows === 0): ?>
            <div class="article empty-bookmarks">
                <h3>No Bookmarks Yet</h3>
                <p>Your bookmarked articles will appear here. Go to the homepage and click the star icon (☆) to bookmark articles.</p>
            </div>
            <?php else: 
                while ($article = $result->fetch_assoc()) {
                    $imageSrc = $article['image_url'] ?? 'images/default-image.jpg';
                    ?>
                    <div class="article">
                        <img src="<?php echo htmlspecialchars($imageSrc); ?>" alt="News Image" onerror="this.src='default-cover.jpeg'">
                        <h3><?php echo htmlspecialchars($article['title'] ?? 'No title'); ?></h3>
                        <p><?php echo htmlspecialchars(substr($article['description'] ?? 'No description', 0, 150) . (strlen($article['description'] ?? '') > 150 ? '...' : '')); ?></p>
                        <a href="<?php echo htmlspecialchars($article['article_url']); ?>" target="_blank">Read more</a>
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
                            <button class="bookmark" onclick="toggleBookmark(this, '<?php echo htmlspecialchars(urlencode($article['article_url'])); ?>')" data-url="<?php echo htmlspecialchars(urlencode($article['article_url'])); ?>" data-title="<?php echo htmlspecialchars($article['title']); ?>" data-description="<?php echo htmlspecialchars($article['description']); ?>" data-image="<?php echo htmlspecialchars($imageSrc); ?>">
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
                <?php } 
            endif; ?>
        </section>
    </main>
    <!-- Settings Sidebar -->
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
    <script src="script.js"></script>
    <script src="news.js"></script>
    <script>
function searchBookmarks(event) {
    const keyword = event.target.value.trim();
    fetch('search_bookmarks.php?keyword=' + encodeURIComponent(keyword))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('news-container');
                container.innerHTML = '';
                
                if (data.bookmarks.length === 0) {
                    container.innerHTML = `
                        <div class="article empty-bookmarks">
                            <h3>No Bookmarks Found</h3>
                            <p>No bookmarks match your search criteria.</p>
                        </div>
                    `;
                    return;
                }
                
                data.bookmarks.forEach(article => {
                    const articleElement = document.createElement('div');
                    articleElement.className = 'article';
                    articleElement.innerHTML = `
                        <img src="${article.image_url}" alt="News Image" onerror="this.src='default-cover.jpeg'">
                        <h3>${article.title || 'No title'}</h3>
                        <p>${trimDescription(article.description)}</p>
                        <a href="${article.article_url}" target="_blank">Read more</a>
                        <div class="news-options">
                            <div class="vote-container">
                                <button class="upvote" data-url="${encodeURIComponent(article.article_url)}">
                                    <span class="arrow">↑</span>
                                </button>
                                <span class="vote-count">${article.total_votes}</span>
                                <button class="downvote" data-url="${encodeURIComponent(article.article_url)}">
                                    <span class="arrow">↓</span>
                                </button>
                            </div>
                            <button class="discuss" data-article-id="${article.id}">Comments</button>
                            <button class="share">Share</button>
                            <button class="bookmark active" onclick="toggleBookmark(this, '${encodeURIComponent(article.article_url)}')" data-url="${encodeURIComponent(article.article_url)}" data-title="${article.title}" data-description="${article.description}" data-image="${article.image_url}">★</button>
                        </div>
                    `;
                    container.appendChild(articleElement);
                });
            }
        })
        .catch(error => console.error('Error:', error));
}

function toggleBookmark(button, articleUrl) {
    const isBookmarked = button.classList.contains('active');
    const article = button.closest('.article');
    
    if (isBookmarked) {
        fetch('remove_bookmark.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `article_url=${encodeURIComponent(articleUrl)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                article.style.opacity = '0';
                setTimeout(() => {
                    location.reload();
                }, 300);
            }
        })
        .catch(error => console.error('Error:', error));
    } else {
        fetch('get_bookmarks.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `article_url=${encodeURIComponent(articleUrl)}&is_bookmarked=1`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                button.classList.add('active');
                button.innerHTML = '★';
            }
        })
        .catch(error => console.error('Error:', error));
    }
}
document.addEventListener('DOMContentLoaded', function() {
        // Settings sidebar functionality
        const settingsButton = document.getElementById('settings-button');
        const settingsSidebar = document.getElementById('settings-sidebar');
        const overlay = document.getElementById('overlay');
        const closeSettings = document.getElementById('close-settings');

        if (settingsButton) {
            settingsButton.addEventListener('click', function() {
                if (settingsSidebar) {
                    settingsSidebar.style.right = '0';
                    if (overlay) overlay.style.display = 'block';
                }
            });
        }

        if (closeSettings) {
            closeSettings.addEventListener('click', function() {
                if (settingsSidebar) {
                    settingsSidebar.style.right = '-250px';
                    if (overlay) overlay.style.display = 'none';
                }
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function() {
                if (settingsSidebar) {
                    settingsSidebar.style.right = '-250px';
                    overlay.style.display = 'none';
                }
            });
        }
    });
</script>
<?php
function removeBookmark($conn, $user_id, $article_url) {
    if (!$user_id || !$article_url) {
        return ['success' => false, 'message' => 'Invalid parameters'];
    }

    $stmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = ? AND article_url = ?");
    $stmt->bind_param("is", $user_id, $article_url);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Bookmark removed successfully'];
        }
        return ['success' => false, 'message' => 'Bookmark not found'];
    }
    return ['success' => false, 'message' => 'Error removing bookmark'];
}
?>
</body>
</html>
