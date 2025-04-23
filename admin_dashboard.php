<?php
session_start();
require_once 'db.php';

// Array of authorized admin user IDs
$admin_user_ids = [18, 2, 3]; // Replace with actual admin user IDs

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_id'], $admin_user_ids)) {
    header('Location: admin_index.php');
    exit();
}

// Fetch statistics
try {
    $conn = new mysqli($host, $username, $password, $database);
} catch (mysqli_sql_exception $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get total number of reported comments
$reportedCommentsQuery = "SELECT COUNT(DISTINCT comment_id) as total FROM comment_reports";
$reportedCommentsResult = $conn->query($reportedCommentsQuery);
if (!$reportedCommentsResult) {
    die("Error fetching reported comments: " . $conn->error);
}
$reportedCommentsCount = $reportedCommentsResult->fetch_assoc()['total'];
$reportedCommentsResult->free();

// Get total number of pending submissions
$pendingSubmissionsQuery = "SELECT COUNT(*) as total FROM submissions WHERE status = 'pending'";
$pendingSubmissionsResult = $conn->query($pendingSubmissionsQuery);
if (!$pendingSubmissionsResult) {
    die("Error fetching pending submissions: " . $conn->error);
}
$pendingSubmissionsCount = $pendingSubmissionsResult->fetch_assoc()['total'];
$pendingSubmissionsResult->free();

// Get recent activity
$recentActivityQuery = "SELECT 'report' as type, cr.reported_at as created_at, c.comment_text as content, u.username
                        FROM comment_reports cr
                        JOIN comments c ON cr.comment_id = c.id
                        JOIN users u ON c.user_id = u.id
                        WHERE cr.reported_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        UNION ALL
                        SELECT 'submission' as type, s.created_at, s.title as content, u.username
                        FROM submissions s
                        JOIN users u ON s.user_id = u.id
                        WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                        ORDER BY created_at DESC
                        LIMIT 50";
$recentActivityResult = $conn->query($recentActivityQuery);
if (!$recentActivityResult) {
    die("Error fetching recent activity: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NewsYork</title>
    <link rel="stylesheet" href="homepagestyle.css">
    <style>
        .dashboard-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #007bff;
            margin: 10px 0;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-button {
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .reports-button {
            background-color: #dc3545;
        }

        .submissions-button {
            background-color: #28a745;
        }

        .recent-activity {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .activity-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
            margin-right: 10px;
        }

        .type-report {
            background-color: #ffebee;
            color: #dc3545;
        }

        .type-submission {
            background-color: #e8f5e9;
            color: #28a745;
        }

        .activity-time {
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <header>
        <div class="left-header">
            <div class="logo">
                <a href="homepage.php"><img src="images/logo.png" alt="Logo" height="40"></a>
            </div>
        </div>
        <h1>Admin Dashboard</h1>
    </header>

    <main>
        <div class="dashboard-container">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Reported Comments</h3>
                    <div class="stat-number"><?php echo $reportedCommentsCount; ?></div>
                    <p>Pending Review</p>
                </div>
                <div class="stat-card">
                    <h3>Article Submissions</h3>
                    <div class="stat-number"><?php echo $pendingSubmissionsCount; ?></div>
                    <p>Awaiting Approval</p>
                </div>
            </div>

            <div class="action-buttons">
                <a href="display_reports.php" class="action-button reports-button">
                    Manage Reported Comments
                </a>
                <a href="display_submissions.php" class="action-button submissions-button">
                    Review Article Submissions
                </a>
            </div>

            <div class="recent-activity">
                <h2>Recent Activity</h2>
                <?php if ($recentActivityResult && $recentActivityResult->num_rows > 0): ?>
                    <?php while ($activity = $recentActivityResult->fetch_assoc()): ?>
                        <div class="activity-item">
                            <span class="activity-type <?php echo $activity['type'] === 'report' ? 'type-report' : 'type-submission'; ?>">
                                <?php echo ucfirst($activity['type']); ?>
                            </span>
                            <span class="activity-content">
                                <?php echo htmlspecialchars($activity['content']); ?>
                            </span>
                            <div class="activity-time">
                                by <?php echo htmlspecialchars($activity['username']); ?> • 
                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No recent activity</p>
                <?php endif; ?>
<?php $conn->close(); // Close the database connection ?>
            </div>
        </div>
    </main>
</body>
</html>