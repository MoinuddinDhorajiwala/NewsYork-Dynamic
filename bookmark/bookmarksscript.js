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

// Function to handle bookmark toggle
async function handleBookmark(button) {
    if (!userId) {
        alert('Please log in to bookmark articles');
        return;
    }

    const articleUrl = decodeURIComponent(button.dataset.url);
    const title = button.dataset.title;
    const description = button.dataset.description;
    const imageUrl = button.dataset.image;
    const isCurrentlyBookmarked = button.textContent.trim() === '★';

    try {
        const formData = new FormData();
        formData.append('article_url', articleUrl);
        formData.append('title', title);
        formData.append('description', description);
        formData.append('image_url', imageUrl);
        formData.append('is_bookmarked', isCurrentlyBookmarked ? '0' : '1');

        const response = await fetch('save_bookmark.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Failed to toggle bookmark');

        if (data.success) {
            // Update all bookmark buttons for this article URL
            const allBookmarkButtons = document.querySelectorAll(`button.bookmark[data-url="${encodeURIComponent(articleUrl)}"]`);
            allBookmarkButtons.forEach(btn => {
                btn.textContent = isCurrentlyBookmarked ? '☆' : '★';
            });

            // If on bookmark page and unbookmarking, remove the article element
            if (window.location.pathname.includes('bookmark.php') && isCurrentlyBookmarked) {
                const articleElement = button.closest('.article');
                if (articleElement) {
                    // Add a fade-out animation
                    articleElement.style.transition = 'opacity 0.3s ease';
                    articleElement.style.opacity = '0';
                    
                    // Remove the element after animation completes
                    setTimeout(() => {
                        articleElement.remove();
                        // Check if there are no more bookmarks
                        const remainingArticles = document.querySelectorAll('.article:not(.empty-bookmarks)');
                        if (remainingArticles.length === 0) {
                            const newsContainer = document.getElementById('news-container');
                            if (newsContainer) {
                                const emptyMessage = document.createElement('div');
                                emptyMessage.className = 'article empty-bookmarks';
                                emptyMessage.innerHTML = `
                                    <h3>No Bookmarks Yet</h3>
                                    <p>Your bookmarked articles will appear here. Go to the homepage and click the star icon (☆) to bookmark articles.</p>
                                `;
                                newsContainer.appendChild(emptyMessage);
                            }
                        }
                    }, 300);
                }
            }
        } else {
            throw new Error(data.message || 'Failed to toggle bookmark');
        }
    } catch (error) {
        console.error('Bookmark error:', error);
        alert(error.message || 'Failed to toggle bookmark. Please try again.');
    }
}