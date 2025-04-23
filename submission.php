<?php
require_once 'auth.php';
require_once 'db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $reference_links = trim($_POST['reference_links']);
    
    // Basic validation
    if (empty($title) || empty($description)) {
        $message = 'Title and description are required.';
    } else {
        $image_url = '';
        
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (in_array($ext, $allowed)) {
                $target_dir = 'uploads/submissions/';
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $target_file = $target_dir . time() . '_' . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                    $image_url = $target_file;
                }
            }
        }
        
        // Insert submission
        $stmt = $conn->prepare("INSERT INTO submissions (user_id, title, description, image_url, reference_links) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $title, $description, $image_url, $reference_links);
        
        if ($stmt->execute()) {
            $message = 'Article submitted successfully! It will be reviewed by our admins.';
        } else {
            $message = 'Error submitting article. Please try again.';
        }
    }
}

// Fetch user's submissions
$stmt = $conn->prepare("SELECT * FROM submissions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$submissions = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Article Submissions - NewsYork</title>
    <link rel="icon" href="images/logo.jpg">
    <link rel="stylesheet" href="homepagestyle.css">
    <link rel="stylesheet" href="submissions/submissionstyle.css">
    <!-- Add Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
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

        /* Updated submission form styles with Tailwind-like classes */
        .submission-section {
            @apply max-w-3xl mx-auto p-6 bg-white rounded-lg shadow-lg;
        }

        .submission-form {
            @apply space-y-6;
        }

        .form-group {
            @apply space-y-2;
        }

        .form-group label {
            @apply block text-sm font-medium text-gray-700;
        }

        .form-group input[type="text"],
        .form-group textarea {
            @apply w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500;
        }

        .form-group input[type="file"] {
            @apply block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100;
        }

        .submit-btn {
            @apply w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors;
        }

        /* Updated submission cards styles for horizontal layout */
        .submissions-grid {
            @apply flex flex-row flex-nowrap gap-6 mt-4;
            width: 100%;
        }

        .submission-card {
            @apply bg-white rounded-lg shadow-sm p-4 w-[200px] flex-shrink-0;
            border: 1px solid #eee;
        }

        .submission-header {
            @apply flex items-start justify-between mb-3;
        }

        .submission-header h3 {
            @apply text-base font-medium text-gray-900 truncate mr-2;
            max-width: 70%;
        }

        .status-badge {
            @apply text-xs px-2 py-1 rounded-full;
            background: #e8f5e9;
            color: #2e7d32;
        }

        .submission-image {
            @apply relative w-full mb-3;
            aspect-ratio: 4/3;
        }

        .submission-image img {
            @apply rounded-md w-full h-full object-cover;
        }

        .description {
            @apply text-sm text-gray-600 mb-2;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .submission-footer {
            @apply flex items-center text-xs text-gray-500 mt-auto pt-2;
            border-top: 1px solid #f0f0f0;
        }

        .no-submissions {
            @apply text-center py-4 text-gray-500 text-xs;
            aspect-ratio: 1/1;
        }

        .submission-tip {
            @apply text-[10px] text-gray-400 mt-1;
        }

        /* Message styles */
        .message {
            @apply mb-6 p-4 rounded-md;
        }

        .message:not(:empty) {
            @apply bg-green-100 text-green-700 border border-green-200;
        }
    </style>
    <script src="news.js"></script>
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
        <div class="user-profile">
            <div class="bell-icon">

            <div class="settings-icon">
                <button id="settings-button" style="background: none; border: none; cursor: pointer;">
                    <img src="images/cog-solid-24.png" alt="Settings" width="25">
                </button>
            </div>
        </div>
    </header>

    <div class="overlay" id="overlay"></div>
    
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

    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <div class="submission-section bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Submit Your Article</h1>
            
            <?php if ($message): ?>
                <div class="message mb-6 p-4 rounded-md">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="submission-form space-y-6">
                <div class="form-group">
                    <label for="title" class="block text-sm font-medium text-gray-700">Title *</label>
                    <input type="text" id="title" name="title" required 
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="Enter a compelling title for your article">
                </div>
                
                <div class="form-group">
                    <label for="description" class="block text-sm font-medium text-gray-700">Description *</label>
                    <textarea id="description" name="description" rows="8" required 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Write your article content here..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="image" class="block text-sm font-medium text-gray-700">Featured Image</label>
                    <input type="file" id="image" name="image" accept="image/*"
                           class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 
                                  file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                
                <div class="form-group">
                    <label for="reference_links" class="block text-sm font-medium text-gray-700">Reference Links</label>
                    <textarea id="reference_links" name="reference_links" rows="3" 
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            placeholder="Add any relevant links, one per line"></textarea>
                </div>
                
                <button type="submit" class="submit-btn w-full bg-blue-600 text-white py-3 px-4 rounded-md hover:bg-blue-700 
                                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                    Submit Article
                </button>
            </form>
        </div>

        <div class="my-submissions">
            <h2 class="text-xl font-bold text-gray-900 mb-6">My Submissions</h2>
            <div class="submissions-grid">
                <?php if (empty($submissions)): ?>
                    <div class="submission-card">
                        <p class="text-gray-600 text-center">No submissions yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($submissions as $submission): ?>
                        <div class="submission-card">
                            <div class="submission-header">
                                <h3><?php echo htmlspecialchars($submission['title']); ?></h3>
                                <span class="status-badge">
                                    <?php echo ucfirst($submission['status']); ?>
                                </span>
                            </div>
                            
                            <?php if ($submission['image_url']): ?>
                                <div class="submission-image">
                                    <img src="<?php echo htmlspecialchars($submission['image_url']); ?>" 
                                         alt="" 
                                         class="w-full h-full object-cover">
                                </div>
                            <?php endif; ?>
                            
                            <div class="description">
                                <?php echo nl2br(htmlspecialchars(substr($submission['description'], 0, 100))); ?>
                            </div>
                            
                            <div class="submission-footer">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>
                                </svg>
                                <?php echo date('M j', strtotime($submission['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Settings sidebar functionality
            const settingsButton = document.getElementById('settings-button');
            const settingsSidebar = document.getElementById('settings-sidebar');
            
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

            // Close login prompt
            const loginPrompt = document.getElementById('login-prompt-overlay');
            if (loginPrompt) {
                loginPrompt.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.style.display = 'none';
                    }
                });
            }
        });
    </script>
    <script src="submissions/submissionscript.js"></script>
</body>
</html>