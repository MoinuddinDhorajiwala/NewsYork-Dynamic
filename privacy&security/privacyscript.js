// Selecting the sidebar and overlay elements
const sidebar = document.getElementById('sidebar');
const toggleSidebar = document.getElementById('toggle-sidebar');
const overlay = document.getElementById('overlay');

// Toggle sidebar visibility when clicking on the toggle button
toggleSidebar.addEventListener('click', (event) => {
    event.stopPropagation();
    sidebar.style.left = '0';
    overlay.style.display = 'block';
});

// Close sidebar when clicking on the overlay
overlay.addEventListener('click', () => {
    sidebar.style.left = '-220px';
    overlay.style.display = 'none';
});

// Close sidebar when clicking anywhere outside the sidebar and toggle button
document.addEventListener('click', (event) => {
    const isClickInsideSidebar = sidebar.contains(event.target);
    const isClickInsideToggle = toggleSidebar.contains(event.target);

    if (!isClickInsideSidebar && !isClickInsideToggle) {
        sidebar.style.left = '-220px';
        overlay.style.display = 'none';
    }
});

// Prevent clicks inside the sidebar from closing it
sidebar.addEventListener('click', (event) => {
    event.stopPropagation();
});
