/**
 * The journey map's control panel page. Every form posts in the background and answers with a toast,
 * and the map card follows a running job until it ends. The server writes every sentence; this file
 * only places them.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-journey-map-admin]');
  if (!root) {
    return;
  }

  var POLL_MS = 3000;
  var timer = null;
  var wasBusy = root.dataset.busy === '1';

  function toast(message, isError) {
    if (window.cpToast && message) {
      window.cpToast(message, isError ? 'error' : 'success');
    }
  }

  /** Show each field's message under it, or hide the ones that have none. */
  function showErrors(form, errors) {
    form.querySelectorAll('[data-error]').forEach(function (holder) {
      var message = errors[holder.dataset.error] || '';
      holder.textContent = message;
      holder.hidden = message === '';
    });
  }

  /** Post a form with its CSRF token; always resolves to { ok, body }. */
  function post(form, url) {
    var token = form.querySelector('input[name="_token"]').value;

    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
      body: new FormData(form)
    }).then(function (response) {
      return response.json()
        .catch(function () { return {}; })
        .then(function (body) { return { ok: response.ok, body: body }; });
    });
  }

  /** A looked up place becomes the area choice. */
  function choosePlace(place) {
    var form = root.querySelector('[data-map-form]');
    var option = form.querySelector('[data-place-option]');
    form.querySelector('[data-region]').value = place.file;
    option.textContent = place.name;
    option.hidden = false;
    option.selected = true;
  }

  function submitted(event) {
    var form = event.target.closest('[data-journey-map-form]');
    if (!form) {
      return;
    }
    event.preventDefault();

    var button = event.submitter;
    var url = button && button.hasAttribute('formaction') ? button.formAction : form.action;
    showErrors(form, {});
    post(form, url)
      .then(function (result) {
        if (result.ok && form.hasAttribute('data-area-form')) {
          choosePlace(result.body);
        }
        if (result.ok && form.hasAttribute('data-reload')) {
          window.location.reload();
          return;
        }
        showErrors(form, result.body.errors || {});
        toast(result.body.message || result.body.error || root.dataset.checkFields, !result.ok);
        refresh();
      })
      .catch(function () { toast(root.dataset.failedText, true); });
  }

  /** Put the latest status on the page. A finished download reloads it, for the server check and usage. */
  function render(status) {
    var job = root.querySelector('[data-job]');
    var progress = root.querySelector('[data-progress]');

    root.querySelector('[data-line="map"]').textContent = status.lines.map;
    root.querySelector('[data-line="size"]').textContent = status.lines.size;
    root.querySelector('[data-line="job"]').textContent = status.lines.job;
    job.hidden = !status.job;
    progress.hidden = !status.busy;
    progress.value = status.job ? status.job.percent : 0;
    root.querySelectorAll('[data-idle-only]').forEach(function (control) {
      control.disabled = status.busy;
    });

    var downloaded = status.job && status.job.kind === 'download' && status.job.state === 'finished';
    if (wasBusy && !status.busy && downloaded) {
      window.location.reload();
    }
    wasBusy = status.busy;
  }

  function refresh() {
    window.clearTimeout(timer);
    fetch(root.dataset.statusUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (response) { return response.json(); })
      .then(function (status) {
        render(status);
        if (status.busy) {
          timer = window.setTimeout(refresh, POLL_MS);
        }
      })
      .catch(function () { timer = window.setTimeout(refresh, POLL_MS * 5); });
  }

  root.addEventListener('submit', submitted);
  if (wasBusy) {
    timer = window.setTimeout(refresh, POLL_MS);
  }
})();
