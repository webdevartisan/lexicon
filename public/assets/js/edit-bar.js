/**
 * The bar at the bottom of a blog page that starts, cancels and saves an edit in place.
 *
 * Shared by the post page and the blog home. Each page owns what gets edited;
 * this owns the bar: its buttons, its status line, sending the save, and the
 * guards against losing changes.
 */
(function () {
  'use strict';

  /**
   * @param {HTMLFormElement} bar The [data-edit-bar] form
   * @param {{isDirty: function(): boolean, isEditing: function(): boolean, discardMessage: string}} page
   */
  function attach(bar, page) {
    // Out of the theme's content area: a theme that stacks its own layers there would
    // otherwise draw its footer over the bar, however high the bar's z-index.
    document.body.appendChild(bar);

    var startBtn = bar.querySelector('[data-edit-start]');
    var cancelBtn = bar.querySelector('[data-edit-cancel]');
    var saveBtn = bar.querySelector('[data-edit-save]');
    var moreLink = bar.querySelector('[data-edit-more]');
    var statusEl = bar.querySelector('[data-edit-status]');

    function say(text, tone) {
      statusEl.textContent = text || '';
      if (tone) {
        statusEl.setAttribute('data-tone', tone);
      } else {
        statusEl.removeAttribute('data-tone');
      }
    }

    function showEditing(on) {
      startBtn.hidden = on;
      cancelBtn.hidden = !on;
      saveBtn.hidden = !on;
      moreLink.hidden = !on;
      document.documentElement.classList.toggle('is-editing-in-place', on);
    }

    function setBusy(on) {
      [startBtn, cancelBtn, saveBtn].forEach(function (button) { button.disabled = on; });
    }

    function confirmDiscard() {
      if (!page.isDirty()) return Promise.resolve(true);
      var message = page.discardMessage;
      return window.blogConfirm ? window.blogConfirm(message) : Promise.resolve(window.confirm(message));
    }

    // Answers with the server's JSON, or fails with the message it gave. The address defaults to the bar's own.
    function send(data, failure, url) {
      return fetch(url || bar.getAttribute('data-save-url'), {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (response) {
        return response.json()
          .catch(function () { return {}; })
          .then(function (json) {
            if (!response.ok) throw new Error(json.error || failure);
            return json;
          });
      });
    }

    // Phones draw the on-screen keyboard over fixed elements, so the bar rides above it.
    function liftAboveKeyboard() {
      var viewport = window.visualViewport;
      var covered = viewport && viewport.scale <= 1.01
        ? window.innerHeight - viewport.height - viewport.offsetTop
        : 0;
      bar.style.bottom = covered > 1 ? 'calc(' + Math.round(covered) + 'px + 0.5rem)' : '';
    }

    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', liftAboveKeyboard);
      window.visualViewport.addEventListener('scroll', liftAboveKeyboard);
    }

    document.addEventListener('keydown', function (event) {
      if (page.isEditing() && (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
        event.preventDefault();
        bar.requestSubmit(saveBtn);
      }
    });

    // Set once the person has agreed to leave, so the browser does not ask a second time.
    var leaving = false;

    // A link on the page asks in the site's own dialog. The browser's question is left for
    // closing the tab or typing an address, where a page may not show its own.
    document.addEventListener('click', function (event) {
      var link = event.target.closest('a[href]');
      if (!link || event.defaultPrevented || link.isContentEditable || !page.isDirty()) return;
      if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      if ((link.target && link.target !== '_self') || link.hasAttribute('download')) return;
      if (link.getAttribute('href').charAt(0) === '#') return;

      event.preventDefault();
      confirmDiscard().then(function (yes) {
        if (!yes) return;
        leaving = true;
        window.location.href = link.href;
      });
    });

    window.addEventListener('beforeunload', function (event) {
      if (leaving || !page.isDirty()) return;
      event.preventDefault();
      event.returnValue = '';
    });

    return {
      startBtn: startBtn,
      cancelBtn: cancelBtn,
      say: say,
      showEditing: showEditing,
      setBusy: setBusy,
      confirmDiscard: confirmDiscard,
      send: send
    };
  }

  window.EditBar = { attach: attach };
}());
