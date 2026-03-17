function updateCharCount(fieldId, limit) {
  const field = document.getElementById(fieldId);
  const counter = document.getElementById(fieldId + '_count');
  const length = field.value.length;
  
  counter.textContent = `${length}/${limit}`;
  
  // Color coding: green (good), amber (approaching), red (over)
  if (length === 0) {
    counter.className = 'text-xs tabular-nums text-slate-400 dark:text-zink-400';
  } else if (length <= limit) {
    counter.className = 'text-xs tabular-nums text-emerald-600 dark:text-emerald-400 font-medium';
  } else if (length <= limit + 10) {
    counter.className = 'text-xs tabular-nums text-amber-600 dark:text-amber-400 font-medium';
  } else {
    counter.className = 'text-xs tabular-nums text-red-600 dark:text-red-400 font-medium';
  }
  
  // Update SEO preview
  if (fieldId === 'meta_title') {
    const preview = document.getElementById('seo_preview_title');
    if (preview) preview.textContent = field.value || document.querySelector('[name="title"]')?.value || 'Your post title';
  } else if (fieldId === 'meta_description') {
    const preview = document.getElementById('seo_preview_desc');
    if (preview) preview.textContent = field.value || document.querySelector('[name="excerpt"]')?.value || 'Your description will appear here...';
  }
}

function updateSocialPreview() {
  const titleField = document.getElementById('og_title');
  const descField = document.getElementById('og_description');
  const imageField = document.getElementById('og_image');
  
  const titlePreview = document.getElementById('social_preview_title');
  const descPreview = document.getElementById('social_preview_desc');
  const imagePreview = document.getElementById('social_preview_image');
  
  // Update title
  if (titlePreview) {
    titlePreview.textContent = titleField.value || document.querySelector('[name="title"]')?.value || 'Your post title';
  }
  
  // Update description
  if (descPreview) {
    descPreview.textContent = descField.value || document.querySelector('[name="excerpt"]')?.value || 'Your description will appear here...';
  }
  
  // Update image (if provided)
  if (imagePreview && imageField.value) {
    imagePreview.style.backgroundImage = `url('${imageField.value}')`;
    imagePreview.style.backgroundSize = 'cover';
    imagePreview.style.backgroundPosition = 'center';
    imagePreview.innerHTML = ''; // Remove placeholder icon
  }
}

function confirmDelete() {
  if (confirm('Are you sure you want to move this post to trash? You can restore it later.')) {
    const form = document.querySelector('form');
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'action';
    input.value = 'trash';
    form.appendChild(input);
    form.submit();
  }
}
window.confirmDelete = confirmDelete;

// Initialize character counters on page load
document.addEventListener('DOMContentLoaded', () => {
  updateCharCount('meta_title', 60);
  updateCharCount('meta_description', 160);
  updateCharCount('og_title', 60);
  updateCharCount('og_description', 65);
  updateSocialPreview();
});

// Handle all button clicks with data-onclick
document.addEventListener('click', (e) => {
  const button = e.target.closest('[data-onclick]');

  if (!button) return;
  
  const functionName = button.dataset.onclick;
  const params = button.dataset.params ? JSON.parse(button.dataset.params) : null;

  // Call the function if it exists
  if (typeof window[functionName] === 'function') {
    window[functionName](params, button, e);
  }
});