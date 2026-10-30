/**
 * Lets a blog owner reword the blog's texts on its own pages: the front-page
 * texts from the blog settings and the theme's own wording.
 *
 * The theme marks each text with data-edit-text. A theme text also carries its
 * wording with placeholders (data-edit-template) and what each placeholder
 * shows (data-edit-tokens), so a placeholder sits in the text as a locked tag.
 * The bar's [data-edit-fields] carries the save address and each text as
 * stored, so nothing here knows the server's routes.
 *
 * On the blog home and archive the texts drive the bar on their own. On the
 * post page they join the post's edit, through window.EditTexts.
 */
(function () {
  'use strict';

  var TOKEN = /(\{[a-z_]+\})/i;
  var RESET_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
    + ' stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>'
    + '<path d="M3 3v5h5"/></svg>';

  function attach(bar) {
    var dataEl = bar.querySelector('[data-edit-fields]');
    if (!dataEl) return null;

    var fields = JSON.parse(dataEl.textContent);
    var els = Array.prototype.slice.call(document.querySelectorAll('[data-edit-text]')).filter(function (el) {
      return Object.prototype.hasOwnProperty.call(fields, el.getAttribute('data-edit-text'));
    });
    if (els.length === 0) return null;

    var editing = false;
    var resets = [];
    var frame = 0;
    // What each text showed before editing, so Cancel can put the theme's own rendering back.
    var shown = [];
    var started = [];

    function keyOf(el) {
      return el.getAttribute('data-edit-text');
    }

    function fieldOf(el) {
      return fields[keyOf(el)];
    }

    function tokensOf(el) {
      try {
        return JSON.parse(el.getAttribute('data-edit-tokens') || '{}');
      } catch (e) {
        return {};
      }
    }

    // A theme text starts from the owner's wording, else the theme's, so a small change needs no retyping.
    function startingText(el) {
      var field = fieldOf(el);
      return field.wording ? (field.value || el.getAttribute('data-edit-template') || '') : field.value;
    }

    function render(el, text) {
      var tokens = tokensOf(el);
      el.textContent = '';
      text.split(TOKEN).forEach(function (part) {
        var name = part.charAt(0) === '{' ? part.slice(1, -1) : null;
        if (name === null || !Object.prototype.hasOwnProperty.call(tokens, name)) {
          if (part !== '') el.appendChild(document.createTextNode(part));
          return;
        }
        var tag = document.createElement('span');
        tag.className = 'edit-token';
        tag.contentEditable = 'false';
        tag.setAttribute('data-token', name);
        tag.textContent = tokens[name];
        el.appendChild(tag);
      });
    }

    function serialize(el) {
      var text = Array.prototype.map.call(el.childNodes, function (node) {
        if (node.nodeType === Node.ELEMENT_NODE && node.hasAttribute('data-token')) {
          return '{' + node.getAttribute('data-token') + '}';
        }
        return node.textContent;
      }).join('');

      return text.replace(/\s+/g, ' ').trim();
    }

    // What gets stored: a theme text left as the theme words it is stored as nothing.
    function valueOf(el) {
      var text = serialize(el);
      return fieldOf(el).wording && text === el.getAttribute('data-edit-template') ? '' : text;
    }

    // An empty text shows readers the blog's wording in its own language, else the theme's default.
    function placeholderOf(el) {
      var field = fieldOf(el);
      return field.fallback || el.getAttribute('data-edit-default') || field.label || '';
    }

    function makeEditable(el, on) {
      if (!on) {
        el.removeAttribute('contenteditable');
        el.removeAttribute('role');
        el.removeAttribute('aria-label');
        return;
      }
      el.setAttribute('contenteditable', 'plaintext-only');
      // Firefox before 136 has no plaintext mode; the save sends plain text either way.
      if (el.contentEditable !== 'plaintext-only') el.setAttribute('contenteditable', 'true');
      el.setAttribute('role', 'textbox');
      el.setAttribute('aria-label', fieldOf(el).label || el.getAttribute('data-edit-default') || '');
    }

    // Back to the default: the theme's wording, or for a front-page text, empty so its fallback shows.
    function resetText(el) {
      if (fieldOf(el).wording) {
        render(el, el.getAttribute('data-edit-template') || '');
      } else {
        el.textContent = '';
      }
    }

    function resetAll() {
      els.forEach(resetText);
    }

    // Each changed text gets a reset button at its corner. The buttons float above the page
    // rather than sit in the theme's markup, so no theme layout shifts to make room.
    function showResets(on) {
      if (!on) {
        window.cancelAnimationFrame(frame);
        resets.forEach(function (button) { button.remove(); });
        resets = [];
        return;
      }
      resets = els.map(function (el) {
        var button = document.createElement('button');
        var label = fieldOf(el).label;
        button.type = 'button';
        button.className = 'edit-reset';
        button.setAttribute('data-edit-reset-for', keyOf(el));
        button.title = 'Use the default wording';
        button.setAttribute('aria-label', 'Reset ' + (label ? label.toLowerCase() : 'this text'));
        button.innerHTML = RESET_ICON;
        button.addEventListener('click', function () {
          resetText(el);
          el.focus();
        });
        document.body.appendChild(button);
        return button;
      });
      placeResets();
    }

    // Texts move as the page scrolls and themes animate, so the buttons follow them every frame.
    function placeResets() {
      var size = 26;
      var header = document.querySelector('[data-edit-header]');
      var below = header ? header.getBoundingClientRect().bottom : 0;
      var rects = els.map(function (el) { return el.getBoundingClientRect(); });
      var changedNow = els.map(function (el) { return valueOf(el) !== ''; });

      rects.forEach(function (rect, i) {
        var button = resets[i];
        var show = changedNow[i] && rect.width > 0 && rect.top - size > below && rect.top < window.innerHeight;
        button.hidden = !show;
        if (!show) return;
        var rtl = getComputedStyle(els[i]).direction === 'rtl';
        var x = rtl ? rect.left - size / 2 - 6 : rect.right - size / 2 + 6;
        button.style.left = Math.min(Math.max(x, 4), window.innerWidth - size - 4) + 'px';
        button.style.top = (rect.top - size / 2 - 6) + 'px';
      });
      frame = window.requestAnimationFrame(placeResets);
    }

    function start() {
      shown = els.map(function (el) {
        return { html: el.innerHTML, hidden: el.hidden, ariaHidden: el.getAttribute('aria-hidden') };
      });
      els.forEach(function (el) {
        render(el, startingText(el));
        el.setAttribute('data-edit-placeholder', placeholderOf(el));
        el.hidden = false;
        // A decorative text the theme hides from screen readers still has to be reachable while it is a text box.
        el.removeAttribute('aria-hidden');
        makeEditable(el, true);
      });
      started = els.map(valueOf);
      editing = true;
      showResets(true);
    }

    // Saved texts show as typed, placeholders filled in; anything else goes back to what the theme drew.
    function stop(saved) {
      showResets(false);
      els.forEach(function (el, i) {
        makeEditable(el, false);
        if (saved && fieldOf(el).value !== '') {
          var tokens = tokensOf(el);
          el.textContent = fieldOf(el).value.replace(/\{([a-z_]+)\}/gi, function (match, name) {
            return Object.prototype.hasOwnProperty.call(tokens, name) ? tokens[name] : match;
          });
        } else if (saved && fieldOf(el).wording && started[i] !== '') {
          el.textContent = el.getAttribute('data-edit-default');
        } else {
          el.innerHTML = shown[i].html;
        }
        el.hidden = shown[i].hidden;
        if (shown[i].ariaHidden !== null) el.setAttribute('aria-hidden', shown[i].ariaHidden);
      });
      editing = false;
    }

    // Ends editing with each text as the theme draws it in a fresh copy of the page, so empty texts,
    // fallbacks and placeholders show exactly as they will after a reload.
    function redraw(fresh) {
      showResets(false);
      var waiting = {};
      fresh.querySelectorAll('[data-edit-text]').forEach(function (node) {
        (waiting[keyOf(node)] = waiting[keyOf(node)] || []).push(node);
      });
      els.forEach(function (el, i) {
        makeEditable(el, false);
        var twin = (waiting[keyOf(el)] || []).shift();
        if (!twin) {
          el.innerHTML = shown[i].html;
          return;
        }
        el.innerHTML = twin.innerHTML;
        el.hidden = twin.hidden;
        el.setAttribute('data-edit-default', twin.getAttribute('data-edit-default') || '');
        if (twin.hasAttribute('aria-hidden')) el.setAttribute('aria-hidden', twin.getAttribute('aria-hidden'));
      });
      editing = false;
    }

    function changed() {
      return els.filter(function (el, i) { return valueOf(el) !== started[i]; });
    }

    function isDirty() {
      return editing && changed().length > 0;
    }

    function save(send) {
      var edited = changed();
      if (edited.length === 0) return Promise.resolve();

      var data = new FormData();
      data.append('_token', bar.querySelector('input[name="_token"]').value);
      data.append('locale', dataEl.getAttribute('data-locale'));
      data.append('wording_locale', dataEl.getAttribute('data-wording-locale'));
      edited.forEach(function (el) {
        data.append(fieldOf(el).wording ? 'wording[' + keyOf(el) + ']' : keyOf(el), valueOf(el));
      });

      return send(data, 'The page could not be saved. Try again.', dataEl.getAttribute('data-save-url'))
        .then(function () {
          edited.forEach(function (el) { fieldOf(el).value = valueOf(el); });
          // What is saved is the new starting point, so leaving the page (or the reload that follows) asks nothing.
          started = els.map(valueOf);
        });
    }

    els.forEach(function (el) {
      el.addEventListener('keydown', function (event) {
        // These texts are single lines in every theme.
        if (event.key === 'Enter' && editing) event.preventDefault();
      });
      // Deleting every letter leaves a stray line break behind, which would hide the faded placeholder.
      el.addEventListener('input', function () {
        if (editing && el.textContent === '') el.textContent = '';
      });
    });

    // Some texts sit inside links and buttons; while they are text boxes a click places the cursor instead of leaving or sending.
    document.addEventListener('click', function (event) {
      if (editing && event.target.closest('[data-edit-text]') && event.target.closest('a, button')) event.preventDefault();
    }, true);

    return { start: start, stop: stop, redraw: redraw, isDirty: isDirty, save: save, resetAll: resetAll };
  }

  window.EditTexts = { attach: attach };

  /* The blog home and archive: the texts are all there is to edit. */

  var bar = document.querySelector('[data-edit-bar][data-edit-texts-only]');
  if (!bar) return;

  var texts = attach(bar);
  if (!texts) {
    bar.remove();
    return;
  }

  var editing = false;
  var editBar = window.EditBar.attach(bar, {
    isDirty: texts.isDirty,
    isEditing: function () { return editing; },
    discardMessage: 'Your changes to this page will be lost.'
  });
  var SAVED_KEY = 'lexicon.textsSaved';

  try {
    if (window.sessionStorage.getItem(SAVED_KEY)) {
      window.sessionStorage.removeItem(SAVED_KEY);
      editBar.say('Saved');
    }
  } catch (e) { /* Storage can be blocked; the page is right either way. */ }

  var resetAllBtn = bar.querySelector('[data-edit-reset-all]');

  function showEditing(on) {
    editing = on;
    editBar.showEditing(on);
    resetAllBtn.hidden = !on;
  }

  editBar.startBtn.addEventListener('click', function () {
    texts.start();
    showEditing(true);
    editBar.say('');
  });

  resetAllBtn.addEventListener('click', function () {
    var message = 'Every text on this page goes back to its default wording in this language. Nothing changes until you save.';
    var ask = window.blogConfirm ? window.blogConfirm(message) : Promise.resolve(window.confirm(message));
    ask.then(function (yes) {
      if (yes) texts.resetAll();
    });
  });

  editBar.cancelBtn.addEventListener('click', function () {
    editBar.confirmDiscard().then(function (yes) {
      if (!yes) return;
      texts.stop(false);
      showEditing(false);
      editBar.say('');
    });
  });

  // The theme decides how each text shows, empty ones included, so the saved texts come from the page as it now renders.
  function freshPage() {
    return fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html' } })
      .then(function (response) {
        if (!response.ok) throw new Error('The page could not be loaded.');
        return response.text();
      })
      .then(function (html) {
        return new DOMParser().parseFromString(html, 'text/html');
      });
  }

  bar.addEventListener('submit', function (event) {
    event.preventDefault();
    if (!editing) return;

    editBar.setBusy(true);
    editBar.say('Saving…');
    texts.save(editBar.send)
      .then(function () {
        return freshPage()
          .then(function (fresh) {
            texts.redraw(fresh);
            showEditing(false);
            editBar.setBusy(false);
            editBar.say('Saved');
          })
          .catch(function () {
            // Saved, but the fresh copy did not arrive: a reload shows the same thing, only less smoothly.
            editing = false;
            try { window.sessionStorage.setItem(SAVED_KEY, '1'); } catch (e) { /* see above */ }
            window.location.reload();
          });
      })
      .catch(function (error) {
        editBar.say(error.message, 'error');
        editBar.setBusy(false);
      });
  });
}());
