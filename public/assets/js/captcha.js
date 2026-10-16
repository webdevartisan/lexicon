// Sign-in check: solves a proof-of-work challenge from the server in Web Workers.
// The workers are ALTCHA's own (public/assets/vendor/altcha/pbkdf2.js); this file
// only splits the search between them and builds the payload the server checks.
// The server decides when a check is needed; nothing here decides that.
(function () {
  if (window.LexiconCaptcha) return;

  var script = document.currentScript;
  var workerUrl = (script && script.getAttribute('data-worker')) || '/assets/vendor/altcha/pbkdf2.js';

  // Resolves with the base64 payload the server expects in the "altcha" field.
  function solve(challenge) {
    var count = Math.max(1, Math.min(4, navigator.hardwareConcurrency || 2));

    return new Promise(function (resolve, reject) {
      var workers = [];
      var unanswered = count;
      var settled = false;

      function stop() {
        workers.forEach(function (w) { w.terminate(); });
      }

      function fail(err) {
        if (settled) return;
        settled = true;
        stop();
        reject(err instanceof Error ? err : new Error(String(err)));
      }

      for (var i = 0; i < count; i++) {
        var worker = new Worker(workerUrl);
        workers.push(worker);

        worker.onmessage = function (ev) {
          var data = ev.data;
          if (settled) return;
          if (data && data.error) { fail(data.error); return; }
          if (data && typeof data.counter === 'number') {
            settled = true;
            stop();
            resolve(btoa(JSON.stringify({ challenge: challenge, solution: data })));
            return;
          }
          // null: this worker ran out of time without finding the answer
          unanswered--;
          if (unanswered === 0) fail(new Error('No worker found the answer before the timeout.'));
        };
        worker.onerror = function (ev) { fail(ev.message || 'The check worker failed to load.'); };

        // Each worker walks its own slice of the counters: i, i + count, i + 2 * count...
        worker.postMessage({ type: 'work', challenge: challenge, counterStart: i, counterStep: count, counterMode: 'uint32', timeout: 60000 });
      }
    });
  }

  // Shows progress in a [data-captcha-status] element and holds the submit button
  // until the answer is in. Returns the solve promise.
  function run(challenge, ui) {
    var status = ui.status;
    var button = ui.button;

    function setState(state, text) {
      if (status) {
        status.hidden = false;
        status.setAttribute('data-state', state);
        status.querySelector('[data-captcha-text]').textContent = text;
      }
      if (button) {
        var busy = state === 'checking';
        button.disabled = busy;
        button.setAttribute('aria-busy', busy ? 'true' : 'false');
      }
    }

    setState('checking', ui.text.checking);

    return solve(challenge).then(function (payload) {
      setState('done', ui.text.done);
      return payload;
    }, function (err) {
      console.error('Sign-in check failed:', err);
      setState('failed', ui.text.failed);
      throw err;
    });
  }

  window.LexiconCaptcha = { solve: solve, run: run };

  // Full login page: a challenge printed by the server starts solving at once,
  // so it is usually done before the password has been typed again.
  var form = document.querySelector('form[data-captcha]');
  if (!form) return;

  var status = form.querySelector('[data-captcha-status]');
  var field = form.querySelector('input[name="altcha"]');
  var text = {
    checking: status.getAttribute('data-checking'),
    done: status.getAttribute('data-done'),
    failed: status.getAttribute('data-failed')
  };

  run(JSON.parse(form.getAttribute('data-captcha')), { status: status, button: form.querySelector('[type="submit"]'), text: text })
    .then(function (payload) { field.value = payload; })
    .catch(function () {
      // Leave the button usable: the server refuses the empty answer and hands out a new challenge
      var button = form.querySelector('[type="submit"]');
      if (button) button.disabled = false;
    });
})();
