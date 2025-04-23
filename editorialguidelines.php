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
    <title>Editorial Guidelines - NewsYork</title>
    <link rel="stylesheet" href="editorialguideline/editorialguidelinesstyle.css">
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
        <section id="guidelines-container">
            <div class="article">
                <img src="editorialguideline/images/editorial_banner.jpg" alt="Editorial Banner" style="width: 100%; height: auto; margin-bottom: 20px;">
                <h1>Editorial Guidelines</h1>
                <div class="guidelines-content">
                    <h2>Content Standards</h2>
                    <p>At NewsYork, we maintain high standards for all content published on our platform. Our guidelines ensure accuracy, fairness, and quality in news reporting.</p>
                    
                    <h2>Core Principles</h2>
                    <ul>
                        <li><strong>Accuracy:</strong> All facts must be verified from reliable sources.</li>
                        <li><strong>Objectivity:</strong> Present multiple viewpoints without bias.</li>
                        <li><strong>Transparency:</strong> Clear attribution of sources and disclosure of potential conflicts.</li>
                        <li><strong>Integrity:</strong> Maintain ethical standards in reporting and content creation.</li>
                    </ul>
                    
                    <h2>Submission Guidelines</h2>
                    <p>For contributors and journalists submitting content:</p>
                    <ul>
                        <li><strong>Original Content:</strong> All submissions must be original and not published elsewhere.</li>
                        <li><strong>Fact-Checking:</strong> Include sources for all factual claims.</li>
                        <li><strong>Writing Style:</strong> Clear, concise, and engaging writing.</li>
                        <li><strong>Media:</strong> High-quality images with proper attribution.</li>
                    </ul>
                    
                    <h2>Community Standards</h2>
                    <p>For community participation and comments:</p>
                    <ul>
                        <li>Respectful and constructive dialogue</li>
                        <li>No hate speech or discriminatory content</li>
                        <li>No personal attacks or harassment</li>
                        <li>Factual and relevant contributions</li>
                    </ul>
                    
                    <h2>Quality Assurance</h2>
                    <p>Our editorial team reviews all content to ensure:</p>
                    <ul>
                        <li>Compliance with journalistic standards</li>
                        <li>Accuracy of information</li>
                        <li>Clarity and readability</li>
                        <li>Proper formatting and presentation</li>
                    </ul>
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