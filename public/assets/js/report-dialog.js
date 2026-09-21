/**
 * The report dialog, shared by comment and post reporting on every theme.
 *
 * Built on the native <dialog>: showModal() makes the page behind it inert and
 * Escape closes it, which covers most of the WAI-ARIA modal dialog pattern.
 * Focus moves to the first reason on open and back to the button that opened
 * it on close. The reasons come from the server, so a category an
 * administrator retires disappears here too.
 *
 * Callers pass a send(fields) function that returns a promise: it resolves
 * with the message to show, or rejects with { kind: 'auth' } when the reader
 * has to sign in first, or with { message } for anything else.
 */
(function () {
  if (window.LexiconReport) return;

  var DETAILS_MAX = 1000;
  var reasons = null;
  var dialog = null;
  var parts = {};
  var current = null;

  function loadReasons() {
    if (reasons) return reasons;

    reasons = fetch('/report-reasons', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.json();
      })
      .then(function (json) { return json.data || []; })
      .catch(function (error) {
        // Forget the failure so the next attempt asks the server again
        reasons = null;
        throw error;
      });

    return reasons;
  }

  function el(tag, attrs, text) {
    var node = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (key) { node.setAttribute(key, attrs[key]); });
    if (text) node.textContent = text;
    return node;
  }

  function build() {
    dialog = el('dialog', { class: 'lex-report', 'aria-labelledby': 'lex-report-title' });

    var form = el('form', { class: 'lex-report-form', novalidate: '' });
    parts.title = el('h2', { id: 'lex-report-title', class: 'lex-report-title' });
    var lede = el('p', { class: 'lex-report-lede' }, 'A moderator will look at it. The author is not told who reported it.');

    parts.list = el('fieldset', { class: 'lex-report-reasons' });
    parts.list.appendChild(el('legend', {}, 'What is wrong with it?'));
    parts.options = el('div', { class: 'lex-report-options' });
    parts.list.appendChild(parts.options);

    var detailsLabel = el('label', { for: 'lex-report-details', class: 'lex-report-label' }, 'Anything a moderator should know? (optional)');
    parts.details = el('textarea', { id: 'lex-report-details', name: 'details', rows: '3', maxlength: String(DETAILS_MAX) });

    parts.error = el('p', { class: 'lex-report-error', role: 'alert' });
    parts.error.hidden = true;

    var actions = el('div', { class: 'lex-report-actions' });
    parts.cancel = el('button', { type: 'button', class: 'lex-report-cancel' }, 'Cancel');
    parts.submit = el('button', { type: 'submit', class: 'lex-report-submit' }, 'Send report');
    actions.appendChild(parts.cancel);
    actions.appendChild(parts.submit);

    form.appendChild(parts.title);
    form.appendChild(lede);
    form.appendChild(parts.list);
    form.appendChild(detailsLabel);
    form.appendChild(parts.details);
    form.appendChild(parts.error);
    form.appendChild(actions);

    parts.done = el('div', { class: 'lex-report-done', role: 'status' });
    parts.doneText = el('p', {});
    parts.close = el('button', { type: 'button', class: 'lex-report-submit' }, 'Close');
    parts.done.appendChild(parts.doneText);
    parts.done.appendChild(parts.close);
    parts.done.hidden = true;

    parts.form = form;
    dialog.appendChild(form);
    dialog.appendChild(parts.done);
    document.body.appendChild(dialog);

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      send();
    });
    parts.cancel.addEventListener('click', function () { dialog.close(); });
    parts.close.addEventListener('click', function () { dialog.close(); });

    // A click on the backdrop lands on the dialog element itself
    dialog.addEventListener('click', function (ev) {
      if (ev.target === dialog && !parts.submit.disabled) dialog.close();
    });

    dialog.addEventListener('close', function () {
      var opener = current && current.opener;
      current = null;
      if (opener && document.contains(opener)) opener.focus();
    });
  }

  function showError(message) {
    parts.error.textContent = message;
    parts.error.hidden = false;
  }

  function busy(on) {
    parts.submit.disabled = on;
    parts.submit.textContent = on ? 'Sending...' : 'Send report';
  }

  function renderReasons(list) {
    parts.options.textContent = '';

    list.forEach(function (reason, i) {
      var id = 'lex-report-reason-' + i;
      var row = el('div', { class: 'lex-report-option' });
      var input = el('input', { type: 'radio', name: 'reason', id: id, value: reason.slug });
      var label = el('label', { for: id });

      label.appendChild(el('span', { class: 'lex-report-option-label' }, reason.label));
      if (reason.description) {
        label.appendChild(el('span', { class: 'lex-report-option-hint' }, reason.description));
      }

      row.appendChild(input);
      row.appendChild(label);
      parts.options.appendChild(row);
    });
  }

  function send() {
    var picked = parts.options.querySelector('input[name="reason"]:checked');

    if (!picked) {
      showError('Choose what is wrong with it.');
      var first = parts.options.querySelector('input[name="reason"]');
      if (first) first.focus();
      return;
    }

    parts.error.hidden = true;
    busy(true);

    var request = current;

    request.send({ reason: picked.value, details: parts.details.value.trim() })
      .then(function (message) {
        parts.form.hidden = true;
        parts.done.hidden = false;
        parts.doneText.textContent = message || 'Thanks. A moderator will look at it.';
        parts.close.focus();
      })
      .catch(function (error) {
        if (error && error.kind === 'auth') {
          dialog.close();
          if (request.onAuth) request.onAuth();
          return;
        }

        showError((error && error.message) || 'That report could not be sent. Please try again.');
      })
      .finally(function () { busy(false); });
  }

  /**
   * @param {{subject: string, opener: HTMLElement, send: function(Object): Promise, onAuth: function()}} options
   */
  function open(options) {
    if (!dialog) build();
    if (dialog.open) return;

    current = options;
    parts.title.textContent = 'Report this ' + options.subject;
    parts.form.hidden = false;
    parts.done.hidden = true;
    parts.error.hidden = true;
    parts.details.value = '';
    parts.options.textContent = '';
    busy(true);
    dialog.showModal();

    loadReasons()
      .then(function (list) {
        renderReasons(list);
        busy(false);
        var first = parts.options.querySelector('input[name="reason"]');
        if (first) first.focus();
      })
      .catch(function (error) {
        console.error('Report reasons could not be loaded:', error);
        showError('The list of reasons could not be loaded. Close this and try again.');
        parts.submit.textContent = 'Send report';
        parts.cancel.focus();
      });
  }

  window.LexiconReport = { open: open };
})();
