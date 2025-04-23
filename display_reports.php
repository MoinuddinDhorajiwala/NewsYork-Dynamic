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

// Handle comment deletion
if (isset($_POST['delete_comment'])) {
    $comment_id = $_POST['comment_id'];
    
    // Delete the comment and its reports
    $deleteReportsQuery = "DELETE FROM comment_reports WHERE comment_id = ?";
    $deleteCommentQuery = "DELETE FROM comments WHERE id = ?";
    
    // Update the reports count in comments table
    $updateReportsQuery = "UPDATE comments SET reports = reports - 1 WHERE id = ?";
    
    // Start transaction
    $conn->begin_transaction();
    
    $stmt = $conn->prepare($deleteReportsQuery);
    $stmt->bind_param('i', $comment_id);
    if (!$stmt->execute()) {
        $conn->rollback();
        exit('Error deleting reports');
    }
    
    $stmt = $conn->prepare($deleteCommentQuery);
    $stmt->bind_param('i', $comment_id);
    if (!$stmt->execute()) {
        $conn->rollback();
        exit('Error deleting comment');
    }
    
    $stmt = $conn->prepare($updateReportsQuery);
    $stmt->bind_param('i', $comment_id);
    if (!$stmt->execute()) {
        $conn->rollback();
        exit('Error updating reports count');
    }
    
    // Commit transaction
    $conn->commit();
    
    header('Location: display_reports.php');
    exit();
}

// Fetch reported comments with count and details
$query = "SELECT c.id, c.comment_text, c.user_id, c.article_url, c.created_at, c.reports,
          COUNT(cr.id) as report_count,
          GROUP_CONCAT(DISTINCT u_reporter.username) as reporters,
          u_commenter.username as commenter_username
          FROM comments c
          INNER JOIN comment_reports cr ON c.id = cr.comment_id
          INNER JOIN users u_commenter ON c.user_id = u_commenter.id
          INNER JOIN users u_reporter ON cr.user_id = u_reporter.id
          GROUP BY c.id, c.comment_text, c.user_id, c.article_url, c.created_at, c.reports, u_commenter.username
          ORDER BY c.reports DESC, c.created_at DESC";

$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reported Comments - NewsYork</title>
    <link rel="stylesheet" href="homepagestyle.css">
    <style>
        .reports-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .reports-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        .reports-table th,
        .reports-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .reports-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .delete-btn {
            background-color: #ff4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .delete-btn:hover {
            background-color: #cc0000;
        }

        .report-count {
            font-weight: bold;
            color: #ff4444;
        }

        .no-reports {
            text-align: center;
            padding: 40px;
            color: #666;
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
        <h1>Comment Moderation Panel</h1>
    </header>



    <main>
        <div class="reports-container">
            <h1>Reported Comments</h1>
            <?php if ($result && $result->num_rows > 0): ?>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Comment</th>
                            <th>Commenter</th>
                            <th>Report Count</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['comment_text']); ?></td>
                                <td><?php echo htmlspecialchars($row['commenter_username']); ?></td>
                                <td class="report-count"><?php echo $row['report_count']; ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this comment?');">
                                        <input type="hidden" name="comment_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_comment" class="delete-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-reports">
                    <h2>No reported comments found</h2>
                    <p>There are currently no comments that have been reported by users.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>