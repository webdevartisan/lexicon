<?php
// Blog-front auth modal, shared by every theme (resolved from global views/).
// Email-first: one email field decides between login and inline registration,
// so readers never leave the page they were reading. Server endpoints:
// POST /login/identify, /login/submit, /register/submit (AJAX/JSON branches).
// With JS off, every trigger keeps its normal link to the full login page.
if (!auth()->check()) {
    $authModalReturnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    ?>
<div class="lex-auth-overlay" id="lex-auth-modal" hidden role="dialog" aria-modal="true" aria-labelledby="lex-auth-title"
     data-return-to="<?= e($authModalReturnTo) ?>" data-nav-url="<?= e(lurl('/auth/nav')) ?>">
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

<script defer src="/assets/js/auth-modal.js"></script>
<?php } ?>
