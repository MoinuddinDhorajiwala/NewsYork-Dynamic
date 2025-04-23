document.addEventListener('DOMContentLoaded', function() {
    // Get user ID from hidden input field
    const userId = document.getElementById('userId').value;

    // Add click event listeners to all vote buttons
    document.querySelectorAll('.vote-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Check if user is logged in
            if (!userId) {
                alert('Please log in to vote');
                return;
            }

            const submissionId = this.dataset.submission;
            const voteType = this.dataset.type;
            const voteContainer = this.closest('.votes');
            const voteCountElement = voteContainer.querySelector('.vote-count');

            // Send vote to server
            fetch('../submit_submission_vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `submission_id=${submissionId}&vote_type=${voteType}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update vote count
                    voteCountElement.textContent = data.total_votes;

                    // Update button states
                    const upvoteBtn = voteContainer.querySelector('.upvote');
                    const downvoteBtn = voteContainer.querySelector('.downvote');

                    if (data.action === 'removed') {
                        upvoteBtn.classList.remove('active');
                        downvoteBtn.classList.remove('active');
                    } else {
                        if (voteType === 'upvote') {
                            upvoteBtn.classList.add('active');
                            downvoteBtn.classList.remove('active');
                        } else {
                            upvoteBtn.classList.remove('active');
                           downvoteBtn.classList.add('active');
                        }
                    }
                } else {
                    alert(data.message || 'Error processing vote');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing vote');
            });
        });
    });
});