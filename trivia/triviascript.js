// Global variables
let currentCategory = '';
let currentQuestions = [];
let currentQuestionIndex = 0;
let answeredQuestions = {};

// Function to handle error messages
function showError(message) {
    document.getElementById('trivia-question-container').classList.remove('hidden');
    document.getElementById('question-text').textContent = message;
    document.getElementById('options-container').innerHTML = '';
    document.getElementById('result-message').textContent = '';
    document.getElementById('next-question').classList.add('hidden');
}

// Fetch trivia questions based on category
function fetchTrivia(category) {
    currentCategory = category;
    document.getElementById('current-category').textContent = category.charAt(0).toUpperCase() + category.slice(1);
    
    // Show loading state
    document.getElementById('trivia-question-container').classList.remove('hidden');
    document.getElementById('question-text').textContent = 'Loading questions...';
    document.getElementById('options-container').innerHTML = '';
    document.getElementById('result-message').textContent = '';
    document.getElementById('next-question').classList.add('hidden');
    
    // Check if we already have questions for this category
    if (answeredQuestions[category] && answeredQuestions[category].length > 0) {
        currentQuestions = answeredQuestions[category];
        displayQuestion();
        return;
    }
    
    // Fetch new questions
    fetch(`get_trivia.php?category=${category}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                currentQuestions = data.questions;
                currentQuestionIndex = 0;
                answeredQuestions[category] = currentQuestions;
                displayQuestion();
            } else {
                document.getElementById('question-text').textContent = 'Failed to load questions. Please try again.';
            }
        })
        .catch(error => {
            console.error('Error fetching trivia:', error);
            document.getElementById('question-text').textContent = 'An error occurred. Please try again.';
        });
}

// Display current question
function displayQuestion() {
    if (currentQuestionIndex >= currentQuestions.length) {
        // No more questions
        document.getElementById('question-text').textContent = 'You have completed all questions in this category!';
        document.getElementById('options-container').innerHTML = '';
        document.getElementById('result-message').textContent = '';
        document.getElementById('next-question').classList.add('hidden');
        return;
    }
    
    const question = currentQuestions[currentQuestionIndex];
    document.getElementById('question-text').textContent = question.question;
    
    // Create options
    const optionsContainer = document.getElementById('options-container');
    optionsContainer.innerHTML = '';
    
    const options = [...question.incorrect_answers, question.correct_answer];
    // Shuffle options
    for (let i = options.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [options[i], options[j]] = [options[j], options[i]];
    }
    
    options.forEach(option => {
        const button = document.createElement('button');
        button.textContent = option;
        button.classList.add('option-button');
        button.onclick = () => checkAnswer(option, question.correct_answer);
        optionsContainer.appendChild(button);
    });
    
    document.getElementById('result-message').textContent = '';
    document.getElementById('next-question').classList.add('hidden');
}

// Check if the answer is correct
function checkAnswer(selectedAnswer, correctAnswer) {
    const resultMessage = document.getElementById('result-message');
    const optionButtons = document.querySelectorAll('.option-button');
    
    // Disable all option buttons
    optionButtons.forEach(button => {
        button.disabled = true;
        
        if (button.textContent === correctAnswer) {
            button.classList.add('correct');
        } else if (button.textContent === selectedAnswer && selectedAnswer !== correctAnswer) {
            button.classList.add('incorrect');
        }
    });
    
    if (selectedAnswer === correctAnswer) {
        resultMessage.textContent = 'Correct! +10 points';
        resultMessage.className = 'correct-message';
        
        // Update points in the database
        updatePoints(10);
    } else {
        resultMessage.textContent = `Incorrect. The correct answer is: ${correctAnswer}`;
        resultMessage.className = 'incorrect-message';
    }
    
    document.getElementById('next-question').classList.remove('hidden');
}

// Update points in the database
function updatePoints(points) {
    fetch('update_trivia_points.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `points=${points}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update points display
            document.getElementById('weekly-points').textContent = data.weekly_points;
            document.getElementById('total-points').textContent = data.total_points;
        }
    })
    .catch(error => {
        console.error('Error updating points:', error);
    });
}

// Move to the next question
document.getElementById('next-question').addEventListener('click', () => {
    currentQuestionIndex++;
    displayQuestion();
});

// Leaderboard functions
function openLeaderboard() {
    const modal = document.getElementById('leaderboardModal');
    modal.style.display = 'block';
    
    // Load weekly leaderboard by default
    loadLeaderboard('weekly');
}

function closeLeaderboard() {
    document.getElementById('leaderboardModal').style.display = 'none';
}

function showLeaderboard(type) {
    // Update active tab
    document.querySelectorAll('.leaderboard-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.currentTarget.classList.add('active');
    
    // Show selected leaderboard
    document.querySelectorAll('.leaderboard-content').forEach(content => {
        content.classList.remove('active');
    });
    
    if (type === 'weekly') {
        document.getElementById('weekly-leaderboard').classList.add('active');
        loadLeaderboard('weekly');
    } else {
        document.getElementById('all-time-leaderboard').classList.add('active');
        loadLeaderboard('all-time');
    }
}

function loadLeaderboard(type) {
    const container = document.getElementById(`${type}-leaderboard`);
    container.innerHTML = '<div class="loading">Loading...</div>';
    
    fetch(`get_leaderboard.php?type=${type}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLeaderboard(container, data.leaderboard);
            } else {
                container.innerHTML = '<p>Failed to load leaderboard.</p>';
            }
        })
        .catch(error => {
            console.error('Error loading leaderboard:', error);
            container.innerHTML = '<p>An error occurred. Please try again.</p>';
        });
}

function displayLeaderboard(container, leaderboard) {
    if (leaderboard.length === 0) {
        container.innerHTML = '<p>No data available.</p>';
        return;
    }
    
    let html = '<table class="leaderboard-table">';
    html += '<thead><tr><th>Rank</th><th>User</th><th>Points</th></tr></thead>';
    html += '<tbody>';
    
    leaderboard.forEach((entry, index) => {
        const rankClass = index < 3 ? `rank-${index + 1}` : '';
        html += `<tr class="${rankClass}">`;
        html += `<td>${index + 1}</td>`;
        html += `<td>${entry.username}</td>`;
        html += `<td>${entry.points}</td>`;
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    container.innerHTML = html;
}
function fetchNewsByCategory(category) {
    console.log("Fetching news for category: " + category);
    
    // Show loading indicator
    const newsContainer = document.querySelector('#news-container');
    if (newsContainer) {
        newsContainer.innerHTML = '<div class="loading-indicator">Loading articles for category: ' + category + '...</div>';
    }
    
    fetch('fetchnewsbycategory.php?category=' + encodeURIComponent(category), {
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
// Initialize the page
document.addEventListener('DOMContentLoaded', () => {
    // Add event listener for the leaderboard button
    const leaderboardButton = document.getElementById('leaderboard-button');
    if (leaderboardButton) {
        leaderboardButton.addEventListener('click', openLeaderboard);
    }
    
    // Close leaderboard when clicking outside
    window.addEventListener('click', (event) => {
        const modal = document.getElementById('leaderboardModal');
        if (event.target === modal) {
            closeLeaderboard();
        }
    });
});
document.addEventListener('DOMContentLoaded', function() {
    // Settings sidebar functionality
    const settingsButton = document.getElementById('settings-button');
    const settingsSidebar = document.getElementById('settings-sidebar');
    const overlay = document.getElementById('overlay');
    
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
});