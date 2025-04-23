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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NewsYork Profile</title>
    <link rel="stylesheet" href="homepagestyle.css">
    <link rel="stylesheet" href="profile.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="left-header">
            <button id="toggle-sidebar">☰</button>
            <div class="logo">
                <img src="images/logo.jpg" alt="Logo" width="40">
            </div>
        </div>
        <nav class="top-nav">
            <a href="#" onclick="fetchNews('politics')">Politics</a>
            <a href="#" onclick="fetchNews('business')">Business</a>
            <a href="#" onclick="fetchNews('sports')">Sports</a>
            <a href="#" onclick="fetchNews('entertainment')">Entertainment</a>
            <a href="#" onclick="fetchNews('health')">Health</a>
            <a href="#" onclick="fetchNews('technology')">Technology</a>
        </nav>
        <div class="search-bar">
            <input type="text" id="search-input" placeholder="Search" onkeypress="searchNews(event)">
        </div>
        <div class="user-profile">
            <div class="bell-icon">
                <img src="images/bell-regular-24.png" alt="Notification" width="25">
            </div>
            <div class="settings-icon">
                <button id="settings-button">
                    <img src="images/cog-solid-24.png" alt="Settings" width="25">
                </button>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul>
            <li><a href="homepage.php">Home</a></li>
            <li><a href="trivia.php">Trivia</a></li>
            <li><a href="peoplevoice.php">People's Voice</a></li>
            <li><a href="submissions.php">Submissions</a></li>
            <li><a href="bookmarks.php">Bookmarks</a></li>
            <li><a href="editorialguidelines.php">Editorial Guidelines</a></li>
            <li><a href="aboutus.php">About Us</a></li>
            <li><a href="privacysecurity.php">Privacy & Security</a></li>
        </ul>
    </aside>

    <div class="overlay" id="overlay"></div>

    <!-- Main Content -->
    <main>
        <div class="profile-container">
            <div class="header">
                <button class="edit-profile-btn" onclick="openModal()">Edit Profile</button>
            </div>

            <div class="profile-info">
                <div class="profile-banner"></div>
                <label for="upload-pfp" class="profile-picture" title="Click to upload">
                    <img id="profile-img" src="<?php echo htmlspecialchars($user['profile_picture'] ?? 'default-pfp.png'); ?>" alt="Profile Picture">
                    <input type="file" id="upload-pfp" accept="image/*" style="display: none;" onchange="uploadPfp(event)">
                </label>
                <div class="profile-details">
                    <h1 class="name" id="name-display"><?php echo htmlspecialchars($user['fullname']); ?></h1>
                    <p class="username" id="username-display"><?php echo htmlspecialchars($user['username']); ?></p>
                    <p class="bio" id="bio-display"><?php echo htmlspecialchars($user['bio'] ?? 'No bio available.'); ?></p>
                    <div class="additional-info">
                        <p><strong>DOB:</strong> <span id="dob-display"><?php echo htmlspecialchars($user['dob'] ?? 'Not specified'); ?></span></p>
                        <p><strong>Joined:</strong> <?php echo date('F Y', strtotime($user['created_at'])); ?></p>
                        <p><strong>Location:</strong> <span id="location-display"><?php echo htmlspecialchars($user['location'] ?? 'Not specified'); ?></span></p>
                    </div>
                </div>
            </div>

            <div class="profile-tabs">
                <button class="tab active" onclick="showTab('bookmarks')">Bookmarks</button>
                <button class="tab" onclick="showTab('articles')">Articles Posted</button>
            </div>

            <div class="tab-content">
                <div id="bookmarks" class="tab-panel active">
                    <p>No bookmarks yet.</p>
                </div>
                <div id="articles" class="tab-panel">
                    <p>No articles posted yet.</p>
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
            <a href="settings.php" class="settings-option">General Settings</a>
            <a href="#" class="settings-option" id="logout-button">Log Out</a>
        </div>
    </div>
    <div class="settings-overlay" id="settings-overlay"></div>

    <!-- Edit Profile Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <h2>Edit Profile</h2>
            <form id="editProfileForm" method="POST" action="update_profile.php">
                <label for="edit-name">Name</label>
                <input type="text" id="edit-name" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>">

                <label for="edit-username">Username</label>
                <input type="text" id="edit-username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">

                <label for="edit-bio">Bio</label>
                <input type="text" id="edit-bio" name="bio" value="<?php echo htmlspecialchars($user['bio'] ?? ''); ?>">

                <label for="edit-dob">Date of Birth</label>
                <input type="date" id="edit-dob" name="dob" value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>">

                <label for="edit-location">Location</label>
                <input type="text" id="edit-location" name="location" value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>">

                <button type="submit">Save</button>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="api.js"></script>
    <script src="script.js"></script>
    <script>
        function openModal() {
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function saveProfile() {
            document.getElementById('name-display').textContent = document.getElementById('edit-name').value;
            document.getElementById('username-display').textContent = document.getElementById('edit-username').value;
            document.getElementById('bio-display').textContent = document.getElementById('edit-bio').value;
            document.getElementById('dob-display').textContent = formatDate(document.getElementById('edit-dob').value);
            document.getElementById('location-display').textContent = document.getElementById('edit-location').value;
            closeModal();
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        function showTab(tabId) {
            // Hide all tab panels
            document.querySelectorAll('.tab-panel').forEach(panel => {
                panel.classList.remove('active');
            });

            // Show the selected tab panel
            document.getElementById(tabId).classList.add('active');

            // Update tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        function uploadPfp(event) {
            const file = event.target.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('profile_picture', file);

                fetch('upload_pfp.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('profile-img').src = data.filePath;
                    } else {
                        alert('Failed to upload profile picture.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>