<?php
// Blog-front auth modal, shared by every theme (resolved from global views/).
// Email-first: one email field decides between login and inline registration,
// so readers never leave the page they were reading. Server endpoints:
// POST /login/identify, /login/submit, /register/submit (AJAX/JSON branches).
// With JS off, every trigger keeps its normal link to the full login page.
if (!auth()->check()) {
    $authModalReturnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    ?>
<div class="lex-auth-overlay" id="lex-auth-modal" hidden role="dialog" aria-modal="true" aria-labelledby="lex-auth-title">
  <div class="lex-auth-card">
    <button type="button" class="lex-auth-close" data-auth-close aria-label="Close">&times;</button>

    <h2 class="lex-auth-title" id="lex-auth-title">Log in to continue</h2>
    <p class="lex-auth-sub" data-auth-sub>Enter your email to log in or create your reader account.</p>

    <p class="lex-auth-error" data-auth-error role="alert" hidden></p>

    <form data-auth-step="email" novalidate>
      <?= csrf_field() ?>
      <label class="lex-auth-label" for="lex-auth-email">Email</label>
      <input class="lex-auth-input" type="email" id="lex-auth-email" name="email"
             autocomplete="email" inputmode="email" required>
      <button class="lex-auth-btn" type="submit">Continue &rarr;</button>
    </form>

    <form data-auth-step="password" hidden novalidate>
      <div class="lex-auth-labelrow">
        <label class="lex-auth-label" for="lex-auth-password">Password</label>
        <button class="lex-auth-toggle" type="button" data-auth-toggle aria-pressed="false">Show</button>
      </div>
      <input class="lex-auth-input" type="password" id="lex-auth-password" name="password"
             autocomplete="current-password" required>
      <p class="lex-auth-hint" data-auth-hint hidden>At least 6 characters.</p>
      <button class="lex-auth-btn" type="submit" data-auth-submit>Log in &rarr;</button>
      <div class="lex-auth-links">
        <a href="<?= e(lurl('/password/forgot')) ?>" data-auth-forgot>Forgot password?</a>
        <button class="lex-auth-linkbtn" type="button" data-auth-back>Use a different email</button>
      </div>
    </form>

    <form data-auth-step="forgot" hidden novalidate>
      <p class="lex-auth-hint" data-auth-forgot-text style="margin: 0 0 .75rem;"></p>
      <button class="lex-auth-btn" type="submit" data-auth-forgot-send>Send reset link</button>
      <div class="lex-auth-links">
        <button class="lex-auth-linkbtn" type="button" data-auth-forgot-back>Back to login</button>
      </div>
    </form>

    <div data-auth-step="success" hidden>
      <p class="lex-auth-success" data-auth-success-msg>You&rsquo;re logged in!</p>
    </div>

    <p class="lex-auth-foot">
      <a href="<?= e(lurl('/login').'?return_to='.urlencode($authModalReturnTo)) ?>">Use the full login page</a>
      <span aria-hidden="true">&middot;</span>
      <span>Lexicon</span>
    </p>
  </div>
</div>

<style>
  .lex-auth-overlay { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 18, 20, .6); backdrop-filter: blur(2px); }
  .lex-auth-overlay[hidden] { display: none; }
  .lex-auth-card { position: relative; width: 100%; max-width: 380px; background: #fff; color: #16181d; border-radius: 10px; padding: 1.75rem 1.5rem 1.25rem; box-shadow: 0 24px 64px rgba(0, 0, 0, .35); font-family: inherit; }
  /* The platform layout loads main.css, whose bare `button` and
     `input[type="..."]` selectors outrank plain classes and were repainting
     this modal (uppercase Roboto Slab buttons, forced input metrics, and a
     `color: ... !important` on every button). Everything below is scoped to
     .lex-auth-card so it wins on specificity, and the two reset blocks strip
     what main.css injects. The colour resets need !important to match it. */
  .lex-auth-card button {
    appearance: none; -webkit-appearance: none; background: none; border: 0;
    box-shadow: none; font: inherit; letter-spacing: normal;
    text-transform: none; text-align: inherit; height: auto;
    line-height: normal; padding: 0; border-radius: 0; white-space: normal;
    color: inherit !important;
  }
  .lex-auth-card input {
    appearance: none; -webkit-appearance: none; box-sizing: border-box;
    height: auto; margin: 0; font: inherit; line-height: normal;
    box-shadow: none;
  }

  .lex-auth-card .lex-auth-close { position: absolute; top: .6rem; right: .75rem; font-size: 1.5rem; line-height: 1; color: #9aa1ab !important; cursor: pointer; padding: .25rem; width: auto; }
  .lex-auth-card .lex-auth-close:hover { color: #16181d !important; }
  .lex-auth-card .lex-auth-title { margin: 0 0 .35rem; font-size: 1.25rem; line-height: 1.25; color: #16181d; }
  .lex-auth-card .lex-auth-sub { margin: 0 0 1rem; font-size: .85rem; color: #5c6470; }
  .lex-auth-card .lex-auth-error { margin: 0 0 .75rem; padding: .5rem .65rem; border-radius: 6px; background: #fdecec; color: #b3261e; font-size: .8rem; }
  .lex-auth-card .lex-auth-label { display: block; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #444b55; margin-bottom: .35rem; }
  .lex-auth-card .lex-auth-labelrow { display: flex; align-items: center; justify-content: space-between; }
  .lex-auth-card .lex-auth-toggle { font-size: .7rem; letter-spacing: .05em; text-transform: uppercase; color: #5c6470 !important; cursor: pointer; padding: .25rem; width: auto; }
  .lex-auth-card .lex-auth-input { display: block; width: 100%; height: auto; padding: .7rem .75rem; margin-bottom: .75rem; border: 1px solid #cdd3db; border-radius: 6px; background: #fff; color: #16181d; font-size: 1rem; }
  .lex-auth-card .lex-auth-input:focus { border-color: #16181d; box-shadow: none; outline: 2px solid #16181d; outline-offset: 1px; }
  .lex-auth-card .lex-auth-hint { margin: -.35rem 0 .75rem; font-size: .75rem; color: #5c6470; }
  .lex-auth-card .lex-auth-btn { display: block; width: 100%; min-height: 44px; height: auto; border-radius: 6px; background: #16181d; color: #fff !important; font-size: .9rem; font-weight: 600; text-align: center; cursor: pointer; padding: .75rem 1rem; }
  .lex-auth-card .lex-auth-btn:hover { background: #2c313a; }
  .lex-auth-card .lex-auth-btn[disabled] { opacity: .6; cursor: wait; }
  .lex-auth-card .lex-auth-links { display: flex; justify-content: space-between; align-items: center; margin-top: .75rem; }
  .lex-auth-card .lex-auth-links a, .lex-auth-card .lex-auth-linkbtn { padding: 0; width: auto; font-size: .78rem; color: #5c6470 !important; text-decoration: underline; cursor: pointer; }
  .lex-auth-card .lex-auth-links a:hover, .lex-auth-card .lex-auth-linkbtn:hover { color: #16181d !important; }
  .lex-auth-card .lex-auth-success { margin: .5rem 0 1rem; font-size: .95rem; font-weight: 600; color: #1b7f4b; }
  .lex-auth-card .lex-auth-foot { display: flex; gap: .4rem; justify-content: center; margin: 1.25rem 0 0; font-size: .72rem; color: #9aa1ab; }
  .lex-auth-card .lex-auth-foot a { color: #9aa1ab; text-decoration: underline; }
  .lex-auth-card .lex-auth-foot a:hover { color: #16181d; }
  @media (max-width: 480px) {
    .lex-auth-overlay { align-items: flex-end; padding: 0; }
    .lex-auth-card { max-width: none; border-radius: 12px 12px 0 0; padding-bottom: max(1.25rem, env(safe-area-inset-bottom)); }
  }
</style>

<script>
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
  var toggle = modal.querySelector('[data-auth-toggle]');
  var pageToken = modal.querySelector('input[name="_token"]').value;
  var freshToken = null;
  var returnTo = <?= json_encode($authModalReturnTo) ?>;
  // Locale-prefixed so the nav fetch does not eat a 308 on every login
  var authNavUrl = <?= json_encode(lurl('/auth/nav')) ?>;

  // Public pages come from the full-page guest cache, so the token baked
  // into the HTML can belong to another session. Fetch a live one before
  // posting. The same pattern consent.js uses on cached pages.
  function getToken() {
    if (freshToken) return Promise.resolve(freshToken);
    return fetch('/csrf-token', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { freshToken = j.token || pageToken; return freshToken; })
      .catch(function () { return pageToken; });
  }

  // Reader-first titles keyed by what the visitor was trying to do
  var titles = {
    login: 'Log in to continue',
    reply: 'Log in to reply',
    like: 'Log in to like this post',
    bookmark: 'Log in to save this post'
  };

  var state = { mode: 'login', email: '', onSuccess: null, lastFocus: null };

  function post(url, body) {
    return getToken().then(function (t) {
      var fd = new FormData();
      fd.append('_token', t);
      Object.keys(body).forEach(function (k) { fd.append(k, body[k]); });
      return fetch(url, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (r) {
        return r.json().then(function (j) { return { ok: r.ok && j.success, json: j }; });
      });
    });
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
      .catch(function () { /* leave the header alone, the next page load fixes it */ });
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
      sub.textContent = 'Enter your email to log in or create your reader account.';
      clearError();
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
    if (!email || email.indexOf('@') < 1) { showError('Please enter a valid email address.'); return; }

    post('/login/identify', { email: email }).then(function (res) {
      if (!res.ok) { showError(res.json.error || 'Something went wrong. Please try again.'); return; }
      state.email = email;
      var exists = !!(res.json.data && res.json.data.exists);
      state.mode = exists ? 'login' : 'register';

      if (exists) {
        title.textContent = 'Welcome back';
        sub.textContent = 'Enter the password for ' + email + '.';
        submitBtn.textContent = 'Log in →';
        passInput.setAttribute('autocomplete', 'current-password');
        hint.setAttribute('hidden', '');
        forgot.removeAttribute('hidden');
      } else {
        title.textContent = 'Create your account';
        sub.textContent = 'New here? Set a password and your reader account is ready.';
        submitBtn.textContent = 'Create account →';
        passInput.setAttribute('autocomplete', 'new-password');
        hint.removeAttribute('hidden');
        forgot.setAttribute('hidden', '');
      }

      passInput.value = '';
      showStep('password');
      passInput.focus();
    }).catch(function () { showError('Network error. Please try again.'); });
  });

  passForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearError();

    if (passInput.value === '') { showError('Password is required.'); return; }
    if (state.mode === 'register' && passInput.value.length < 6) {
      showError('Your password needs at least 6 characters.');
      return;
    }

    var url = state.mode === 'register' ? '/register/submit' : '/login/submit';
    submitBtn.disabled = true;

    post(url, { email: state.email, password: passInput.value, return_to: returnTo }).then(function (res) {
      submitBtn.disabled = false;
      if (!res.ok) { showError(res.json.error || 'Something went wrong. Please try again.'); return; }

      successMsg.textContent = state.mode === 'register'
        ? 'Welcome to Lexicon! Your reader account is ready.'
        : 'You’re logged in!';
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
    }).catch(function () {
      submitBtn.disabled = false;
      showError('Network error. Please try again.');
    });
  });

  // Forgot password without leaving the modal: the email is already known
  // from step 1, so one tap sends the reset link
  forgot.addEventListener('click', function (ev) {
    ev.preventDefault();
    clearError();
    title.textContent = 'Reset your password';
    forgotText.textContent = 'We’ll email a password reset link to ' + state.email + '.';
    forgotSend.removeAttribute('hidden');
    showStep('forgot');
  });

  forgotForm.addEventListener('submit', function (ev) {
    ev.preventDefault();
    clearError();
    forgotSend.disabled = true;

    post('/password/forgot', { email: state.email }).then(function (res) {
      forgotSend.disabled = false;
      if (!res.ok) { showError(res.json.error || 'Something went wrong. Please try again.'); return; }
      forgotText.textContent = (res.json.data && res.json.data.message) || 'If that email exists in our system, a reset link has been sent.';
      forgotSend.setAttribute('hidden', '');
    }).catch(function () {
      forgotSend.disabled = false;
      showError('Network error. Please try again.');
    });
  });

  modal.querySelector('[data-auth-forgot-back]').addEventListener('click', function () {
    clearError();
    title.textContent = 'Welcome back';
    showStep('password');
    passInput.focus();
  });

  toggle.addEventListener('click', function () {
    var show = passInput.type === 'password';
    passInput.type = show ? 'text' : 'password';
    toggle.textContent = show ? 'Hide' : 'Show';
    toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
  });

  modal.querySelector('[data-auth-back]').addEventListener('click', function () {
    clearError();
    title.textContent = titles.login;
    sub.textContent = 'Enter your email to log in or create your reader account.';
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

  // Guest reply forms: open the modal on submit and post the reply once
  // authenticated. The typed text never leaves the page. Server-side
  // session capture remains the no-JS fallback for the same forms.
  document.querySelectorAll('form.reply-form').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      window.LexiconAuth.open({
        reason: 'reply',
        onSuccess: function () {
          // The form's baked-in token may be stale on cached pages; the
          // live one from the login exchange is guaranteed to match
          var tokenField = form.querySelector('input[name="_token"]');
          if (tokenField && freshToken) tokenField.value = freshToken;
          form.submit();
        }
      });
    });
  });
})();
</script>
<?php } ?>
