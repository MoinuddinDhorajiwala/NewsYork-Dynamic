// Initialize user ID and profanity filter
const userId = document.getElementById('userId')?.value;
console.log('User ID:', userId);
const isGuest = sessionStorage.getItem('guest') === 'true';
const apiKey = '26fd62fc2f794a7e8b34dccab29576f8';

const profanityFilter = {
    bannedWords: ['badword1', 'badword2', 'inappropriate', 'vulgar'],
    containsProfanity(text) {
        if (!text) return false;
        const words = text.toLowerCase().split(/\s+/);
        return this.bannedWords.some(banned => words.some(word => word.includes(banned)));
    },
    clean(text) {
        if (!text) return '';
        return text.split(/\s+/)
            .map(word => this.bannedWords.some(banned => word.toLowerCase().includes(banned)) ? '*'.repeat(word.length) : word)
            .join(' ');
    }
};

// Sidebar and overlay logic
// Selecting the sidebar and overlay elements
const sidebar = document.getElementById('sidebar');
const toggleSidebar = document.getElementById('toggle-sidebar');
const overlay = document.getElementById('overlay');

// Toggle sidebar visibility when clicking on the toggle button
toggleSidebar.addEventListener('click', (event) => {
    event.stopPropagation();  // Stop event from bubbling up to document
    sidebar.style.left = '0';  // Open sidebar
    overlay.style.display = 'block';  // Show overlay
});

// Close sidebar when clicking on the overlay
overlay.addEventListener('click', () => {
    sidebar.style.left = '-220px';  // Close sidebar
    overlay.style.display = 'none';  // Hide overlay
});

// Close sidebar when clicking anywhere outside the sidebar and toggle button
document.addEventListener('click', (event) => {
    const isClickInsideSidebar = sidebar.contains(event.target);
    const isClickInsideToggle = toggleSidebar.contains(event.target);

    // If the click is outside the sidebar and toggle button, close the sidebar
    if (!isClickInsideSidebar && !isClickInsideToggle) {
        sidebar.style.left = '-220px';  // Close sidebar
        overlay.style.display = 'none';  // Hide overlay
    }
});

// Prevent clicks inside the sidebar from closing it
sidebar.addEventListener('click', (event) => {
    event.stopPropagation();  // Stop event from bubbling up to document
});

// Settings sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const settingsButton = document.getElementById('settings-button');
    const settingsSidebar = document.getElementById('settings-sidebar');
    const overlay = document.getElementById('overlay');
    const closeSettings = document.getElementById('close-settings');
    
    if (settingsButton && settingsSidebar) {
        settingsButton.addEventListener('click', function(event) {
            event.stopPropagation();
            settingsSidebar.style.right = '0';
            if (overlay) overlay.style.display = 'block';
        });
    }
    
    if (closeSettings && settingsSidebar) {
        closeSettings.addEventListener('click', function() {
            settingsSidebar.style.right = '-250px';
            if (overlay) overlay.style.display = 'none';
        });
    }
    
    // Close settings sidebar when clicking on overlay
    if (overlay && settingsSidebar) {
        overlay.addEventListener('click', function() {
            settingsSidebar.style.right = '-250px';
            overlay.style.display = 'none';
        });
    }
    
    // Prevent clicks inside settings sidebar from closing it
    if (settingsSidebar) {
        settingsSidebar.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    }
});
const settingsOverlay = document.getElementById('settings-overlay');
const backArrow = document.querySelector('.back-arrow');

// Function to open settings sidebar
function openSettingsSidebar() {
    settingsSidebar.classList.add('active');
    settingsOverlay.style.display = 'block';
}

// Function to close settings sidebar
function closeSettingsSidebar() {
    settingsSidebar.classList.remove('active');
    settingsOverlay.style.display = 'none';
}

// Event listeners for settings sidebar
settingsButton.addEventListener('click', (event) => {
    event.stopPropagation();
    openSettingsSidebar();
});

backArrow.addEventListener('click', () => {
    closeSettingsSidebar();
});

settingsOverlay.addEventListener('click', () => {
    closeSettingsSidebar();
});

// Handle logout
// Find the existing logout button handler in script.js and replace it with this:

// Handle logout
logoutButton.addEventListener('click', (e) => {
    e.preventDefault();
    // Clear any user data or session if needed
    localStorage.removeItem('user'); // If you're storing any user data
    // Redirect to logout.php which will handle session destruction and redirect to index.php
    window.location.href = 'logout.php';
});
// Prevent clicks inside settings sidebar from closing it
settingsSidebar.addEventListener('click', (event) => {
    event.stopPropagation();
});
// Logout handler
function logout() {
    fetch('logout.php')
        .then(() => {
            sessionStorage.removeItem('guest');
            localStorage.removeItem('user_data');
            window.location.href = 'index.php';
        })
        .catch(() => window.location.href = 'index.php');
}

const logoutButton = document.getElementById('logout-button');
if (logoutButton) {
    logoutButton.addEventListener('click', (e) => {
        e.preventDefault();
        logout();
    });
}

// Function to update vote count display
async function updateVoteCount(articleUrl, articleElement) {
    try {
        const response = await fetch(`get_vote_count.php?article_url=${encodeURIComponent(articleUrl)}`);
        if (!response.ok) throw new Error('Failed to get vote count');
        
        const data = await response.json();
        if (data.success) {
            const voteCountElement = articleElement.querySelector('.vote-count');
            if (voteCountElement) {
                voteCountElement.textContent = data.total_votes || 0;
            }
        }
    } catch (error) {
        console.error('Error updating vote count:', error);
    }
}

// Function to check if user has already voted
async function checkExistingVote(articleUrl, articleElement) {
    if (!userId) return;
    
    try {
        const response = await fetch(`check_vote.php?user_id=${userId}&article_url=${encodeURIComponent(articleUrl)}`);
        if (!response.ok) throw new Error('Failed to check vote');
        
        const data = await response.json();
        if (data.success && data.vote_type) {
            const button = articleElement.querySelector(`.${data.vote_type}`);
            if (button) button.classList.add('active');
        }
    } catch (error) {
        console.error('Error checking existing vote:', error);
    }
}

// Main news fetching function
async function fetchNews(keyword = '', category = 'general') {
    const newsContainer = document.getElementById("news-container");
    
    if (!newsContainer) {
        console.error("News container not found");
        return;
    }
    
    try {
        // Show loading indicator
        newsContainer.innerHTML = '<div class="loading">Loading news articles...</div>';
        
        // Fetch articles from our database
        const response = await fetch('get_articles.php');
        const data = await response.json();
        
        if (!data.success) {
            throw new Error(data.message || 'Failed to fetch articles');
        }
        
        // Clear container before adding new articles
        newsContainer.innerHTML = '';
        
        if (!data.articles || data.articles.length === 0) {
            newsContainer.innerHTML = '<div class="no-results">No articles found.</div>';
            return;
        }
        
        // Display each article
        data.articles.forEach(article => {
            const articleElement = document.createElement("div");
            articleElement.classList.add("article");
            
            const imageSrc = article.image_url || 'images/default-image.jpg';
            
            articleElement.innerHTML = `
                <img src="${imageSrc}" alt="News Image" onerror="this.src='images/default-image.jpg'">
                <h3>${article.title || 'No title'}</h3>
                <p>${article.description || 'No description'}</p>
                <a href="${article.article_url}" target="_blank">Read more</a>
                <div class="news-options">
                    <div class="vote-container">
                        <button class="upvote" data-url="${encodeURIComponent(article.article_url)}">
                            <span class="arrow">↑</span>
                        </button>
                        <span class="vote-count">${article.total_votes || 0}</span>
                        <button class="downvote" data-url="${encodeURIComponent(article.article_url)}">
                            <span class="arrow">↓</span>
                        </button>
                    </div>
                    <button class="discuss" data-article-id="${article.id}">Comments</button>
                    <button class="share">Share</button>
                    <button class="bookmark" 
                        data-url="${encodeURIComponent(article.article_url)}"
                        data-title="${encodeURIComponent(article.title)}"
                        data-description="${encodeURIComponent(article.description)}"
                        data-image="${encodeURIComponent(article.image_url)}">
                        ${article.is_bookmarked ? '★' : '☆'}
                    </button>
                </div>
            `;
            
            newsContainer.appendChild(articleElement);
        });
        
    } catch (error) {
        console.error('Error:', error);
        newsContainer.innerHTML = `<div class="error">Error loading news: ${error.message}</div>`;
    }
}

// Initialize the page
document.addEventListener('DOMContentLoaded', () => {
    console.log('Document loaded, fetching news...');
    fetchNews();  // Call fetchNews when page loads
});

// Load comments for an article
async function loadComments(articleId) {
    if (!articleId) return;
    
    try {
        const response = await fetch(`get_comments.php?article_id=${articleId}`);
        if (!response.ok) throw new Error('Failed to load comments');
        
        const data = await response.json();
        const commentsList = document.querySelector(`.comments-list[data-article-id="${articleId}"]`);
        
        if (!commentsList) return;
        
        if (data.success && data.comments && data.comments.length > 0) {
            commentsList.innerHTML = '';
            data.comments.forEach(comment => {
                const li = document.createElement('li');
                li.innerHTML = `
                    <div class="comment-header">
                        <span class="comment-user">${comment.username || 'Anonymous'}</span>
                        <span class="comment-date">${new Date(comment.created_at).toLocaleString()}</span>
                    </div>
                    <p>${profanityFilter.clean(comment.comment_text)}</p>
                `;
                commentsList.appendChild(li);
            });
        } else {
            commentsList.innerHTML = '<li class="no-comments">No comments yet. Be the first to comment!</li>';
        }
    } catch (error) {
        console.error('Error loading comments:', error);
    }
}

// Post a new comment
async function postComment(articleId, commentText) {
    if (!userId) {
        showLoginPrompt();
        return;
    }
    
    if (!commentText.trim()) {
        alert('Please enter a comment');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('article_id', articleId);
        formData.append('user_id', userId);
        formData.append('comment_text', commentText);
        
        const response = await fetch('post_comment.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) throw new Error('Failed to post comment');
        
        const data = await response.json();
        if (data.success) {
            // Reload comments to show the new one
            loadComments(articleId);
            // Clear the comment textarea
            const textarea = document.querySelector(`.post-comment[data-article-id="${articleId}"]`)
                                      .closest('.comments-section')
                                      .querySelector('textarea');
            if (textarea) textarea.value = '';
        } else {
            throw new Error(data.message || 'Failed to post comment');
        }
    } catch (error) {
        console.error('Error posting comment:', error);
        alert(error.message || 'Failed to post comment. Please try again.');
    }
}

// Voting handlers
async function handleVote(button, voteType) {
    if (!userId) {
        showLoginPrompt();
        return;
    }

    try {
        const articleUrl = decodeURIComponent(button.dataset.url);
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('article_url', articleUrl);
        formData.append('vote_type', voteType);
        
        const response = await fetch("submit_vote.php", {
            method: "POST",
            body: formData
        });

        if (!response.ok) throw new Error('Failed to submit vote');
        
        const result = await response.json();
        if (!result.success) throw new Error(result.message || "Vote failed");

        const voteContainer = button.closest('.vote-container');
        const countElement = voteContainer.querySelector('.vote-count');
        if (countElement) countElement.textContent = result.new_total || 0;
        
        // Update button states
        const upvoteBtn = voteContainer.querySelector('.upvote');
        const downvoteBtn = voteContainer.querySelector('.downvote');
        if (upvoteBtn) upvoteBtn.classList.remove('active');
        if (downvoteBtn) downvoteBtn.classList.remove('active');
        
        if (result.action !== 'removed') button.classList.add('active');
    } catch (error) {
        console.error('Vote error:', error);
        alert(error.message || "Vote failed. Try again.");
    }
}

// Function to handle bookmark toggle
async function handleBookmark(button) {
    if (!userId) {
        showLoginPrompt();
        return;
    }

    const articleUrl = decodeURIComponent(button.dataset.url);
    const title = decodeURIComponent(button.dataset.title);
    const description = decodeURIComponent(button.dataset.description);
    const imageUrl = decodeURIComponent(button.dataset.image);
    const isCurrentlyBookmarked = button.textContent.trim() === '★';

    try {
        const formData = new FormData();
        formData.append('article_url', articleUrl);
        formData.append('title', title);
        formData.append('description', description);
        formData.append('image_url', imageUrl);

        const response = await fetch('save_bookmark.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (data.success) {
            button.textContent = isCurrentlyBookmarked ? '☆' : '★';
        } else {
            throw new Error(data.message || 'Failed to toggle bookmark');
        }
    } catch (error) {
        console.error('Bookmark error:', error);
        alert('Failed to toggle bookmark. Please try again.');
    }
}

// Save bookmark to database
async function saveBookmark(articleUrl, isBookmarked) {
    if (!userId) return;
    
    try {
        const formData = new FormData();
        formData.append('user_id', userId);
        formData.append('article_url', articleUrl);
        formData.append('is_bookmarked', isBookmarked ? '1' : '0');
        
        const response = await fetch('save_bookmark.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) throw new Error('Failed to save bookmark');
        
        const result = await response.json();
        if (!result.success) throw new Error(result.message);
        
        // Show success message
        const message = isBookmarked ? 'Article bookmarked!' : 'Bookmark removed';
        const notification = document.createElement('div');
        notification.className = 'notification';
        notification.textContent = message;
        document.body.appendChild(notification);
        
        // Remove notification after 3 seconds
        setTimeout(() => {
            notification.remove();
        }, 3000);
    } catch (error) {
        console.error('Bookmark error:', error);
    }
}

// Handle search functionality
const searchInput = document.getElementById('search-input');
if (searchInput) {
    searchInput.addEventListener('keypress', (event) => {
        if (event.key === 'Enter') {
            const query = searchInput.value.trim();
            if (query) {
                fetchNews(query);
            } else {
                alert('Please enter a search term.');
            }
        }
    });
}

// Initialize the page
document.addEventListener('DOMContentLoaded', () => {
    console.log('Document loaded, fetching news...');
    fetchNews();
    
    // Fix navigation menu event listeners
    document.querySelectorAll('nav.top-nav a').forEach(link => {
        const category = link.textContent.toLowerCase();
        link.setAttribute('data-category', category);
        
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const category = this.getAttribute('data-category');
            fetchNews(category);
        });
    });
});
