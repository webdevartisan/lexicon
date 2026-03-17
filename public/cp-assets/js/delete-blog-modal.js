/**
 * Delete Blog Modal Handler
 * 
 * We fetch blog stats asynchronously when the delete button is clicked
 * to avoid loading unnecessary data on page load.
 */

document.addEventListener('DOMContentLoaded', function() {
    const deleteButton = document.querySelector('[data-modal-target="deleteBlogModal"]');
    
    if (!deleteButton) return;

    // intercept the modal open event
    deleteButton.addEventListener('click', function(e) {
        const blogId = this.getAttribute('data-blog-id');
        
        if (!blogId) {
            console.error('Blog ID not found');
            return;
        }

        // fetch stats before showing the modal
        fetchBlogStats(blogId);
    });
});

/**
 * Fetch blog deletion stats via AJAX.
 * 
 * @param {string|number} blogId
 */
async function fetchBlogStats(blogId) {
    const statsLoading = document.getElementById('statsLoading');
    const statsGrid = document.getElementById('statsGrid');
    const statsError = document.getElementById('statsError');

    // show loading state
    statsLoading.classList.remove('hidden');
    statsGrid.classList.add('hidden');
    statsError.classList.add('hidden');

    try {
        const response = await fetch(`/dashboard/api/blog/${blogId}/deletion-stats`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const json = await response.json();
        
        // destructure the nested data object for clean access
        const { data } = json;

        // update the stats in the modal
        updateModalStats(data);

        // hide loading and show stats
        statsLoading.classList.add('hidden');
        statsGrid.classList.remove('hidden');

        // reinitialize Lucide icons for the stats section
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }

    } catch (error) {
        console.error('Failed to fetch blog stats:', error);
        
        // show error state
        statsLoading.classList.add('hidden');
        statsError.classList.remove('hidden');
    }
}

/**
 * Update modal with fetched stats.
 * 
 * @param {Object} data
 */
function updateModalStats(data) {
    // update the stat numbers
    document.getElementById('postCount').textContent = data.postCount || 0;
    document.getElementById('commentCount').textContent = data.commentCount || 0;
    document.getElementById('collaboratorCount').textContent = data.collaboratorCount || 0;

    // update the deletion list text with proper pluralization
    const postCount = data.postCount || 0;
    const commentCount = data.commentCount || 0;
    const collaboratorCount = data.collaboratorCount || 0;

    document.getElementById('postCountText').textContent = 
        `All ${postCount} ${postCount === 1 ? 'post' : 'posts'}`;
    
    document.getElementById('commentCountText').textContent = 
        `All ${commentCount} ${commentCount === 1 ? 'comment' : 'comments'}`;
    
    document.getElementById('collaboratorCountText').textContent = 
        `Collaborator access (${collaboratorCount} ${collaboratorCount === 1 ? 'user' : 'users'} will lose access)`;
}
