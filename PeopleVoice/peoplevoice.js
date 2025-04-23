// Sidebar toggle functionality
const sidebar = document.getElementById('sidebar');
const toggleSidebar = document.getElementById('toggle-sidebar');
const overlay = document.getElementById('overlay');

// Open sidebar
toggleSidebar.addEventListener('click', (event) => {
    event.stopPropagation();
    sidebar.style.left = '0';
    overlay.style.display = 'block';
});

// Close sidebar
overlay.addEventListener('click', () => {
    sidebar.style.left = '-220px';
    overlay.style.display = 'none';
});

// Prevent clicks inside the sidebar from closing it
sidebar.addEventListener('click', (event) => {
    event.stopPropagation();
});

// Create modal HTML structure and styles
const modalStyles = `
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 5px;
        }
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-modal:hover {
            color: black;
        }
        .comment-form {
            margin-top: 20px;
        }
        #comment-text {
            width: 100%;
            height: 100px;
            margin-bottom: 10px;
            padding: 8px;
        }
        #post-comment {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .comment {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        .comment-author {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .comment-date {
            color: #666;
            font-size: 0.9em;
        }
    </style>
`;

const modalHTML = `
    ${modalStyles}
    <div id="comments-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div id="comments-container"></div>
            <div class="comment-form">
                <textarea id="comment-text" placeholder="Write your comment..."></textarea>
                <button id="post-comment">Post Comment</button>
            </div>
        </div>
    </div>
`;

// Add modal to the document
document.body.insertAdjacentHTML('beforeend', modalHTML);

// Get modal elements
const modal = document.getElementById('comments-modal');
const closeModal = document.querySelector('.close-modal');
const commentsContainer = document.getElementById('comments-container');
const commentText = document.getElementById('comment-text');
const postCommentBtn = document.getElementById('post-comment');

// Close modal functionality
closeModal.onclick = () => modal.style.display = 'none';
window.onclick = (event) => {
    if (event.target === modal) modal.style.display = 'none';
};

// Function to fetch comments
async function fetchComments(submissionId) {
    try {
        commentsContainer.innerHTML = '<p>Loading comments...</p>';
        const response = await fetch(`get_submission_comments.php?submission_id=${submissionId}`);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to load comments');
        }

        if (!Array.isArray(data.comments)) {
            throw new Error('Invalid comment data received');
        }

        if (data.comments.length === 0) {
            commentsContainer.innerHTML = '<p>No comments yet. Be the first to comment!</p>';
            return;
        }

        const comments = data.comments;
        commentsContainer.innerHTML = comments.map(comment => `
            <div class="comment">
                <div class="comment-header">
                    <img src="${comment.profile_image || 'uploads/user-circle-solid-216.png'}" 
                         alt="${comment.username}" 
                         class="comment-avatar"
                         onerror="this.src='uploads/user-circle-solid-216.png'">
                    <p class="comment-author">${comment.username}</p>
                </div>
                <p class="comment-text">${comment.comment_text}</p>
                <p class="comment-date">${comment.created_at}</p>
            </div>
        `).join('');
    } catch (error) {
        console.error('Error fetching comments:', error);
        commentsContainer.innerHTML = `
            <div class="error-message">
                <p>Sorry, we couldn't load the comments at this time.</p>
                <p>Error details: ${error.message}</p>
                <button onclick="fetchComments(${submissionId})">Try Again</button>
            </div>
        `;
    }
}

// Function to post comment
async function postComment(submissionId, commentText) {
    try {
        const response = await fetch('post_comment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                submission_id: submissionId,
                comment_text: commentText
            })
        });
        
        if (response.ok) {
            // Refresh comments after posting
            fetchComments(submissionId);
            // Clear comment input
            document.getElementById('comment-text').value = '';
        } else {
            throw new Error('Failed to post comment');
        }
    } catch (error) {
        console.error('Error posting comment:', error);
        alert('Failed to post comment. Please try again.');
    }
}

// Handle comment link clicks
document.addEventListener('DOMContentLoaded', () => {
    const commentLinks = document.querySelectorAll('.comments-link');
    
    commentLinks.forEach(link => {
        link.addEventListener('click', async (event) => {
            event.preventDefault();
            const submissionId = link.dataset.submission;
            
            // Show modal
            modal.style.display = 'block';
            
            // Fetch and display comments
            await fetchComments(submissionId);
            
            // Set up post comment button
            postCommentBtn.onclick = () => {
                const text = commentText.value.trim();
                if (text) {
                    postComment(submissionId, text);
                }
            };
        });
    });
});
