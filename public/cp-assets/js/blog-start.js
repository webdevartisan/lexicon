/**
 * Start your blog, step 1: shows the address the blog will get while the
 * name is typed, and opens the "Change" field when the address can't be used.
 *
 * Moving between the steps animates through the cross-document View
 * Transitions opt-in in blog-start.css, so no navigation code lives here.
 */
(function () {
  var script = document.currentScript;

  document.addEventListener('DOMContentLoaded', function () {
    var name = document.getElementById('name');
    var slug = document.getElementById('slug');
    var preview = document.querySelector('[data-address]');
    var previewSlug = document.querySelector('[data-address-slug]');
    var change = document.querySelector('[data-address-change]');
    if (!name || !slug || !preview || !window.SlugField) return;

    window.SlugField.bind({ source: 'name', target: 'slug', min: 2, max: 50, checkUrl: script.dataset.slugCheck });

    // Runs after SlugField's own listeners, so the slug field already holds the new value
    var refresh = function () {
      previewSlug.textContent = slug.value;
      preview.hidden = slug.value === '';
    };
    name.addEventListener('input', refresh);
    slug.addEventListener('input', refresh);
    refresh();

    // Pressing Continue with an address that can't be used opens "Change", so the
    // reason is visible. Opening it while the name is still being typed would jump.
    slug.addEventListener('invalid', function () {
      change.open = true;
    });
  });
})();
