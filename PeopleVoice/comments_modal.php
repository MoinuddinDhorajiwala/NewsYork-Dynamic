<?php
if (!isset($article)) {
    exit('Direct access not allowed');
}
?>
<div class="comments-modal" id="comments-modal-<?php echo $article['id']; ?>">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Comments</h3>
            <button class="close-modal">&times;</button>
        </div>
        
        <div class="comments-container">
            <!-- Comments will be loaded here dynamically -->
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
        <form class="comment-form" id="comment-form-<?php echo $article['id']; ?>">
            <textarea name="comment" placeholder="Write your comment..." required></textarea>
            <input type="hidden" name="submission_id" value="<?php echo $article['id']; ?>">
            <button type="submit" class="submit-comment">Post Comment</button>
        </form>
        <?php else: ?>
        <div class="login-prompt">
            <p>Please <a href="index.php">log in</a> to post a comment.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.comments-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-content {
    position: relative;
    background: white;
    width: 90%;
    max-width: 600px;
    margin: 50px auto;
    padding: 20px;
    border-radius: 8px;
    max-height: 80vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.close-modal {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
}

.comments-container {
    margin-bottom: 20px;
}

.comment {
    padding: 10px;
    border-bottom: 1px solid #eee;
    margin-bottom: 10px;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 0.9em;
    color: #666;
}

.comment-form {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.comment-form textarea {
    width: 100%;
    min-height: 100px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    resize: vertical;
}

.submit-comment {
    padding: 8px 16px;
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.submit-comment:hover {
    background-color: #0056b3;
}

.login-prompt {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 4px;
}
</style>