/**
 * Comment thread behaviour: reply boxes, collapsible replies, batched reply
 * reveal, voting, the per-comment overflow menu, reporting, and landing on a
 * deep-linked comment at any depth.
 *
 * Mostly progressive. Reply, remove and pin are real form posts that work with
 * scripting off; voting and reporting need fetch, and degrade to doing nothing
 * rather than to a broken control.
 */
(function () {
  'use strict';

  var root = document.querySelector('.comment-thread-root');
  if (!root) return;

  var loggedIn = root.getAttribute('data-auth') === '1';
  var loginUrl = root.getAttribute('data-login');
  var tokenInput = root.querySelector('.comment-token input[name="_token"]');

  var REPORT_REASONS = ['spam', 'harassment', 'hate', 'misinformation', 'other'];

  function token() {
    return tokenInput ? tokenInput.value : '';
  }

  function post(url, fields) {
    var body = new FormData();
    body.append('_token', token());

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

  // -------------------------------------------------------------- composer

  /** Grow a textarea to fit what has been typed, with no scrollbar. */
  function autoGrow(field) {
    field.style.height = 'auto';
    field.style.height = field.scrollHeight + 'px';
  }

  /**
   * Collapse the composer to a single line until somebody wants it.
   *
   * Done here rather than in the markup so the form ships expanded and stays
   * usable with scripting off; collapsing is the enhancement, not the baseline.
   */
  var composer = root.querySelector('[data-comment-composer]');

  if (composer) {
    var composerField = composer.querySelector('textarea');

    composer.classList.add('is-collapsed');

    composerField.addEventListener('focus', function () {
      composer.classList.remove('is-collapsed');
    });

    composerField.addEventListener('input', function () { autoGrow(composerField); });

    composer.addEventListener('click', function (ev) {
      if (!ev.target.closest('[data-composer-cancel]')) return;

      composerField.value = '';
      composerField.style.height = '';
      composer.classList.add('is-collapsed');
      composerField.blur();
    });
  }

  // ---------------------------------------------------------------- replies

  function closeReplyForms() {
    root.querySelectorAll('.reply-form').forEach(function (form) {
      form.setAttribute('hidden', '');
    });
  }

  function openReplyForm(id) {
    var form = document.getElementById('reply-form-' + id);
    if (!form) return;

    var wasHidden = form.hasAttribute('hidden');
    closeReplyForms();

    if (wasHidden) {
      form.removeAttribute('hidden');

      var field = form.querySelector('textarea');
      field.focus();
      field.addEventListener('input', function () { autoGrow(field); });
    }
  }

  /** Expand or collapse one comment's replies and relabel its toggle. */
  function setThreadExpanded(toggle, expanded) {
    var list = document.getElementById('replies-' + toggle.getAttribute('data-collapse-toggle'));
    if (!list) return;

    list.toggleAttribute('hidden', !expanded);
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');

    var label = toggle.querySelector('[data-collapse-label]');
    var text = toggle.getAttribute(expanded ? 'data-label-hide' : 'data-label-show');

    if (label && text) label.textContent = text;
  }

  /** Reveal the replies held back past the first batch. */
  function showMoreReplies(id) {
    root.querySelectorAll('[data-batched="' + id + '"]').forEach(function (item) {
      item.removeAttribute('hidden');
      item.removeAttribute('data-batched');
    });

    var row = root.querySelector('[data-more-row="' + id + '"]');
    if (row) row.remove();
  }

  // -------------------------------------------------------------- menus

  function closeMenus(except) {
    root.querySelectorAll('[data-menu-list]').forEach(function (list) {
      if (list === except) return;

      list.setAttribute('hidden', '');
      var button = list.parentElement.querySelector('[data-menu-toggle]');
      if (button) button.setAttribute('aria-expanded', 'false');
    });
  }

  function toggleMenu(button) {
    var list = button.parentElement.querySelector('[data-menu-list]');
    if (!list) return;

    var open = list.hasAttribute('hidden');
    closeMenus(list);
    list.toggleAttribute('hidden', !open);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function copyCommentLink(button) {
    var url = location.origin + location.pathname + location.search + button.getAttribute('data-anchor');
    var restore = button.textContent;
    var done = function () {
      button.textContent = 'Link copied';
      setTimeout(function () { button.textContent = restore; }, 1500);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done);
      return;
    }

    var tmp = document.createElement('textarea');
    tmp.value = url;
    document.body.appendChild(tmp);
    tmp.select();
    document.execCommand('copy');
    document.body.removeChild(tmp);
    done();
  }

  function reportComment(button) {
    var reason = window.prompt(
      'Why are you reporting this comment?\n' + REPORT_REASONS.join(', '),
      'spam'
    );

    // Cancelled, or typed something we do not recognise: fall back to "other"
    // rather than dropping a report the reader meant to file.
    if (reason === null) return;
    reason = REPORT_REASONS.indexOf(reason.trim().toLowerCase()) === -1
      ? 'other'
      : reason.trim().toLowerCase();

    closeMenus(null);

    post('/comments/' + button.getAttribute('data-comment-id') + '/report', { reason: reason })
      .then(function (json) {
        window.alert(json.error || (json.data && json.data.message) || 'Thanks for the report.');
      })
      .catch(function () {
        window.alert('That report could not be sent. Please try again.');
      });
  }

  // ------------------------------------------------------------------ votes

  /** Send the viewer through auth, then re-fire the button they pressed. */
  function authenticateThen(button, reason) {
    if (!window.LexiconAuth) {
      window.location = loginUrl + '?return_to=' + encodeURIComponent(location.pathname + location.search);
      return;
    }

    window.LexiconAuth.open({
      reason: reason,
      onSuccess: function () {
        // The session changed, so the token rendered with the page is stale.
        fetch('/csrf-token', { credentials: 'same-origin' })
          .then(function (r) { return r.json(); })
          .then(function (json) {
            if (tokenInput && json.token) tokenInput.value = json.token;
            loggedIn = true;
            root.setAttribute('data-auth', '1');
            button.click();
          })
          .catch(function () { window.location.reload(); });
      }
    });
  }

  function paintVote(group, data) {
    group.querySelectorAll('.comment-vote').forEach(function (button) {
      var direction = button.getAttribute('data-vote');
      var active = (direction === 'up' && data.mine === 1) || (direction === 'down' && data.mine === -1);
      var count = button.querySelector('[data-vote-count]');

      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
      button.querySelector('svg').setAttribute('fill', active ? 'currentColor' : 'none');

      // Only the up vote carries a tally, and a zero reads as noise next to a
      // thumb, so it is left blank.
      if (count) count.textContent = data.up > 0 ? data.up.toLocaleString() : '';
    });
  }

  function castVote(button) {
    var group = button.closest('.comment-votes');

    if (group.dataset.busy === '1') return;
    group.dataset.busy = '1';

    post('/comments/' + group.getAttribute('data-comment-id') + '/vote', {
      direction: button.getAttribute('data-vote')
    })
      .then(function (json) { paintVote(group, json.data || json); })
      .catch(function () { /* leave the counts as rendered; the next click retries */ })
      .finally(function () { group.dataset.busy = '0'; });
  }

  // --------------------------------------------------------------- listeners

  root.addEventListener('click', function (ev) {
    var replyToggle = ev.target.closest('[data-reply-toggle]');
    if (replyToggle) {
      openReplyForm(replyToggle.getAttribute('data-reply-toggle'));
      return;
    }

    if (ev.target.closest('[data-reply-cancel]')) {
      closeReplyForms();
      return;
    }

    var collapse = ev.target.closest('[data-collapse-toggle]');
    if (collapse) {
      setThreadExpanded(collapse, collapse.getAttribute('aria-expanded') !== 'true');
      return;
    }

    var more = ev.target.closest('[data-show-more]');
    if (more) {
      showMoreReplies(more.getAttribute('data-show-more'));
      return;
    }

    var menuToggle = ev.target.closest('[data-menu-toggle]');
    if (menuToggle) {
      toggleMenu(menuToggle);
      return;
    }

    var copy = ev.target.closest('[data-copy-comment]');
    if (copy) {
      copyCommentLink(copy);
      return;
    }

    var report = ev.target.closest('[data-report-comment]');
    if (report) {
      if (loggedIn) {
        reportComment(report);
      } else {
        authenticateThen(report, 'report');
      }
      return;
    }

    var vote = ev.target.closest('.comment-vote');
    if (vote) {
      if (loggedIn) {
        castVote(vote);
      } else {
        authenticateThen(vote, 'vote');
      }
    }
  });

  document.addEventListener('click', function (ev) {
    if (!ev.target.closest('[data-comment-menu]')) closeMenus(null);
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape') closeMenus(null);
  });

  root.addEventListener('submit', function (ev) {
    var form = ev.target.closest('[data-confirm]');

    if (form && !window.confirm(form.getAttribute('data-confirm'))) {
      ev.preventDefault();
    }
  });

  // ------------------------------------------------------------ deep linking

  /**
   * Make #comment-<id> land on the right comment, however deep it sits.
   *
   * A target can be buried under several collapsed reply lists and behind a
   * "show more" batch at once, so every ancestor gets opened on the way up
   * before anything is measured.
   */
  var HIGHLIGHT_MS = 3000;
  var highlightTimer = null;

  function prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function openAncestorsOf(el) {
    var node = el;

    while (node && node !== root) {
      if (node.nodeType === 1) {
        // A target can be batched away and collapsed inside several lists at
        // once, so every ancestor is opened on the way up before anything is
        // measured.
        if (node.hasAttribute('data-batched')) {
          showMoreReplies(node.getAttribute('data-batched'));
        }

        if (node.classList.contains('comment-replies')) {
          var toggle = root.querySelector('[data-collapse-toggle="' + node.id.replace('replies-', '') + '"]');
          if (toggle) setThreadExpanded(toggle, true);
        }

        // Top-level comments start transparent until the reveal observer fires
        if (node.classList.contains('reveal')) {
          node.classList.add('is-in');
        }
      }

      node = node.parentElement;
    }
  }

  function highlight(el) {
    if (highlightTimer) window.clearTimeout(highlightTimer);

    el.classList.remove('comment-target');
    // Force a reflow so re-targeting the same comment restarts the animation
    void el.offsetWidth;
    el.classList.add('comment-target');

    highlightTimer = window.setTimeout(function () {
      el.classList.remove('comment-target');
      highlightTimer = null;
    }, HIGHLIGHT_MS);
  }

  /**
   * Put the target in the middle of the viewport.
   *
   * Not a fixed header offset: every theme has a different masthead and a
   * short comment landing just under it still reads as "stuck to the top".
   * Centring needs no measurement and puts the surrounding thread in view.
   */
  function scrollToTarget(el, smooth) {
    var box = el.getBoundingClientRect();
    var top = box.top + window.pageYOffset - Math.max(0, (window.innerHeight - box.height) / 2);

    window.scrollTo({ top: top < 0 ? 0 : top, behavior: smooth ? 'smooth' : 'auto' });
  }

  function goToHash(smooth, withHighlight) {
    var match = /^#comment-(\d+)$/.exec(window.location.hash);
    var el = match ? document.getElementById('comment-' + match[1]) : null;

    // Comment may be deleted, unapproved, or on another post
    if (!el) return;

    openAncestorsOf(el);
    el.classList.add('is-in');
    scrollToTarget(el, smooth && !prefersReducedMotion());

    if (withHighlight) highlight(el);
  }

  // Smooth scrolling issued during load gets cancelled, so the first pass is
  // always instant. Images and webfonts land after that and move everything
  // under the target, so it is re-centred on load and once more just after.
  function onReady() {
    goToHash(false, true);

    window.addEventListener('load', function () {
      goToHash(false, false);
      window.setTimeout(function () { goToHash(false, false); }, 250);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', onReady);
  } else {
    onReady();
  }

  window.addEventListener('hashchange', function () { goToHash(true, true); });
})();
