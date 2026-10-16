(function () {
  var modal = document.getElementById('lex-auth-modal');
  if (!modal || window.LexiconAuth) return;

  var title = document.getElementById('lex-auth-title');
  var sub = modal.querySelector('[data-auth-sub]');
  var errEl = modal.querySelector('[data-auth-error]');
  var emailForm = modal.querySelector('[data-auth-step="email"]');
  var passForm = modal.querySelector('[data-auth-step="password"]');
  var forgotForm = modal.querySelector('[data-auth-step="forgot"]');
  var forgotText = modal.querySelector('[data-auth-forgot-text]');
  var forgotSend = modal.querySelector('[data-auth-forgot-send]');
  var successBox = modal.querySelector('[data-auth-step="success"]');
  var successMsg = modal.querySelector('[data-auth-success-msg]');
  var emailInput = document.getElementById('lex-auth-email');
  var passInput = document.getElementById('lex-auth-password');
  var submitBtn = modal.querySelector('[data-auth-submit]');
  var hint = modal.querySelector('[data-auth-hint]');
  var forgot = modal.querySelector('[data-auth-forgot]');
  var registerOnly = modal.querySelector('[data-auth-register-only]');
  var ageInput = modal.querySelector('[data-auth-age]');
  var toggle = modal.querySelector('[data-auth-toggle]');
  var checkStatus = modal.querySelector('[data-captcha-status]');
  var text = JSON.parse(modal.getAttribute('data-text') || '{}');
  var pageToken = modal.querySelector('input[name="_token"]').value;
  var freshToken = null;
  var returnTo = modal.getAttribute('data-return-to') || '/';
  // Locale-prefixed so the nav fetch does not eat a 308 on every login
  var authNavUrl = modal.getAttribute('data-nav-url') || '/auth/nav';
  var urls = {
    identify: modal.getAttribute('data-identify-url') || '/login/identify',
    login: modal.getAttribute('data-login-url') || '/login',
    register: modal.getAttribute('data-register-url') || '/register/submit',
    forgot: modal.getAttribute('data-forgot-url') || '/password/forgot',
    captcha: modal.getAttribute('data-captcha-url') || '/captcha/challenge'
  };

  // Locale strings come from the partial; {name} placeholders are filled here.
  function tr(key, params) {
    var out = text[key] || key;
    Object.keys(params || {}).forEach(function (k) { out = out.split('{' + k + '}').join(params[k]); });
    return out;
  }

  // Public pages come from the full-page guest cache, so the token baked
  // into the HTML can belong to another session. Fetch a live one before
  // posting. The same pattern consent.js uses on cached pages.
  function getToken() {
    if (freshToken) return Promise.resolve(freshToken);
    return fetch('/csrf-token', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { freshToken = j.token || pageToken; return freshToken; })
      .catch(function (err) {
        console.warn('Could not fetch a fresh security token, using the one on the page:', err);
        return pageToken;
      });
  }

  // Reader-first titles keyed by what the visitor was trying to do
  var titles = {
    login: tr('titleLogin'),
    reply: tr('titleReply'),
    comment: tr('titleComment'),
    like: tr('titleLike'),
    bookmark: tr('titleBookmark')
  };

  var state = { mode: 'login', email: '', onSuccess: null, lastFocus: null, registerCheck: null };

  // Resolves for every answer the server gives, including errors, so only a
  // request that never got an answer ends up in a .catch.
  function post(url, body) {
    return getToken().then(function (t) {
      var fd = new FormData();
      fd.append('_token', t);
      Object.keys(body).forEach(function (k) { fd.append(k, body[k]); });
      return fetch(url, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      }).then(function (r) {
        var isJson = (r.headers.get('Content-Type') || '').indexOf('application/json') !== -1;
        var parsed = isJson ? r.json().catch(function () { return null; }) : Promise.resolve(null);

        return parsed.then(function (j) {
          if (r.status === 419) freshToken = null;
          return { ok: r.ok && !!(j && j.success), status: r.status, json: j || {} };
        });
      });
    });
  }

  function failureMessage(res) {
    if (res.status === 419) {
      return tr('sessionExpired');
    }
    if (res.status >= 500 || !res.json.error) {
      return tr('serverError', { status: res.status });
    }
    return res.json.error;
  }

  function networkFailure(err) {
    console.error('Auth request failed:', err);
    showError(tr('networkError'));
  }

  // The check script is only fetched once a challenge actually arrives, so the
  // cached pages carrying this modal load nothing extra for most readers.
  var captchaLoad = null;
  function loadCaptcha() {
    if (window.LexiconCaptcha) return Promise.resolve(window.LexiconCaptcha);
    if (captchaLoad) return captchaLoad;
    captchaLoad = new Promise(function (resolve, reject) {
      var el = document.createElement('script');
      el.src = modal.getAttribute('data-captcha-script');
      el.setAttribute('data-worker', modal.getAttribute('data-captcha-worker'));
      el.onload = function () { resolve(window.LexiconCaptcha); };
      el.onerror = function () { captchaLoad = null; reject(new Error('Could not load the sign-in check script.')); };
      document.head.appendChild(el);
    });
    return captchaLoad;
  }

  function runCheck(challenge) {
    return loadCaptcha().then(function (captcha) {
      return captcha.run(challenge, {
        status: checkStatus,
        button: submitBtn,
        text: { checking: tr('checking'), done: tr('checkDone'), failed: tr('checkFailed') }
      });
    }).catch(function (err) {
      // Tells the submit handler this was the check, not the network
      err.captcha = true;
      throw err;
    });
  }

  // Sign-up always carries an answer, so it starts solving while the password is typed.
  function startRegisterCheck() {
    state.registerCheck = post(urls.captcha, { purpose: 'register' }).then(function (res) {
      if (!res.ok) throw new Error(failureMessage(res));
      return runCheck(res.json.data.challenge);
    });
    // Reported when the form is sent; an unhandled rejection here would only be noise.
    state.registerCheck.catch(function () {});
  }

  function resetCheck() {
    state.registerCheck = null;
    checkStatus.setAttribute('hidden', '');
    submitBtn.removeAttribute('aria-busy');
  }

  // The theme script bound its user-menu handler at load, when this page was
  // still guest markup and the menu did not exist. Bind the swapped-in copy
  // here. No risk of double binding: the modal only renders for guests.
  function bindUserMenu(root) {
    var menu = root.querySelector('.nav-user');
    var btn = menu && menu.querySelector('.nav-user-btn');
    if (!btn) return;

    btn.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var open = menu.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function () {
      menu.classList.remove('is-open');
      btn.setAttribute('aria-expanded', 'false');
    });
  }

  // Logging in without navigating leaves the guest header on screen, which
  // reads as if the login failed. Pull the logged-in markup and swap it in.
  function refreshAuthNav() {
    var host = document.querySelector('[data-auth-nav]');
    if (!host) return Promise.resolve();

    var url = authNavUrl + '?variant=' + encodeURIComponent(host.getAttribute('data-auth-nav') || 'theme')
            + '&return_to=' + encodeURIComponent(returnTo);

    return fetch(url, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.success && j.data && typeof j.data.html === 'string') {
          host.innerHTML = j.data.html;
          bindUserMenu(host);
        }
      })
      .catch(function (err) { console.error('Could not refresh the header after logging in:', err); });
  }

  function showError(msg) { errEl.textContent = msg; errEl.removeAttribute('hidden'); }
  function clearError() { errEl.setAttribute('hidden', ''); }

  function showStep(step) {
    emailForm.toggleAttribute('hidden', step !== 'email');
    passForm.toggleAttribute('hidden', step !== 'password');
    forgotForm.toggleAttribute('hidden', step !== 'forgot');
    successBox.toggleAttribute('hidden', step !== 'success');
  }

  function close() {
    modal.setAttribute('hidden', '');
    document.body.style.overflow = '';
    if (state.lastFocus && state.lastFocus.focus) state.lastFocus.focus();
  }

  window.LexiconAuth = {
    open: function (opts) {
      opts = opts || {};
      state.onSuccess = opts.onSuccess || null;
      state.mode = 'login';
      state.lastFocus = document.activeElement;
      title.textContent = titles[opts.reason] || titles.login;
      sub.textContent = tr('intro');
      clearError();
      resetCheck();
      showStep('email');
      modal.removeAttribute('hidden');
      document.body.style.overflow = 'hidden';
      emailInput.focus();
    }
  };

  emailForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearError();
    var email = emailInput.value.trim();
    if (!email || email.indexOf('@') < 1) { showError(tr('invalidEmail')); return; }

    post(urls.identify, { email: email }).then(function (res) {
      if (!res.ok) { showError(failureMessage(res)); return; }
      state.email = email;
      var exists = !!(res.json.data && res.json.data.exists);
      state.mode = exists ? 'login' : 'register';

      resetCheck();
      if (exists) {
        title.textContent = tr('welcomeBack');
        sub.textContent = tr('enterPasswordFor', { email: email });
        submitBtn.textContent = tr('logIn');
        passInput.setAttribute('autocomplete', 'current-password');
        hint.setAttribute('hidden', '');
        registerOnly.setAttribute('hidden', '');
        forgot.removeAttribute('hidden');
      } else {
        title.textContent = tr('createTitle');
        sub.textContent = tr('createIntro');
        submitBtn.textContent = tr('createSubmit');
        passInput.setAttribute('autocomplete', 'new-password');
        hint.removeAttribute('hidden');
        registerOnly.removeAttribute('hidden');
        ageInput.checked = false;
        forgot.setAttribute('hidden', '');
        startRegisterCheck();
      }

      passInput.value = '';
      showStep('password');
      passInput.focus();
    }).catch(networkFailure);
  });

  passForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearError();

    if (passInput.value === '') { showError(tr('passwordRequired')); return; }
    var minLength = parseInt(passInput.getAttribute('data-min-length'), 10);
    if (state.mode === 'register' && passInput.value.length < minLength) {
      showError(tr('passwordTooShort', { min: minLength }));
      return;
    }
    if (state.mode === 'register' && !ageInput.checked) {
      showError(ageInput.getAttribute('data-error'));
      return;
    }

    var url = state.mode === 'register' ? urls.register : urls.login;
    submitBtn.disabled = true;

    var body = { email: state.email, password: passInput.value, return_to: returnTo };
    if (state.mode === 'register') { body.age_confirmed = ageInput.checked ? '1' : ''; }

    // The server says when a check is needed (422 with a challenge). Solve it and
    // send again once; a second refusal is shown as it is.
    function send(answer, retried) {
      if (answer) body.altcha = answer;
      return post(url, body).then(function (res) {
        if (!res.ok && res.status === 422 && res.json.captcha && !retried) {
          showError(res.json.error);
          return runCheck(res.json.captcha).then(function (next) {
            clearError();
            return send(next, true);
          });
        }
        return res;
      });
    }

    var first = state.mode === 'register' && state.registerCheck
      ? state.registerCheck.then(function (answer) { return send(answer, false); }, function () { return send('', false); })
      : send('', false);

    first.then(function (res) {
      submitBtn.disabled = false;
      if (!res.ok) { showError(failureMessage(res)); return; }

      // Signing in rotates the CSRF token, so the one used for this post is spent
      freshToken = null;

      successMsg.textContent = state.mode === 'register' ? tr('welcomeNew') : tr('loggedIn');
      showStep('success');

      // Give the success state a beat, then finish what the reader started
      setTimeout(function () {
        var done = state.onSuccess;
        close();

        // No callback means nothing to finish in place, so a reload is both
        // the simplest way to show the logged-in header and what the reader
        // expects after using the header login pill.
        if (!done) { window.location.reload(); return; }

        refreshAuthNav();
        done();
      }, 700);
    }).catch(function (err) {
      submitBtn.disabled = false;
      if (err && err.captcha) { showError(tr('checkFailed')); return; }
      networkFailure(err);
    });
  });

  // Forgot password without leaving the modal: the email is already known
  // from step 1, so one tap sends the reset link
  forgot.addEventListener('click', function (ev) {
    ev.preventDefault();
    clearError();
    title.textContent = tr('resetTitle');
    forgotText.textContent = tr('resetIntro', { email: state.email });
    forgotSend.removeAttribute('hidden');
    showStep('forgot');
  });

  forgotForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearError();
    forgotSend.disabled = true;

    post(urls.forgot, { email: state.email }).then(function (res) {
      forgotSend.disabled = false;
      if (!res.ok) { showError(failureMessage(res)); return; }
      forgotText.textContent = (res.json.data && res.json.data.message) || tr('resetSent');
      forgotSend.setAttribute('hidden', '');
    }).catch(function (err) {
      forgotSend.disabled = false;
      networkFailure(err);
    });
  });

  modal.querySelector('[data-auth-forgot-back]').addEventListener('click', function () {
    clearError();
    title.textContent = tr('welcomeBack');
    showStep('password');
    passInput.focus();
  });

  toggle.addEventListener('click', function () {
    var show = passInput.type === 'password';
    passInput.type = show ? 'text' : 'password';
    toggle.textContent = show ? tr('hide') : tr('show');
    toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
  });

  modal.querySelector('[data-auth-back]').addEventListener('click', function () {
    clearError();
    title.textContent = titles.login;
    sub.textContent = tr('intro');
    resetCheck();
    showStep('email');
    emailInput.focus();
  });

  modal.querySelector('[data-auth-close]').addEventListener('click', close);
  modal.addEventListener('click', function (ev) { if (ev.target === modal) close(); });
  document.addEventListener('keydown', function (ev) {
    if (ev.key === 'Escape' && !modal.hasAttribute('hidden')) close();
  });

  // Login triggers: the theme header pill (same markup in every theme) and
  // any link opting in via data-auth-login (platform front pages).
  // The href stays intact as the no-JS fallback.
  document.querySelectorAll('a.nav-pill[href*="/login"], [data-auth-login]').forEach(function (link) {
    link.addEventListener('click', function (ev) {
      ev.preventDefault();
      window.LexiconAuth.open({ reason: 'login' });
    });
  });

  // Guest comment and reply forms: open the modal on submit and post once
  // authenticated. The typed text never leaves the page. Server-side
  // session capture remains the no-JS fallback for the same forms.
  document.querySelectorAll('form.reply-form, form.comment-form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      window.LexiconAuth.open({
        reason: form.classList.contains('reply-form') ? 'reply' : 'comment',
        onSuccess: function () {
          // The token baked into the form belongs to the guest session
          getToken().then(function (t) {
            var tokenField = form.querySelector('input[name="_token"]');
            if (tokenField) tokenField.value = t;
            form.submit();
          });
        }
      });
    });
  });
})();
