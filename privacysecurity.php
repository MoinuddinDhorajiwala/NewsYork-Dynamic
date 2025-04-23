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
    <title>Privacy & Security - NewsYork</title>
    <link rel="stylesheet" href="privacy&security/privacystyle.css">
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
        <section id="privacy-container">
            <div class="article">
                <img src="privacy&security/images/privacy_banner.jpg" alt="Privacy Banner" style="width: 100%; height: auto; margin-bottom: 20px;">
                <h1>Privacy & Security</h1>
                <div class="privacy-content">
                    <h2>Your Privacy Matters</h2>
                    <p>At NewsYork, we take your privacy and data security seriously. This page outlines our commitment to protecting your personal information and ensuring a secure browsing experience.</p>
                    
                    <h2>Data Collection & Usage</h2>
                    <ul>
                        <li><strong>Personal Information:</strong> We collect only essential information needed to provide our services.</li>
                        <li><strong>Usage Data:</strong> Anonymous analytics help us improve your experience.</li>
                        <li><strong>Cookies:</strong> Used to enhance site functionality and user experience.</li>
                        <li><strong>User Preferences:</strong> Stored securely to personalize your news feed.</li>
                    </ul>
                    
                    <h2>Security Measures</h2>
                    <p>We implement industry-standard security protocols to protect your data:</p>
                    <ul>
                        <li><strong>Encryption:</strong> All sensitive data is encrypted during transmission.</li>
                        <li><strong>Secure Storage:</strong> Your data is stored in secure, protected servers.</li>
                        <li><strong>Regular Audits:</strong> We conduct security audits to maintain data protection.</li>
                        <li><strong>Access Control:</strong> Strict protocols govern access to user data.</li>
                    </ul>
                    
                    <h2>Your Rights</h2>
                    <p>You have control over your data:</p>
                    <ul>
                        <li>Access your personal information</li>
                        <li>Request data correction or deletion</li>
                        <li>Opt-out of non-essential data collection</li>
                        <li>Download your data in a portable format</li>
                    </ul>
                    
                    <h2>Contact Us</h2>
                    <p>If you have any questions about our privacy practices or need to report a security concern, please contact our privacy team.</p>
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