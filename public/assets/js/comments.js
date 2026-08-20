/**
 * Comment thread behaviour: reply boxes, collapsible replies, batched reply
 * reveal, voting, the per-comment overflow menu, the sort menu, reporting, and
 * landing on a deep-linked comment at any depth.
 *
 * Mostly progressive. Reply, remove and pin are real form posts that work with
 * scripting off; voting and reporting need fetch, and degrade to leaving the
 * page as it stands rather than to a broken control. A session that has since
 * expired reopens the sign-in flow instead of failing.
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

  /** An outcome a caller can branch on, rather than a bare parse error. */
  function failure(kind, message) {
    var error = new Error(message || kind);
    error.kind = kind;

    return error;
  }

  /**
   * POST to a comment endpoint, resolving with the payload the server sent.
   *
   * Rejects with kind 'auth' when the session is no longer good, and kind
   * 'failed' for anything else, carrying a message worth showing. The split is
   * what lets a caller tell apart the two things that arrive as HTML rather
   * than JSON: a login page, which the reader can do something about, and an
   * error page, which they cannot.
   */
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
    }).then(function (response) {
      // 419 is a stale token, and a redirect means fetch followed its way to
      // the login page. Signing in again fixes both, so neither is worth an
      // error message.
      if (response.status === 401 || response.status === 403 || response.status === 419 || response.redirected) {
        throw failure('auth');
      }

      return response.text().then(function (raw) {
        var json = null;

        try {
          json = JSON.parse(raw);
        } catch (e) {
          json = null;
        }

        // An error page carries nothing a reader can act on, so it becomes one
        // plain sentence rather than a parse exception nobody sees.
        if (json === null) {
          throw failure('failed', 'Something went wrong. Please try again.');
        }

        if (!response.ok || json.success === false) {
          throw failure('failed', json.error || 'That did not work. Please try again.');
        }

        return json.data;
      });
    });
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
    closeSort();
    list.toggleAttribute('hidden', !open);
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  // The sort control is a <details>, so it opens and closes itself but has no
  // idea a click landed somewhere else on the page. Left alone it sits there
  // until something inside it is chosen, which reads as being trapped.
  var sortMenu = root.querySelector('.comment-sort');

  function closeSort() {
    if (sortMenu) sortMenu.removeAttribute('open');
  }

  if (sortMenu) {
    sortMenu.addEventListener('toggle', function () {
      if (sortMenu.hasAttribute('open')) closeMenus(null);
    });
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
    if (button.classList.contains('is-busy')) return;

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
    button.classList.add('is-busy');

    post('/comments/' + button.getAttribute('data-comment-id') + '/report', { reason: reason })
      .then(function (data) {
        window.alert((data && data.message) || 'Thanks for the report.');
      })
      .catch(function (error) {
        if (error.kind === 'auth') {
          authenticateThen(button, 'report');

          return;
        }

        window.alert(error.message);
      })
      .finally(function () { button.classList.remove('is-busy'); });
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

  /**
   * The group's current tally, read from the DOM the first time and carried on
   * the element after that. Reading it back off the rendered text every time
   * would mean parsing a number we already know, through whatever separators
   * toLocaleString chose for the reader's locale.
   */
  function voteState(group) {
    if (group.dataset.up === undefined) {
      var active = group.querySelector('.comment-vote.is-active');
      var count = group.querySelector('[data-vote-count]');
      var text = (count && count.textContent) || '';

      group.dataset.up = String(parseInt(text.replace(/\D/g, ''), 10) || 0);
      group.dataset.mine = active ? (active.getAttribute('data-vote') === 'up' ? '1' : '-1') : '0';
    }

    return { up: Number(group.dataset.up), mine: Number(group.dataset.mine) };
  }

  /**
   * What the server will say, worked out locally so the button can move now.
   * Mirrors CommentVoteModel::apply: pressing your own vote clears it, the
   * opposite one flips it, and only up votes carry a tally.
   */
  function predictVote(state, direction) {
    var wanted = direction === 'up' ? 1 : -1;
    var mine = state.mine === wanted ? 0 : wanted;
    var up = state.up;

    if (state.mine === 1 && mine !== 1) up -= 1;
    if (state.mine !== 1 && mine === 1) up += 1;

    return { up: Math.max(0, up), mine: mine };
  }

  function paintVote(group, data) {
    group.dataset.up = String(data.up);
    group.dataset.mine = String(data.mine);

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
    var direction = button.getAttribute('data-vote');
    var confirmed = voteState(group);

    // Move now, ask after. The outcome of pressing your own thumb is known
    // here, so the round trip has nothing to add but delay.
    paintVote(group, predictVote(confirmed, direction));

    // One request at a time, but a press that lands mid-flight is remembered
    // rather than dropped: the screen has already moved, so ignoring it would
    // leave a vote showing that the reader has since changed. Latest wins.
    if (group.classList.contains('is-busy')) {
      group.dataset.pending = direction;

      return;
    }

    sendVote(group, button, direction, confirmed);
  }

  /**
   * Send a vote the group is already painted for.
   *
   * A press queued mid-flight enters here rather than through castVote, since
   * queueing it painted the group and a second paint would double the change.
   *
   * @param {Element} group     The .comment-votes wrapper
   * @param {Element} button    The pressed control, for the auth re-fire
   * @param {string}  direction "up" or "down"
   * @param {Object}  confirmed The tally the server held before this request
   */
  function sendVote(group, button, direction, confirmed) {
    // Ownership of the in-flight guard passes to a queued request, so this
    // one's cleanup leaves the guard alone once it has handed off.
    var chained = false;

    group.classList.add('is-busy');

    post('/comments/' + group.getAttribute('data-comment-id') + '/vote', { direction: direction })
      .then(function (data) {
        var pending = group.dataset.pending;

        // A press arrived while this was out, so the reader has already moved
        // past this answer. Painting it would flick the button back to a state
        // they abandoned, so the newer press goes out against it instead.
        if (pending) {
          delete group.dataset.pending;
          chained = true;
          sendVote(group, button, pending, data);

          return;
        }

        paintVote(group, data);
      })
      .catch(function (error) {
        // Put back what the server still holds. A rejection carries no counts,
        // and leaving the optimistic paint up would show a vote that was never
        // recorded.
        delete group.dataset.pending;
        paintVote(group, confirmed);

        if (error.kind === 'auth') authenticateThen(button, 'vote');
      })
      .finally(function () {
        if (!chained) group.classList.remove('is-busy');
      });
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
    if (!ev.target.closest('.comment-sort')) closeSort();
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape') return;

    closeMenus(null);

    // Escape hands focus back to what opened the list rather than leaving it
    // on a link that just disappeared.
    if (sortMenu && sortMenu.hasAttribute('open')) {
      closeSort();
      sortMenu.querySelector('summary').focus();
    }
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
