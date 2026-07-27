(function () {
  var bar = document.querySelector('.post-engagement');
  if (!bar) return;

  var loggedIn = bar.getAttribute('data-auth') === '1';
  var loginUrl = bar.getAttribute('data-login');
  var tokenInput = document.querySelector('#engage-token input[name="_token"]');
  var popover = bar.querySelector('[data-share-popover]');
  var shareBtn = bar.querySelector('[data-share-toggle]');

  bar.addEventListener('click', function (ev) {
    var btn = ev.target.closest('[data-engage]');
    if (!btn) return;

    if (!loggedIn) {
      if (window.LexiconAuth) {
        // Finish the tap after auth: refresh the CSRF token, flip the bar
        // to logged-in, and re-fire the same button
        window.LexiconAuth.open({
          reason: btn.getAttribute('data-engage'),
          onSuccess: function () {
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
      } else {
        window.location = loginUrl + '?return_to=' + encodeURIComponent(location.pathname + location.search);
      }
      return;
    }
    if (btn.dataset.busy === '1') return;
    btn.dataset.busy = '1';

    var body = new FormData();
    body.append('_token', tokenInput ? tokenInput.value : '');

    fetch(btn.getAttribute('data-url'), { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var data = json.data || json;
        var active = !!data.active;
        btn.classList.toggle('is-active', active);
        btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        btn.querySelector('svg').setAttribute('fill', active ? 'currentColor' : 'none');

        if (btn.getAttribute('data-engage') === 'like') {
          btn.querySelector('[data-count]').textContent = data.count;
        } else {
          btn.querySelector('span:last-child').textContent = active ? 'Saved' : 'Save';
        }
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

    document.addEventListener('click', function (ev) {
      if (!popover.hasAttribute('hidden') && !bar.contains(ev.target)) {
        popover.setAttribute('hidden', '');
        shareBtn.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && !popover.hasAttribute('hidden')) {
        popover.setAttribute('hidden', '');
        shareBtn.setAttribute('aria-expanded', 'false');
      }
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
})();
