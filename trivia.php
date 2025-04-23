<?php
session_start();
require 'db.php';

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch user data from the database
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo "User not found.";
    exit;
}

// Check if trivia table exists, if not create it
$check_table = $conn->query("SHOW TABLES LIKE 'trivia'");
if ($check_table->num_rows == 0) {
    $create_table = "CREATE TABLE trivia (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        weekly_points INT(11) DEFAULT 0,
        total_points INT(11) DEFAULT 0,
        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        politics_count INT(11) DEFAULT 0,
        business_count INT(11) DEFAULT 0,
        sports_count INT(11) DEFAULT 0,
        entertainment_count INT(11) DEFAULT 0,
        health_count INT(11) DEFAULT 0,
        technology_count INT(11) DEFAULT 0,
        last_reset_date DATE DEFAULT CURRENT_DATE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    $conn->query($create_table);
}  

// Get user's trivia points
$trivia_stmt = $conn->prepare("SELECT * FROM trivia WHERE user_id = ?");
$trivia_stmt->bind_param("i", $user_id);
$trivia_stmt->execute();
$trivia_result = $trivia_stmt->get_result();

if ($trivia_result->num_rows == 0) {
    // Create new trivia record for user
    $insert_stmt = $conn->prepare("INSERT INTO trivia (user_id, weekly_points, total_points) VALUES (?, 0, 0)");
    $insert_stmt->bind_param("i", $user_id);
    $insert_stmt->execute();
    $weekly_points = 0;
    $total_points = 0;
} else {
    $trivia_data = $trivia_result->fetch_assoc();
    $weekly_points = $trivia_data['weekly_points'];
    $total_points = $trivia_data['total_points'];
}

// Reset weekly points if it's a new week (Monday)
$today = date('N'); // 1 (Monday) through 7 (Sunday)
if ($today == 1) {
    $last_reset = isset($_SESSION['last_weekly_reset']) ? $_SESSION['last_weekly_reset'] : 0;
    $current_week = date('W');
    
    if (!isset($_SESSION['current_week']) || $_SESSION['current_week'] != $current_week) {
        // Reset weekly points
        $reset_stmt = $conn->prepare("UPDATE trivia SET weekly_points = 0 WHERE user_id = ?");
        $reset_stmt->bind_param("i", $user_id);
        $reset_stmt->execute();
        
        // Update session variables
        $_SESSION['last_weekly_reset'] = time();
        $_SESSION['current_week'] = $current_week;
        $weekly_points = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NewsYork Trivia</title>
    <link rel="icon" href="images/logo.jpg">
    <link rel="stylesheet" href="homepagestyle.css">
    <link rel="stylesheet" href="trivia/triviastyle.css">
    <script src="news.js"></script>
</head>
<body>
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
        <div class="trivia-container">
            <div class="trivia-header">
                <h1>NewsYork Trivia Challenge</h1>
                <div class="points-display">
                    <div class="weekly-points">
                        <h3>Weekly Points</h3>
                        <p id="weekly-points"><?php echo $weekly_points; ?></p>
                    </div>
                    <div class="total-points">
                        <h3>Total Points</h3>
                        <p id="total-points"><?php echo $total_points; ?></p>
                    </div>
                    <div class="leaderboard-icon">
                        <button id="leaderboard-button" title="View Leaderboard">
                            <img src="images/leaderboard-icon.png" alt="Leaderboard">
                        </button>
                    </div>
                </div>
            </div>

            <div class="category-selection">
                <h2>Select a Category</h2>
                <?php
                    $categories = ['politics', 'business', 'sports', 'entertainment', 'health', 'technology'];
                    $remaining_questions = [];
                    
                    // Get remaining questions for each category
                    $remaining_stmt = $conn->prepare("SELECT * FROM trivia WHERE user_id = ?");
                    $remaining_stmt->bind_param("i", $user_id);
                    $remaining_stmt->execute();
                    $remaining_result = $remaining_stmt->get_result();
                    $remaining_data = $remaining_result->fetch_assoc();
                    
                    foreach ($categories as $cat) {
                        $count_column = $cat . '_count';
                        $remaining_questions[$cat] = 10 - ($remaining_data[$count_column] ?? 0);
                    }
                ?>
                <div class="category-buttons">
                    <?php foreach ($categories as $cat): ?>
                        <button onclick="fetchTrivia('<?php echo $cat; ?>')" <?php echo $remaining_questions[$cat] <= 0 ? 'disabled' : ''; ?>>
                            <?php echo ucfirst($cat); ?>
                            <span class="remaining-questions">(<?php echo $remaining_questions[$cat]; ?> left)</span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="trivia-question-container" class="hidden">
                <h3 id="question-category">Category: <span id="current-category">General</span></h3>
                <div class="question-box">
                    <p id="question-text">Select a category to start the trivia challenge!</p>
                    <div id="options-container"></div>
                    <p id="result-message"></p>
                    <button id="next-question" class="hidden">Next Question</button>
                </div>
            </div>
        </div>

        <!-- Leaderboard Modal -->
        <div id="leaderboardModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="closeLeaderboard()">&times;</span>
                <h2>Trivia Leaderboard</h2>
                <div class="leaderboard-tabs">
                    <button class="leaderboard-tab active" onclick="showLeaderboard('weekly')">Weekly</button>
                    <button class="leaderboard-tab" onclick="showLeaderboard('all-time')">All Time</button>
                </div>
                <div id="weekly-leaderboard" class="leaderboard-content active">
                    <!-- Weekly leaderboard will be loaded here -->
                    <div class="loading">Loading...</div>
                </div>
                <div id="all-time-leaderboard" class="leaderboard-content">
                    <!-- All-time leaderboard will be loaded here -->
                    <div class="loading">Loading...</div>
                </div>
            </div>
        </div>
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

    <!-- Scripts -->
    <script>
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
    <script src="script.js"></script>
    <script src="trivia/triviascript.js"></script>
</body>
</html>