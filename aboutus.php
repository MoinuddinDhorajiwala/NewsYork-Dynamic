<?php
require_once 'auth.php';
require_once 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - NewsYork</title>
    <link rel="stylesheet" href="Aboutus/aboutusstyle.css">
    <link rel="stylesheet" href="homepagestyle.css">
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
        
        if ($categoryResult && $categoryResult->num_rows > 0) {
            while ($cat = $categoryResult->fetch_assoc()) {
                $category = htmlspecialchars($cat['category']);
                echo "<a href='homepage.php?category=" . $category . "'>" . ucfirst($category) . "</a>";
            }
        } else {
            $defaultCategories = ['business', 'entertainment', 'health', 'sports', 'technology', 'politics'];
            foreach ($defaultCategories as $category) {
                echo "<a href='homepage.php?category=" . $category . "'>" . ucfirst($category) . "</a>";
            }
        }
        ?>
        </nav>
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
        <section id="about-container">
            <div class="article">
                <img src="Aboutus/images/aboutus_banner.jpg" alt="About Us Banner" style="width: 100%; height: auto; margin-bottom: 20px;">
                <h1>About Us</h1>
                <div class="about-content">
                    <h2>Welcome to NewsYork</h2>
                    <p>NewsYork is your premier destination for reliable, engaging, and up-to-the-minute news coverage. Our platform combines traditional journalism values with modern interactive features to create a unique news experience.</p>
                    
                    <h2>Our Mission</h2>
                    <p>We strive to deliver accurate, unbiased news while fostering an engaged community of readers who actively participate in the news ecosystem through our innovative features like trivia, voting, and the People's Voice section.</p>
                    
                    <h2>What Sets Us Apart</h2>
                    <ul>
                        <li><strong>Interactive News Experience:</strong> Engage with news through voting, bookmarking, and commenting.</li>
                        <li><strong>Community Participation:</strong> Share your voice and perspectives through our People's Voice platform.</li>
                        <li><strong>News Gamification:</strong> Test and expand your knowledge with our news-based trivia system.</li>
                        <li><strong>Personalized Experience:</strong> Customize your news feed and save articles for later reading.</li>
                    </ul>
                    
                    <h2>Our Commitment</h2>
                    <p>We are committed to maintaining the highest standards of journalism while embracing technological innovation to enhance how you consume and interact with news.</p>
                </div>
            </div>
        </section>
    </main>

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

    <script src="script.js"></script>
</body>
</html>