document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('title');
    const descriptionInput = document.getElementById('description');
    const imageInput = document.getElementById('image');
    const referenceLinksInput = document.getElementById('reference_links');

    const previewTitle = document.getElementById('preview-title');
    const previewDescription = document.getElementById('preview-description');
    const previewImage = document.getElementById('preview-image');
    const previewLinksList = document.getElementById('preview-links-list');
    
    // Initialize preview scroll position
    const articlePreview = document.querySelector('.article-preview');
    let lastScrollPosition = window.pageYOffset;
    
    // Handle preview scrolling
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        if (currentScroll > lastScrollPosition) {
            articlePreview.style.transform = 'translateY(-10px)';
        } else {
            articlePreview.style.transform = 'translateY(0)';
        }
        lastScrollPosition = currentScroll;
    });

    // Update title preview with animation
    titleInput.addEventListener('input', function() {
        previewTitle.style.opacity = '0';
        setTimeout(() => {
            previewTitle.textContent = this.value || 'Your Title Will Appear Here';
            previewTitle.style.opacity = '1';
        }, 150);
    });

    // Update description preview with animation
    descriptionInput.addEventListener('input', function() {
        previewDescription.style.opacity = '0';
        setTimeout(() => {
            previewDescription.textContent = this.value || 'Your article content will appear here as you type...';
            previewDescription.style.opacity = '1';
        }, 150);
    });

    // Handle image preview with loading state
    imageInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            previewImage.innerHTML = '<div class="loading-spinner"></div>';
            const reader = new FileReader();
            reader.onload = function(e) {
                setTimeout(() => {
                    previewImage.style.opacity = '0';
                    setTimeout(() => {
                        previewImage.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                        previewImage.style.opacity = '1';
                    }, 150);
                }, 500);
            };
            reader.readAsDataURL(file);
        } else {
            previewImage.innerHTML = '<span>Image Preview</span>';
        }
    });

    // Update reference links preview with validation and animation
    referenceLinksInput.addEventListener('input', function() {
        const links = this.value.split('\n').filter(link => link.trim());
        previewLinksList.style.opacity = '0';
        setTimeout(() => {
            if (links.length > 0) {
                previewLinksList.innerHTML = links
                    .map(link => {
                        try {
                            new URL(link);
                            return `<li><a href="${link}" target="_blank" class="valid-link">${link}</a></li>`;
                        } catch {
                            return `<li><span class="invalid-link">${link}</span></li>`;
                        }
                    })
                    .join('');
            } else {
                previewLinksList.innerHTML = '<li class="preview-placeholder">Your reference links will appear here...</li>';
            }
            previewLinksList.style.opacity = '1';
        }, 150);
    });

    // Add form validation
    const form = document.querySelector('.submission-form');
    form.addEventListener('submit', function(e) {
        const title = titleInput.value.trim();
        const description = descriptionInput.value.trim();

        if (!title || !description) {
            e.preventDefault();
            alert('Please fill in all required fields.');
        }
    });
});
