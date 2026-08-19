/**
 * Post action bar: vote, bookmark, share, report.
 *
 * Mirrors the comment thread's controls so a reader learns them once. Voting
 * and reporting need fetch; a failure leaves the bar as rendered rather than
 * in a half-updated state.
 */
(function () {
  var bar = document.querySelector('.post-engagement');
  if (!bar) return;

  var loggedIn = bar.getAttribute('data-auth') === '1';
  var loginUrl = bar.getAttribute('data-login');
  var tokenInput = document.querySelector('#engage-token input[name="_token"]');
  var popover = bar.querySelector('[data-share-popover]');
  var shareBtn = bar.querySelector('[data-share-toggle]');
  var menu = bar.querySelector('[data-engage-menu-list]');
  var menuBtn = bar.querySelector('[data-engage-menu-toggle]');

  var REPORT_REASONS = ['spam', 'harassment', 'hate', 'misinformation', 'other'];

  function post(url, fields) {
    var body = new FormData();
    body.append('_token', tokenInput ? tokenInput.value : '');

    Object.keys(fields || {}).forEach(function (key) {
      body.append(key, fields[key]);
    });

    // Accept tells the CSRF middleware to answer with JSON instead of
    // bouncing this fetch to an HTML page it cannot read.
    return fetch(url, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (r) { return r.json(); });
  }

  /** Send the viewer through auth, then re-fire the control they pressed. */
  function authenticateThen(btn) {
    if (!window.LexiconAuth) {
      window.location = loginUrl + '?return_to=' + encodeURIComponent(location.pathname + location.search);
      return;
    }

    window.LexiconAuth.open({
      reason: btn.getAttribute('data-engage') || 'report',
      onSuccess: function () {
        // The session changed, so the token rendered with the page is stale.
        fetch('/csrf-token', { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (json) {
            if (tokenInput && json.token) tokenInput.value = json.token;
            loggedIn = true;
            bar.setAttribute('data-auth', '1');
            btn.click();
          })
          .catch(function () { window.location.reload(); });
      }
    });
  }

  /** Repaint both thumbs from one response; only the up vote carries a count. */
  function paintVote(data) {
    bar.querySelectorAll('[data-engage="up"], [data-engage="down"]').forEach(function (button) {
      var kind = button.getAttribute('data-engage');
      var active = (kind === 'up' && data.mine === 1) || (kind === 'down' && data.mine === -1);
      var count = button.querySelector('[data-count]');

      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
      button.querySelector('svg').setAttribute('fill', active ? 'currentColor' : 'none');

      if (count) count.textContent = data.up;
    });
  }

  function closeMenu() {
    if (!menu) return;
    menu.setAttribute('hidden', '');
    if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
  }

  function closeShare() {
    if (!popover || popover.hasAttribute('hidden')) return;
    popover.setAttribute('hidden', '');
    if (shareBtn) shareBtn.setAttribute('aria-expanded', 'false');
  }

  function reportPost(button) {
    var reason = window.prompt('Why are you reporting this post?', 'spam');

    // Cancelled, or typed something we do not recognise: fall back to "other"
    // rather than dropping a report the reader meant to file.
    if (reason === null) return;
    reason = REPORT_REASONS.indexOf(reason.trim().toLowerCase()) === -1
      ? 'other'
      : reason.trim().toLowerCase();

    closeMenu();

    post(button.getAttribute('data-url'), { reason: reason })
      .then(function (json) {
        window.alert(json.error || (json.data && json.data.message) || 'Thanks for the report.');
      })
      .catch(function () {
        window.alert('That report could not be sent. Please try again.');
      });
  }

  bar.addEventListener('click', function (ev) {
    if (ev.target.closest('[data-engage-menu-toggle]')) {
      var open = menu.hasAttribute('hidden');
      menu.toggleAttribute('hidden', !open);
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      return;
    }

    var report = ev.target.closest('[data-report-post]');
    if (report) {
      if (loggedIn) { reportPost(report); } else { authenticateThen(report); }
      return;
    }

    var btn = ev.target.closest('[data-engage]');
    if (!btn) return;

    if (!loggedIn) {
      authenticateThen(btn);
      return;
    }

    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    var kind = btn.getAttribute('data-engage');
    var isVote = kind === 'up' || kind === 'down';

    post(btn.getAttribute('data-url'), isVote ? { direction: kind } : {})
      .then(function (json) {
        var data = json.data || json;

        if (isVote) {
          paintVote(data);
          return;
        }

        btn.classList.toggle('is-active', !!data.active);
        btn.setAttribute('aria-pressed', data.active ? 'true' : 'false');
        btn.querySelector('svg').setAttribute('fill', data.active ? 'currentColor' : 'none');
        btn.querySelector('span:last-child').textContent = data.active ? 'Saved' : 'Save';
      })
      .catch(function () { /* leave state as rendered; next click retries */ })
      .finally(function () { btn.dataset.busy = '0'; });
  });

  if (shareBtn && popover) {
    shareBtn.addEventListener('click', function () {
      var open = !popover.hasAttribute('hidden');
      popover.toggleAttribute('hidden', open);
      shareBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
    });

    var copyBtn = popover.querySelector('[data-copy-link]');
    if (copyBtn) {
      copyBtn.addEventListener('click', function () {
        var link = copyBtn.getAttribute('data-link');
        var label = copyBtn.querySelector('[data-copy-label]');
        var done = function () {
          label.textContent = 'Copied!';
          setTimeout(function () { label.textContent = 'Copy link'; }, 1500);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(link).then(done);
        } else {
          var tmp = document.createElement('textarea');
          tmp.value = link;
          document.body.appendChild(tmp);
          tmp.select();
          document.execCommand('copy');
          document.body.removeChild(tmp);
          done();
        }
      });
    }
  }

  document.addEventListener('click', function (ev) {
    if (!bar.contains(ev.target)) {
      closeMenu();
      closeShare();
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;
    closeMenu();
    closeShare();
  });
})();
