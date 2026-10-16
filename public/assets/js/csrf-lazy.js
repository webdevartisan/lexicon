/**
 * Fills empty CSRF fields when a form is submitted.
 *
 * Guest forms on blog pages render without a token so that viewing a page
 * stores no session. The first submit fetches one from /csrf-token, writes it
 * into every lazy field on the page, then submits again with the same button.
 */
(function () {
  'use strict';

  var pending = null;

  function fields() {
    return document.querySelectorAll('input[data-csrf-lazy]');
  }

  function fetchToken() {
    if (pending) return pending;

    pending = fetch('/csrf-token', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (!r.ok) throw new Error('CSRF token request failed with ' + r.status);
        return r.json();
      })
      .then(function (json) {
        var token = json.token || '';
        if (!token) throw new Error('CSRF token response was empty');
        fields().forEach(function (input) { input.value = token; });
        return token;
      })
      .finally(function () { pending = null; });

    return pending;
  }

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    var input = form.querySelector('input[data-csrf-lazy]');

    // Another handler already took over this submit, or the token is there.
    if (ev.defaultPrevented || !input || input.value) return;

    ev.preventDefault();
    var submitter = ev.submitter && ev.submitter.form === form ? ev.submitter : null;

    // requestSubmit keeps the clicked button's name and value; form.submit() drops them.
    function resubmit() {
      if (form.requestSubmit) {
        form.requestSubmit(submitter || undefined);
      } else {
        form.submit();
      }
    }

    fetchToken().then(resubmit).catch(function (err) {
      console.warn('Could not fetch a security token:', err);
      // Sending it anyway lets the server answer with its own expired-form page.
      input.removeAttribute('data-csrf-lazy');
      resubmit();
    });
  });
})();
