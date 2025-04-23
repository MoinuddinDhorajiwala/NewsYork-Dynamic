<?php
session_start();
require_once 'db.php';

// Array of authorized admin user IDs
$admin_user_ids = [18, 2, 3]; // Replace with actual admin user IDs

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin'] || !in_array($_SESSION['user_id'], $admin_user_ids)) {
    header('Location: admin_index.php');
    exit();
}

// Handle submission actions
if (isset($_POST['action']) && isset($_POST['submission_id'])) {
    $submission_id = $_POST['submission_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        // Update submission status to accepted
        $stmt = $conn->prepare("UPDATE submissions SET status = 'accepted', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param('i', $submission_id);
        $stmt->execute();
    } elseif ($action === 'reject') {
        // Update submission status to rejected
        $stmt = $conn->prepare("UPDATE submissions SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param('i', $submission_id);
        $stmt->execute();
    }
    
    header('Location: display_submissions.php');
    exit();
}

// Fetch submissions with sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

$query = "SELECT s.*, u.username 
          FROM submissions s 
          LEFT JOIN users u ON s.user_id = u.id";

if ($status !== 'all') {
    $query .= " WHERE s.status = '" . $conn->real_escape_string($status) . "'";
}

if ($sort === 'oldest') {
    $query .= " ORDER BY s.created_at ASC";
} else {
    $query .= " ORDER BY s.created_at DESC";
}

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Article Submissions - NewsYork</title>
    <link rel="stylesheet" href="homepagestyle.css">
    <style>
        .submissions-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .submissions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .submissions-table th,
        .submissions-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .submissions-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .action-btn {
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            border: none;
            margin-right: 5px;
        }

        .approve-btn {
            background-color: #4CAF50;
            color: white;
        }

        .reject-btn {
            background-color: #ff4444;
            color: white;
        }

        .approve-btn:hover {
            background-color: #45a049;
        }

        .reject-btn:hover {
            background-color: #cc0000;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
        }

        .status-badge.pending {
            background-color: #ffd700;
            color: #000;
        }

        .status-badge.accepted {
            background-color: #4CAF50;
            color: white;
        }

        .status-badge.rejected {
            background-color: #ff4444;
            color: white;
        }

        .sorting-options {
            margin: 20px 0;
        }

        .sorting-options a {
            margin-right: 15px;
            padding: 8px 16px;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
            background: #f5f5f5;
        }

        .sorting-options a.active {
            background: #007bff;
            color: white;
        }

        .no-submissions {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .preview-content {
            max-height: 100px;
            overflow: hidden;
            text-overflow: ellipsis;
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
        <h1>Article Submissions Panel</h1>
    </header>

    <main>
        <div class="submissions-container">
            <h1>Article Submissions</h1>
            
            <div class="sorting-options">
                <a href="?sort=latest&status=<?php echo $status; ?>" class="<?php echo $sort === 'latest' ? 'active' : ''; ?>">Latest</a>
                <a href="?sort=oldest&status=<?php echo $status; ?>" class="<?php echo $sort === 'oldest' ? 'active' : ''; ?>">Oldest</a>
                |
                <a href="?sort=<?php echo $sort; ?>&status=all" class="<?php echo $status === 'all' ? 'active' : ''; ?>">All</a>
                <a href="?sort=<?php echo $sort; ?>&status=pending" class="<?php echo $status === 'pending' ? 'active' : ''; ?>">Pending</a>
                <a href="?sort=<?php echo $sort; ?>&status=accepted" class="<?php echo $status === 'accepted' ? 'active' : ''; ?>">Accepted</a>
                <a href="?sort=<?php echo $sort; ?>&status=rejected" class="<?php echo $status === 'rejected' ? 'active' : ''; ?>">Rejected</a>
            </div>

            <?php if ($result && $result->num_rows > 0): ?>
                <table class="submissions-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['title']); ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="preview-content"><?php echo htmlspecialchars($row['description']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this submission?');">
                                            <input type="hidden" name="submission_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <button type="submit" class="action-btn approve-btn">Approve</button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to reject this submission?');">
                                            <input type="hidden" name="submission_id" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="action" value="reject">
                                            <button type="submit" class="action-btn reject-btn">Reject</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-submissions">
                    <h2>No submissions found</h2>
                    <p>There are currently no article submissions to review.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>