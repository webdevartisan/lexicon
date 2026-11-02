/**
 * Start your blog, step 1: shows the address the blog will get while the
 * name is typed, and opens the "Change" field when the address can't be used.
 *
 * The steps open in the site modal as well as on their own page. In the modal
 * this script runs the first time step 1 shows up, and hears panel:loaded after that.
 */
(function () {
  var TONES = {
    error: { border: 'is-error', text: 'acct-error' },
    ok: { border: 'is-ok', text: 'acct-hint start-ok' },
    notice: { border: 'is-notice', text: 'acct-hint start-notice' },
    pending: { border: '', text: 'acct-hint' },
  };

  function init(root) {
    var form = root.querySelector('[data-blog-address]');
    if (!form || form.hasAttribute('data-ready') || !window.SlugField) return;
    form.setAttribute('data-ready', '');

    var name = form.querySelector('#name');
    var slug = form.querySelector('#slug');
    var box = form.querySelector('[data-address-box]');
    var preview = form.querySelector('[data-address]');
    var previewSlug = form.querySelector('[data-address-slug]');
    var change = form.querySelector('[data-address-change]');

    window.SlugField.bind({
      root: form,
      source: 'name',
      target: 'slug',
      min: 2,
      max: 50,
      checkUrl: form.getAttribute('data-blog-address'),
      tones: TONES,
      feedbackClass: 'start-feedback',
    });

    // Runs after SlugField's own listeners, so the slug field already holds the new value.
    // An open "Change" keeps the box, so clearing the address there doesn't hide the field being typed in.
    var refresh = function () {
      previewSlug.textContent = slug.value;
      preview.hidden = slug.value === '';
      box.hidden = slug.value === '' && !change.open;
    };
    name.addEventListener('input', refresh);
    slug.addEventListener('input', refresh);
    refresh();

    // Pressing Continue with an address that can't be used opens "Change", so the
    // reason is visible. Opening it while the name is still being typed would jump.
    slug.addEventListener('invalid', function () {
      change.open = true;
      refresh();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }

  document.addEventListener('panel:loaded', function (e) {
    init(e.detail);
  });
})();
