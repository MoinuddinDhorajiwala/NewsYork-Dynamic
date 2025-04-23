// Function to track article interaction
function trackArticleInteraction(articleUrl, interactionType) {
    const userId = document.getElementById('userId').value;
    if (!userId) return;

    fetch('article_interactions.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=track&user_id=${userId}&article_url=${encodeURIComponent(articleUrl)}&interaction_type=${interactionType}`
    });
}

// Function to fetch news by category
function fetchNewsByCategory(category) {
    console.log("Fetching news for category: " + category);
    const hideViewed = document.getElementById('hideViewedArticles').checked;
    
    // Show loading indicator
    const newsContainer = document.querySelector('#news-container');
    if (newsContainer) {
        newsContainer.innerHTML = '<div class="loading-indicator">Loading articles for category: ' + category + '...</div>';
    }
    
    fetch(`fetchnewsbycategory.php?category=${encodeURIComponent(category)}&hide_viewed=${hideViewed}`, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('Category news fetching completed:', data);
        if (data.success) {
            // Update the news container with the fetched articles
            displayArticles(data.articles);
            // Update URL without page reload
            const url = new URL(window.location.href);
            url.searchParams.set('category', category);
            window.history.pushState({}, '', url.toString());
        } else {
            if (newsContainer) {
                const errorMessage = data.message || 'Failed to fetch category news';
                newsContainer.innerHTML = `
                    <div class="error-message">
                        <h3>Error</h3>
                        <p>${errorMessage}</p>
                        <p>Please try selecting a different category from the navigation menu.</p>
                    </div>
                `;
            }
        }
    })
    .catch(error => {
        console.error('Error fetching category news:', error);
        if (newsContainer) {
            newsContainer.innerHTML = '<div class="error-message">Error fetching category news</div>';
        }
    });
}

// Function to trim description text
function trimDescription(description, maxLength = 150) {
    if (!description) return 'No description';
    
    // Strip HTML tags
    description = description.replace(/<[^>]*>/g, '');
    
    if (description.length <= maxLength) return description;
    
    // Trim to the last complete word within maxLength
    let trimmed = description.substr(0, maxLength);
    trimmed = trimmed.substr(0, Math.min(trimmed.length, trimmed.lastIndexOf(' ')));
    
    return trimmed + '...';
}

// Function to handle article interactions
function handleArticleInteraction(element, interactionType) {
    const articleUrl = element.dataset.url;
    if (articleUrl) {
        trackArticleInteraction(articleUrl, interactionType);
    }
}

// Function to display articles
function displayArticles(articles) {
    const newsContainer = document.querySelector('#news-container');
    if (!newsContainer) return;

    newsContainer.innerHTML = '';
    
    if (!articles || articles.length === 0) {
        newsContainer.innerHTML = '<div class="no-results">No articles found for this category.</div>';
        return;
    }
    
    // Sort articles by votes and creation date
    articles.sort((a, b) => {
        if (b.total_votes !== a.total_votes) {
            return b.total_votes - a.total_votes;
        }
        return new Date(b.created_at || 0) - new Date(a.created_at || 0);
    });

    articles.forEach(article => {
        const articleElement = document.createElement('div');
        articleElement.className = 'article';
        const imageSrc = article.image_url || 'images/default-image.jpg';
        
        articleElement.innerHTML = `
            <img src="${imageSrc}" alt="News Image" onerror="this.src='default-cover.jpeg'" onload="handleArticleInteraction(this, 'view')">
            <h3>${article.title || 'No title'}</h3>
            <p>${trimDescription(article.description)}</p>
            <a href="full_view.php?id=${article.id}" class="read-more" onclick="handleArticleInteraction(this, 'view')">Read more</a>
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
}