<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'article_interactions.php';

// Get sorting and filtering parameters
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$category = isset($_GET['category']) ? $_GET['category'] : '';

// Base query for accepted submissions with vote counts and comment counts
$query = "SELECT s.*, u.username,
        COALESCE((SELECT SUM(vote_type) FROM submission_votes WHERE submission_id = s.id), 0) as total_votes,
        COUNT(DISTINCT sc.id) as comment_count
        FROM submissions s 
        LEFT JOIN users u ON s.user_id = u.id
        LEFT JOIN submission_comments sc ON sc.submission_id = s.id
        WHERE s.status = 'accepted'
        GROUP BY s.id";

// Add sorting
if ($sort === 'popular') {
    $query .= " ORDER BY total_votes DESC, s.created_at DESC";
} else {
    $query .= " ORDER BY s.created_at DESC";
}

$stmt = $conn->prepare($query);
$stmt->execute();
$articles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>People's Voice - NewsYork</title>
    <link rel="icon" href="images/logo.jpg">
    <link rel="stylesheet" href="PeopleVoice/peoplevoicestyle.css">
    <script src="news.js"></script>
    <script src="PeopleVoice/peoplevoice.js"></script>
    <style>
        /* Header Styles */
        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-text {
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
        }

        /* Main Sidebar Styles */
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: -250px;
            background: white;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            transition: left 0.3s ease;
            z-index: 1000;
            padding-top: 60px;
            font-family: Arial, sans-serif;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar ul li {
            padding: 0;
        }

        .sidebar ul li a {
            display: block;
            padding: 15px 20px;
            color: #333;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            font-size: 16px;
        }

        .sidebar ul li a:hover,
        .sidebar ul li a.active {
            background-color: #f8f9fa;
            border-left-color: #007bff;
            color: #007bff;
        }

        .sidebar-settings {
            padding: 20px;
            border-top: 1px solid #eee;
            margin-top: 20px;
        }

        /* Toggle Switch Styles */
        .toggle-switch {
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
            color: #333;
            font-size: 14px;
        }

        .toggle-switch input {
            display: none;
        }

        .slider {
            position: relative;
            width: 40px;
            height: 20px;
            background-color: #ccc;
            border-radius: 20px;
            margin-right: 10px;
            transition: background-color 0.3s ease;
        }

        .slider:before {
            content: "";
            position: absolute;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: white;
            top: 2px;
            left: 2px;
            transition: transform 0.3s ease;
        }

        .toggle-switch input:checked + .slider {
            background-color: #007bff;
        }

        .toggle-switch input:checked + .slider:before {
            transform: translateX(20px);
        }

        /* Overlay Styles */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 999;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                left: -100%;
            }
            
            .sidebar.active {
                left: 0;
            }

            .logo-text {
                font-size: 1rem;
            }
        }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Main sidebar functionality
        const toggleSidebar = document.getElementById('toggle-sidebar');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        if (toggleSidebar) {
            toggleSidebar.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                if (overlay) {
                    overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
                }
            });
        }
        
        // Close sidebar when clicking overlay
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                overlay.style.display = 'none';
            });
        }
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !toggleSidebar.contains(e.target)) {
                sidebar.classList.remove('active');
                if (overlay) {
                    overlay.style.display = 'none';
                }
            }
        });

        // Rest of your existing JavaScript...
    });
    </script>
</head>
<body>
    <!-- Hidden input field to store user_id -->
    <input type="hidden" id="userId" value="<?php echo $_SESSION['user_id'] ?? ''; ?>">

    <header>
        <div class="left-header">
            <button id="toggle-sidebar">☰</button>
            <div class="logo-container">
                <img src="images/logo.jpg" alt="Logo" width="40">
                <span class="logo-text">NewsYork</span>
            </div>
        </div>
        <nav class="top-nav">
        <?php
        // Fetch unique categories from articles table
        $categoryQuery = "SELECT DISTINCT category FROM articles WHERE category IS NOT NULL AND category != '' ORDER BY category";
        $categoryResult = $conn->query($categoryQuery);
        
        if ($categoryResult && $categoryResult->num_rows > 0) {
            while ($cat = $categoryResult->fetch_assoc()) {
                $category = htmlspecialchars($cat['category']);
                echo "<a href='#' data-category='" . $category . "' onclick='fetchNewsByCategory(\"" . $category . "\"); return false;' class='category-link'>" . ucfirst($category) . "</a>";
            }
        } else {
            $defaultCategories = ['business', 'entertainment', 'health', 'sports', 'technology'];
            foreach ($defaultCategories as $category) {
                echo "<a href='#' data-category='" . $category . "' onclick='fetchNewsByCategory(\"" . $category . "\"); return false;' class='category-link'>" . ucfirst($category) . "</a>";
            }
        }
        ?>
        </nav>
        <div class="search-bar">
            <form method="GET" action="" onsubmit="fetchNewsByKeyword(document.getElementById('search-input').value); return true;">
                <input type="text" name="search" id="search-input" placeholder="Search news..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <button type="submit" class="search-button">
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
        <li><a href="homepage.php" <?php echo basename($_SERVER['PHP_SELF']) == 'homepage.php' ? 'class="active"' : ''; ?>>Home</a></li>
            <li><a href="trivia.php" <?php echo basename($_SERVER['PHP_SELF']) == 'trivia.php' ? 'class="active"' : ''; ?>>Trivia</a></li>
            <li><a href="peoplesvoice.php" <?php echo basename($_SERVER['PHP_SELF']) == 'peoplesvoice.php' ? 'class="active"' : ''; ?>>People's Voice</a></li>
            <li><a href="submission.php" <?php echo basename($_SERVER['PHP_SELF']) == 'submission.php' ? 'class="active"' : ''; ?>>Submissions</a></li>
            <li><a href="bookmark.php" <?php echo basename($_SERVER['PHP_SELF']) == 'bookmark.php' ? 'class="active"' : ''; ?>>Bookmarks</a></li>
            <li><a href="editorialguidelines.php" <?php echo basename($_SERVER['PHP_SELF']) == 'editorialguidelines.php' ? 'class="active"' : ''; ?>>Editorial Guidelines</a></li>
            <li><a href="aboutus.php" <?php echo basename($_SERVER['PHP_SELF']) == 'aboutus.php' ? 'class="active"' : ''; ?>>About Us</a></li>
            <li><a href="privacysecurity.php" <?php echo basename($_SERVER['PHP_SELF']) == 'privacysecurity.php' ? 'class="active"' : ''; ?>>Privacy & Security</a></li>
    </ul>
    </aside>

    <div class="overlay" id="overlay"></div>
    
    <div class="container">
        <div class="page-header">
            <h1>People's Voice</h1>
            <p>Community-driven news and stories from our users</p>
        </div>
        
        <div class="sorting-options">
            <a href="?sort=latest" class="<?php echo $sort === 'latest' ? 'active' : ''; ?>">Latest</a>
            <a href="?sort=popular" class="<?php echo $sort === 'popular' ? 'active' : ''; ?>">Popular</a>
            <a href="submission.php" class="submit-story-btn">Submit Your Story</a>
        </div>
        
        <div class="articles-grid">
            <?php foreach ($articles as $article): ?>
                <?php
                $isBookmarked = false;
                if (isset($_SESSION['user_id'])) {
                    $bookmarkStmt = $conn->prepare("SELECT 1 FROM bookmarks WHERE user_id = ? AND article_url = ?");
                    $bookmarkStmt->bind_param("is", $_SESSION['user_id'], $article['article_url']);
                    $bookmarkStmt->execute();
                    $isBookmarked = $bookmarkStmt->get_result()->num_rows > 0;
                }
                ?>
                <article class="article-card">
                    <?php if ($article['image_url']): ?>
                        <div class="article-image">
                            <img src="<?php echo htmlspecialchars($article['image_url']); ?>" alt="Article image" onerror="this.src='default-cover.jpeg'">
                        </div>
                    <?php endif; ?>
                    
                    <div class="article-content">
                        <h2><a href="full_view.php?url=<?php echo urlencode($article['article_url']); ?>">
                            <?php echo htmlspecialchars($article['title']); ?>
                        </a></h2>
                        
                        <p class="article-meta">
                            By <?php echo htmlspecialchars($article['username']); ?> |
                            <?php echo date('M j, Y', strtotime($article['created_at'])); ?>
                        </p>
                        
                        <p class="article-excerpt"><?php echo htmlspecialchars(substr($article['description'], 0, 200)) . '...'; ?></p>
                        
                        <!-- Inside the article-card loop, update the article-interactions section -->
                        <div class="article-interactions">
                            <div class="votes">
                                <button class="vote-btn upvote" data-submission="<?php echo $article['id']; ?>" data-type="upvote">
                                    ▲
                                </button>
                                <span class="vote-count"><?php echo $article['total_votes']; ?></span>
                                <button class="vote-btn downvote" data-submission="<?php echo $article['id']; ?>" data-type="downvote">
                                    ▼
                                </button>
                            </div>
                            
                            <button class="comments-link" data-submission="<?php echo $article['id']; ?>" onclick="openCommentsModal(<?php echo $article['id']; ?>)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M14 1a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H4.414A2 2 0 0 0 3 11.586l-2 2V2a1 1 0 0 1 1-1h12zM2 0a2 2 0 0 0-2 2v12.793a.5.5 0 0 0 .854.353l2.853-2.853A1 1 0 0 1 4.414 12H14a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H2z"/>
                                </svg>
                                <span class="comment-count-<?php echo $article['id']; ?>"><?php echo $article['comment_count']; ?></span> Comments
                            </button>
                            
                            <button class="bookmark-btn <?php echo $isBookmarked ? 'bookmarked' : ''; ?>" 
                                    data-submission="<?php echo $article['id']; ?>">
                                <?php echo $isBookmarked ? '★' : '☆'; ?>
                            </button>
                            
                            <button class="share-btn" data-id="<?php echo $article['id']; ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M13.5 1a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zM11 2.5a2.5 2.5 0 1 1 .603 1.628l-6.718 3.12a2.499 2.499 0 0 1 0 1.504l6.718 3.12a2.5 2.5 0 1 1-.488.876l-6.718-3.12a2.5 2.5 0 1 1 0-3.256l6.718-3.12A2.5 2.5 0 0 1 11 2.5z"/>
                                </svg>
                                Share
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Add settings sidebar -->
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

    <?php foreach ($articles as $article): ?>
        <?php include 'PeopleVoice/comments_modal.php'; ?>
    <?php endforeach; ?>

    <script src="auth.js"></script>
    <script>
        function openCommentsModal(submissionId) {
            const modal = document.getElementById(`comments-modal-${submissionId}`);
            modal.style.display = 'block';
            loadComments(submissionId);

            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeCommentsModal(submissionId);
                }
            });

            // Close modal when clicking close button
            modal.querySelector('.close-modal').addEventListener('click', function() {
                closeCommentsModal(submissionId);
            });
        }

        function closeCommentsModal(submissionId) {
            const modal = document.getElementById(`comments-modal-${submissionId}`);
            modal.style.display = 'none';
        }

        function loadComments(submissionId) {
            const commentsContainer = document.querySelector(`#comments-modal-${submissionId} .comments-container`);
            commentsContainer.innerHTML = '<div class="loading">Loading comments...</div>';

            fetch(`PeopleVoice/get_submission_comments.php?submission_id=${submissionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        commentsContainer.innerHTML = data.comments.length ? data.comments.map(comment => `
                            <div class="comment">
                                <div class="comment-header">
                                    <div class="comment-author-info">
                                        ${comment.profile_picture ? `<img src="${comment.profile_picture}" alt="${comment.username}" class="comment-avatar">` : ''}
                                        <span class="comment-author">${comment.username}</span>
                                    </div>
                                    <span class="comment-date">${comment.created_at}</span>
                                </div>
                                <div class="comment-content">${comment.comment_text}</div>
                            </div>
                        `).join('') : '<div class="no-comments">No comments yet. Be the first to comment!</div>';
                    } else {
                        commentsContainer.innerHTML = '<div class="error">Failed to load comments. Please try again.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    commentsContainer.innerHTML = '<div class="error">Failed to load comments. Please try again.</div>';
                });
        }

        // Handle comment form submission
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.comment-form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!isLoggedIn()) {
                        document.getElementById('login-prompt-overlay').style.display = 'flex';
                        return;
                    }

                    const submissionId = this.querySelector('[name="submission_id"]').value;
                    const commentText = this.querySelector('[name="comment"]').value.trim();

                    if (!commentText) {
                        alert('Please enter a comment.');
                        return;
                    }

                    const submitButton = this.querySelector('button[type="submit"]');
                    submitButton.disabled = true;
                    submitButton.textContent = 'Posting...';

                    fetch('PeopleVoice/post_submission_comment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            submission_id: submissionId,
                            comment_text: commentText
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.reset();
                            loadComments(submissionId);
                            // Update comment count
                            const countElement = document.querySelector(`.comment-count-${submissionId}`);
                            countElement.textContent = parseInt(countElement.textContent) + 1;
                        } else {
                            alert(data.message || 'Error posting comment. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to post comment. Please try again.');
                    })
                    .finally(() => {
                        submitButton.disabled = false;
                        submitButton.textContent = 'Post Comment';
                    });
                });
            });
        });


    </script>
    <script>
        function isLoggedIn() {
            return document.getElementById('userId').value !== '';
        }
    
        function handleVote(button) {
            console.log('Vote button clicked', button.dataset.submission, button.dataset.type);
            
            if (!isLoggedIn()) {
                document.getElementById('login-prompt-overlay').style.display = 'flex';
                return;
            }
            
            const submission_id = button.dataset.submission;
            const voteType = button.dataset.type;
            
            // Create FormData object
            const formData = new FormData();
            formData.append('submission_id', submission_id);
            formData.append('vote_type', voteType);
            
            // Debug log
            console.log('Sending vote data:', submission_id, voteType);
            
            fetch('submit_submission_vote.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response received', response);
                return response.json();
            })
            .then(data => {
                console.log('Vote response:', data);
                if (data.success) {
                    const voteCount = button.parentElement.querySelector('.vote-count');
                    voteCount.textContent = data.total_votes;
                    
                    // Update button styling based on vote
                    const upvoteBtn = button.parentElement.querySelector('.upvote');
                    const downvoteBtn = button.parentElement.querySelector('.downvote');
                    
                    if (voteType === 'upvote') {
                        upvoteBtn.classList.toggle('active');
                        downvoteBtn.classList.remove('active');
                    } else {
                        downvoteBtn.classList.toggle('active');
                        upvoteBtn.classList.remove('active');
                    }
                } else {
                    console.error('Vote failed:', data.message, data.debug);
                    alert('Error processing vote: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing vote. Check console for details.');
            });
        }
    
        function handleBookmark(button) {
            if (!isLoggedIn()) {
                document.getElementById('login-prompt-overlay').style.display = 'flex';
                return;
            }
            
            const submission_id = button.dataset.submission;
            
            fetch('toggle_submission_bookmark.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `submission_id=${submission_id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.classList.toggle('bookmarked');
                    button.textContent = button.classList.contains('bookmarked') ? '★' : '☆';
                }
            });
        }
    
        function handleShare(button) {
            const id = button.dataset.id;
            const url = window.location.origin + '/newsyork/full_view.php?id=' + id;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Check out this submission on NewsYork',
                    url: url
                });
            } else {
                navigator.clipboard.writeText(url)
                    .then(() => alert('Submission link copied to clipboard!'));
            }
        }
    
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners for vote buttons
            document.querySelectorAll('.vote-btn').forEach(button => {
                button.addEventListener('click', function() {
                    handleVote(this);
                });
                console.log('Added vote listener to', button.dataset.submission, button.dataset.type);
            });
            
            // Add event listeners for bookmark buttons
            document.querySelectorAll('.bookmark-btn').forEach(button => {
                button.addEventListener('click', function() {
                    handleBookmark(this);
                });
            });
            
            // Add event listeners for share buttons
            document.querySelectorAll('.share-btn').forEach(button => {
                button.addEventListener('click', function() {
                    handleShare(this);
                });
            });
    
            // Settings sidebar functionality
            const settingsButton = document.getElementById('settings-button');
            const settingsSidebar = document.getElementById('settings-sidebar');
            const settingsOverlay = document.getElementById('settings-overlay');
            const backArrow = document.querySelector('.back-arrow');
    
            if (settingsButton) {
                settingsButton.addEventListener('click', function() {
                    settingsSidebar.style.right = '0';
                    settingsOverlay.style.display = 'block';
                });
            }
    
            if (backArrow) {
                backArrow.addEventListener('click', function() {
                    settingsSidebar.style.right = '-300px';
                    settingsOverlay.style.display = 'none';
                });
            }
    
            if (settingsOverlay) {
                settingsOverlay.addEventListener('click', function() {
                    settingsSidebar.style.right = '-300px';
                    settingsOverlay.style.display = 'none';
                });
            }
    
            // Login prompt functionality
            const loginPromptOverlay = document.getElementById('login-prompt-overlay');
            
            loginPromptOverlay.addEventListener('click', function(e) {
                if (e.target === loginPromptOverlay) {
                    loginPromptOverlay.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>