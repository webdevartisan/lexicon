function updateCharCount(fieldId, limit) {
  const field = document.getElementById(fieldId);
  const counter = document.getElementById(fieldId + '_count');
  if (!field || !counter) return;

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

  if (fieldId === 'meta_title' || fieldId === 'meta_description') {
    updateSeoPreview();
  } else {
    updateSocialPreview();
  }
}
window.updateCharCount = updateCharCount;

function fieldValue(id) {
  const el = document.getElementById(id);
  return el ? el.value.trim() : '';
}

function postTitle() {
  const el = document.querySelector('[name="title"]');
  return el ? el.value.trim() : '';
}

function postExcerpt() {
  const el = document.querySelector('[name="excerpt"]');
  return el ? el.value.trim() : '';
}

function updateSeoPreview() {
  const title = document.getElementById('seo_preview_title');
  const desc = document.getElementById('seo_preview_desc');

  if (title) {
    title.textContent = fieldValue('meta_title') || postTitle() || 'Your post title will appear here';
  }
  if (desc) {
    desc.textContent = fieldValue('meta_description') || postExcerpt()
      || 'Your meta description or excerpt will appear here in search results...';
  }
}

/**
 * Repaint every platform preview from the shared og: fields.
 *
 * Only X reads its own overrides; the rest are handed identical content
 * because that is all the protocol allows. What differs between panels is
 * purely how each service crops and truncates, which the markup encodes.
 */
function updateSocialPreview() {
  const card = document.querySelector('[data-social-card]');
  if (!card) return;

  const ogTitle = fieldValue('og_title') || postTitle() || 'Your post title will appear here';
  const ogDesc = fieldValue('og_description') || postExcerpt()
    || 'Your description will appear here when shared on social media...';
  const ogImage = fieldValue('og_image') || featuredImageUrl();

  const xPanel = card.querySelector('[data-social-panel="x"]');

  card.querySelectorAll('[data-social-panel]').forEach((panel) => {
    const isX = panel === xPanel;
    const title = isX ? (fieldValue('twitter_title') || ogTitle) : ogTitle;
    const desc = isX ? (fieldValue('twitter_description') || ogDesc) : ogDesc;
    const image = isX ? (fieldValue('twitter_image') || ogImage) : ogImage;

    panel.querySelectorAll('[data-social-title]').forEach((el) => { el.textContent = title; });
    panel.querySelectorAll('[data-social-desc]').forEach((el) => { el.textContent = desc; });
    panel.querySelectorAll('[data-social-image]').forEach((el) => {
      if (image) {
        // Quotes and parens would otherwise break out of the url() literal.
        el.style.backgroundImage = `url("${image.replace(/["'()\\]/g, '')}")`;
        el.querySelectorAll('i').forEach((icon) => { icon.style.display = 'none'; });
      } else {
        el.style.backgroundImage = '';
        el.querySelectorAll('i').forEach((icon) => { icon.style.display = ''; });
      }
    });
  });

  syncXCardType();
}
window.updateSocialPreview = updateSocialPreview;

/**
 * The featured image is the fallback whenever no explicit og:image is set,
 * mirroring the fallback BlogController applies when it builds the meta tags.
 *
 * Two places to look: a file dropped in this session previews as a Dropzone
 * thumbnail, while an already-saved image renders in the current-image block.
 */
function featuredImageUrl() {
  const dropped = document.querySelector('[data-dz-thumbnail]');
  if (dropped && dropped.getAttribute('src')) {
    return dropped.getAttribute('src');
  }

  const saved = document.querySelector('.current-image-section img[src]');
  return saved ? saved.getAttribute('src') || '' : '';
}

/** Summary and large-image cards are different shapes, so we swap markup. */
function syncXCardType() {
  const select = document.getElementById('twitter_card_type');
  const panel = document.querySelector('[data-social-panel="x"]');
  if (!select || !panel) return;

  panel.querySelectorAll('[data-x-card]').forEach((el) => {
    el.hidden = el.dataset.xCard !== select.value;
  });
}

function initSocialTabs() {
  const card = document.querySelector('[data-social-card]');
  if (!card) return;

  card.querySelectorAll('[data-social-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
      const target = tab.dataset.socialTab;

      card.querySelectorAll('[data-social-tab]').forEach((other) => {
        other.setAttribute('aria-selected', String(other === tab));
      });
      card.querySelectorAll('[data-social-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.socialPanel !== target;
      });

      syncXCardType();
    });
  });
}

/**
 * Setting a future publish date turns publishing into scheduling.
 *
 * The backend decides this the same way, from the date rather than the button,
 * so the label is only there to keep the two from looking like they disagree.
 */
function initScheduleAwareness() {
  const dateField = document.getElementById('published_at');
  const primary = document.querySelector('[data-post-primary-action]');
  const hint = document.querySelector('[data-schedule-hint]');
  const clearBtn = document.querySelector('[data-clear-schedule]');
  if (!dateField) return;

  const label = primary ? primary.querySelector('[data-primary-label]') : null;
  const icon = primary ? primary.querySelector('i') : null;
  const publishLabel = primary ? primary.dataset.publishLabel : '';
  const publishIntent = primary ? primary.getAttribute('value') : '';

  function refresh() {
    const hasDate = dateField.value.trim() !== '';

    if (clearBtn) clearBtn.classList.toggle('hidden', !hasDate);

    if (hint) {
      hint.textContent = hasDate
        ? 'Publishing is held until this time. A sweep runs every minute, so it may go live a moment late.'
        : 'Leave empty to go live as soon as you publish.';
    }

    // Only relabel the buttons that actually publish; "Save draft" and
    // "Submit for review" mean the same thing whatever the date says.
    if (!primary || !label) return;
    if (publishIntent !== 'publish' && publishIntent !== 'schedule') return;

    label.textContent = hasDate ? 'Schedule' : publishLabel;
    if (icon) icon.setAttribute('data-lucide', hasDate ? 'calendar-clock' : 'send');
    if (window.lucide) window.lucide.createIcons();
  }

  dateField.addEventListener('change', refresh);
  dateField.addEventListener('input', refresh);

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      // flatpickr owns the input, so clear through it when it is attached.
      if (dateField._flatpickr) {
        dateField._flatpickr.clear();
      } else {
        dateField.value = '';
      }
      refresh();
    });
  }

  refresh();
}

/** Close the overflow menu on outside click and on Escape. */
function initActionMenu() {
  const menu = document.querySelector('[data-post-menu]');
  if (!menu) return;

  document.addEventListener('click', (e) => {
    if (!menu.contains(e.target)) menu.open = false;
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') menu.open = false;
  });
}

function initCanonicalFill() {
  const button = document.querySelector('[data-canonical-fill]');
  const field = document.getElementById('canonical_url');
  if (!button || !field) return;

  button.addEventListener('click', () => {
    field.value = button.dataset.postUrl || '';
  });
}

document.addEventListener('DOMContentLoaded', () => {
  updateCharCount('meta_title', 60);
  updateCharCount('meta_description', 160);
  updateCharCount('og_title', 60);
  updateCharCount('og_description', 65);

  ['title', 'excerpt'].forEach((name) => {
    const field = document.querySelector(`[name="${name}"]`);
    if (field) {
      field.addEventListener('input', () => {
        updateSeoPreview();
        updateSocialPreview();
      });
    }
  });

  ['og_image', 'og_image_alt', 'twitter_title', 'twitter_description', 'twitter_image'].forEach((id) => {
    const field = document.getElementById(id);
    if (field) field.addEventListener('input', updateSocialPreview);
  });

  const cardType = document.getElementById('twitter_card_type');
  if (cardType) cardType.addEventListener('change', syncXCardType);

  initSocialTabs();
  initScheduleAwareness();
  initActionMenu();
  initCanonicalFill();

  updateSeoPreview();
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
