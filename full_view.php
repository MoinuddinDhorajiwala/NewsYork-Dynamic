<?php
require_once 'auth.php';
require_once 'db.php';

// Get article ID from URL parameter
$article_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$article_id) {
    header('Location: homepage.php');
    exit;
}

// Fetch article details from the database
$stmt = $conn->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->bind_param('i', $article_id);
$stmt->execute();
$result = $stmt->get_result();
$article = $result->fetch_assoc();

if (!$article) {
    header('Location: homepage.php');
    exit;
}

// Get vote count
$vote_count = 0;
$stmt = $conn->prepare("SELECT SUM(vote_type) as vote_count FROM votes WHERE article_url = ?");
$stmt->bind_param('s', $article['url']);
$stmt->execute();
$vote_result = $stmt->get_result();
$vote_data = $vote_result->fetch_assoc();
if ($vote_data) {
    $vote_count = $vote_data['vote_count'] ?: 0;
}

// Check if user has voted
$user_vote = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT vote_type FROM votes WHERE article_url = ? AND user_id = ?");
    $stmt->bind_param('si', $article['url'], $_SESSION['user_id']);
    $stmt->execute();
    $user_vote_result = $stmt->get_result();
    if ($user_vote_data = $user_vote_result->fetch_assoc()) {
        $user_vote = $user_vote_data['vote_type'];
    }
}

// Check if article is bookmarked
$is_bookmarked = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT * FROM bookmarks WHERE user_id = ? AND article_url = ?");
    $stmt->bind_param('is', $_SESSION['user_id'], $article['url']);
    $stmt->execute();
    $bookmark_result = $stmt->get_result();
    $is_bookmarked = $bookmark_result->num_rows > 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> - NewsYork</title>
    <link rel="stylesheet" href="full_view.css">
    <script src="news.js"></script>
</head>
<body>
    <header>
        <div class="left-header">
            <button id="toggle-sidebar">☰</button>
            <div class="logo">
                <a href="homepage.php"><img src="images/logo.png" alt="Logo" height="40"></a>
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
            $defaultCategories = ['business', 'entertainment', 'health', 'sports', 'technology','politics'];
            foreach ($defaultCategories as $category) {
                echo "<a href='homepage.php?category=" . $category . "'>" . ucfirst($category) . "</a>";
            }
        }
        ?>
        </nav>
        <div class="user-profile">
            <div class="bell-icon">
                <img src="bookmark/notification_icon.png" alt="Notification" width="25">
            </div>
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
            <li><a href="peoplevoice.php">People's Voice</a></li>
            <li><a href="submissions.php">Submissions</a></li>
            <li><a href="bookmark.php">Bookmarks</a></li>
            <li><a href="editorialguidelines.php">Editorial Guidelines</a></li>
            <li><a href="aboutus.php">About Us</a></li>
            <li><a href="privacysecurity.php">Privacy & Security</a></li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>


    <main class="full-article">
        <h1><?php echo htmlspecialchars($article['title']); ?></h1>
        
        <?php if ($article['image_url']): ?>
            <img src="<?php echo htmlspecialchars($article['image_url']); ?>" alt="Article image">
        <?php endif; ?>
     
        <div class="article-description">
            <?php echo nl2br(htmlspecialchars($article['description'])); ?>
        </div>

        <div class="news-options">
            <div class="vote-container">
                <button class="upvote <?php echo $user_vote == 1 ? 'active' : ''; ?>" 
                        onclick="handleVote(this, 1, <?php echo $article_id; ?>)">▲</button>
                <span class="vote-count"><?php echo $vote_count; ?></span>
                <button class="downvote <?php echo $user_vote == -1 ? 'active' : ''; ?>" 
                        onclick="handleVote(this, -1, <?php echo $article_id; ?>)">▼</button>
            </div>
            <button class="bookmark" 
                    onclick="handleBookmark(this)" 
                    data-url="<?php echo htmlspecialchars($article['url']); ?>"
                    data-title="<?php echo htmlspecialchars($article['title']); ?>"
                    data-description="<?php echo htmlspecialchars($article['description']); ?>"
                    data-image="<?php echo htmlspecialchars($article['image_url']); ?>">
                <?php echo $is_bookmarked ? '★' : '☆'; ?>
            </button>
        </div>
    </main>
    <div class="settings-sidebar" id="settings-sidebar">
        <div class="settings-header">
            <button class="back-arrow">←</button>
            <span>Settings</span>
        </div>
        <div class="settings-options">
            <a href="profile.php" class="settings-option">User Profile Settings</a>
            <a href="#" class="settings-option">General Settings</a>
            <a href="logout.php" class="settings-option">Log Out</a>
        </div>
    </div>
    <script>
        const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'null'; ?>;
    </script>
    <script src="script.js"></script>
    <script>
        async function handleVote(button, voteType, articleId) {
            if (!userId) {
                alert('Please log in to vote');
                return;
            }

            try {
                const response = await fetch('submit_vote.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `article_id=${articleId}&vote_type=${voteType}`
                });

                const data = await response.json();
                if (data.success) {
                    const voteContainer = button.closest('.vote-container');
                    const voteCount = voteContainer.querySelector('.vote-count');
                    const upvoteBtn = voteContainer.querySelector('.upvote');
                    const downvoteBtn = voteContainer.querySelector('.downvote');

                    voteCount.textContent = data.new_vote_count;

                    upvoteBtn.classList.remove('active');
                    downvoteBtn.classList.remove('active');

                    if (data.user_vote === 1) {
                        upvoteBtn.classList.add('active');
                    } else if (data.user_vote === -1) {
                        downvoteBtn.classList.add('active');
                    }
                }
            } catch (error) {
                console.error('Vote error:', error);
                alert('Failed to submit vote. Please try again.');
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
        overlay.addEventListener('click', () => {
    settingsSidebar.style.right = '-220px';  // Close sidebar
    overlay.style.display = 'none';  // Hide overlay
});
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
    const sidebar = document.getElementById('sidebar');
const toggleSidebar = document.getElementById('toggle-sidebar');
const overlay = document.getElementById('overlay');

// Toggle sidebar visibility when clicking on the toggle button
toggleSidebar.addEventListener('click', (event) => {
    event.stopPropagation();  // Stop event from bubbling up to document
    sidebar.style.left = '0';  // Open sidebar
    overlay.style.display = 'block';  // Show overlay
});

// Close sidebar when clicking on the overlay
overlay.addEventListener('click', () => {
    sidebar.style.left = '-220px';  // Close sidebar
    overlay.style.display = 'none';  // Hide overlay
});

// Close sidebar when clicking anywhere outside the sidebar and toggle button
document.addEventListener('click', (event) => {
    const isClickInsideSidebar = sidebar.contains(event.target);
    const isClickInsideToggle = toggleSidebar.contains(event.target);

    // If the click is outside the sidebar and toggle button, close the sidebar
    if (!isClickInsideSidebar && !isClickInsideToggle) {
        sidebar.style.left = '-220px';  // Close sidebar
        settingsSidebar.style.left = '-220px';  // Close sidebar
        overlay.style.display = 'none';  // Hide overlay
    }
});

// Prevent clicks inside the sidebar from closing it
sidebar.addEventListener('click', (event) => {
    event.stopPropagation();  // Stop event from bubbling up to document
});
    </script>
</body>
</html>