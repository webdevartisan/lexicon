<?php
// Blog-front auth modal, shared by every theme (resolved from global views/).
// Email-first: one email field decides between login and inline registration,
// so readers never leave the page they were reading. Server endpoints:
// POST /login/identify, /login, /register/submit, /password/forgot (AJAX/JSON branches).
// With JS off, every trigger keeps its normal link to the full login page.
if (!auth()->check()) {
    $authModalReturnTo = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $authModalMin = password_length_bounds()['min'];
    $authModalAge = minimum_signup_age();
    // Text the script swaps in as the steps change. Ours, never user input.
    $authModalText = [];
    foreach (['titleLogin', 'titleReply', 'titleComment', 'titleLike', 'titleBookmark', 'intro', 'logIn', 'welcomeBack',
        'enterPasswordFor', 'createTitle', 'createIntro', 'createSubmit', 'welcomeNew', 'loggedIn', 'resetTitle',
        'resetIntro', 'resetSent', 'invalidEmail', 'passwordRequired', 'passwordTooShort', 'sessionExpired',
        'serverError', 'networkError', 'codeRequired'] as $authModalKey) {
        $authModalText[$authModalKey] = $t('authModal.'.$authModalKey);
    }
    foreach (['show', 'hide', 'checking', 'checkDone', 'checkFailed', 'twoFactorTitle', 'twoFactorSubtitle'] as $authModalKey) {
        $authModalText[$authModalKey] = $t('auth.'.$authModalKey);
    }
    ?>
<div class="lex-auth-overlay" id="lex-auth-modal" hidden role="dialog" aria-modal="true" aria-labelledby="lex-auth-title"
     data-return-to="<?= e($authModalReturnTo) ?>" data-nav-url="<?= e(lurl('/auth/nav')) ?>"
     data-identify-url="<?= e(lurl('/login/identify')) ?>" data-login-url="<?= e(lurl('/login')) ?>"
     data-register-url="<?= e(lurl('/register/submit')) ?>" data-forgot-url="<?= e(lurl('/password/forgot')) ?>"
     data-two-factor-url="<?= e(lurl('/login/two-factor')) ?>"
     data-captcha-url="/captcha/challenge"
     data-captcha-script="/assets/js/captcha.js?v=<?= (int) filemtime(ROOT_PATH.'/public/assets/js/captcha.js') ?>"
     data-captcha-worker="/assets/vendor/altcha/pbkdf2.js?v=<?= (int) filemtime(ROOT_PATH.'/public/assets/vendor/altcha/pbkdf2.js') ?>"
     data-text="<?= e((string) json_encode($authModalText)) ?>">
  <div class="lex-auth-card">
    <button type="button" class="lex-auth-close" data-auth-close aria-label="<?= e($t('authModal.close')) ?>">&times;</button>

    <h2 class="lex-auth-title" id="lex-auth-title"><?= e($t('authModal.titleLogin')) ?></h2>
    <p class="lex-auth-sub" data-auth-sub><?= e($t('authModal.intro')) ?></p>

    <p class="lex-auth-error" data-auth-error role="alert" hidden></p>

    <form data-auth-step="email" novalidate>
      <?= csrf_field_lazy() ?>
      <label class="lex-auth-label" for="lex-auth-email"><?= e($t('authModal.email')) ?></label>
      <input class="lex-auth-input" type="email" id="lex-auth-email" name="email"
             autocomplete="email" inputmode="email" required>
      <button class="lex-auth-btn" type="submit"><?= e($t('authModal.continue')) ?></button>
    </form>

    <form data-auth-step="password" hidden novalidate>
      <div class="lex-auth-labelrow">
        <label class="lex-auth-label" for="lex-auth-password"><?= e($t('authModal.password')) ?></label>
        <button class="lex-auth-toggle" type="button" data-auth-toggle aria-pressed="false"><?= e($t('auth.show')) ?></button>
      </div>
      <input class="lex-auth-input" type="password" id="lex-auth-password" name="password"
             autocomplete="current-password" data-min-length="<?= $authModalMin ?>" required>
      <p class="lex-auth-hint" data-auth-hint hidden><?= e($t('authModal.minLength', ['min' => $authModalMin])) ?></p>
      <div data-auth-register-only hidden>
        <label class="lex-auth-hint lex-auth-check">
          <input type="checkbox" data-auth-age value="1" data-error="<?= e($t('auth.ageRequired', ['age' => $authModalAge])) ?>">
          <?= e($t('auth.ageConfirm', ['age' => $authModalAge])) ?>
        </label>
      </div>
      <p class="lex-auth-status" data-captcha-status role="status" aria-live="polite" hidden>
        <span class="lex-auth-status-mark" aria-hidden="true"></span>
        <span data-captcha-text></span>
      </p>
      <button class="lex-auth-btn" type="submit" data-auth-submit><?= e($t('authModal.logIn')) ?></button>
      <div class="lex-auth-links">
        <a href="<?= e(lurl('/password/forgot')) ?>" data-auth-forgot><?= e($t('authModal.forgot')) ?></a>
        <button class="lex-auth-linkbtn" type="button" data-auth-back><?= e($t('authModal.differentEmail')) ?></button>
      </div>
    </form>

    <form data-auth-step="code" hidden novalidate>
      <label class="lex-auth-label" for="lex-auth-code"><?= e($t('auth.twoFactorCodeLabel')) ?></label>
      <input class="lex-auth-input" type="text" id="lex-auth-code" name="code"
             autocomplete="one-time-code" inputmode="text" autocapitalize="off" spellcheck="false" required>
      <p class="lex-auth-hint"><?= e($t('auth.twoFactorHint')) ?></p>
      <button class="lex-auth-btn" type="submit" data-auth-code-submit><?= e($t('auth.twoFactorSubmit')) ?></button>
      <div class="lex-auth-links">
        <button class="lex-auth-linkbtn" type="button" data-auth-back><?= e($t('authModal.differentEmail')) ?></button>
      </div>
    </form>

    <form data-auth-step="forgot" hidden novalidate>
      <p class="lex-auth-hint" data-auth-forgot-text style="margin: 0 0 .75rem;"></p>
      <button class="lex-auth-btn" type="submit" data-auth-forgot-send><?= e($t('authModal.sendReset')) ?></button>
      <div class="lex-auth-links">
        <button class="lex-auth-linkbtn" type="button" data-auth-forgot-back><?= e($t('authModal.backToLogin')) ?></button>
      </div>
    </form>

    <div data-auth-step="success" hidden>
      <p class="lex-auth-success" data-auth-success-msg><?= e($t('authModal.loggedIn')) ?></p>
    </div>

    <p class="lex-auth-foot" data-auth-foot-links>
      <a href="<?= e(lurl('/login').'?return_to='.urlencode($authModalReturnTo)) ?>"><?= e($t('authModal.fullPage')) ?></a>
      <a href="<?= e(lurl('/terms')) ?>"><?= e($t('footer.linkTerms')) ?></a>
      <span aria-hidden="true">&middot;</span>
      <a href="<?= e(lurl('/privacy')) ?>"><?= e($t('footer.linkPrivacy')) ?></a>
    </p>
    <p class="lex-auth-foot" data-auth-legal hidden>
      <?php
      // The sentence shape differs per language, so the locale owns it and the links go in as placeholders.
      echo $t('auth.legalNotice', [
          'terms' => '<a href="'.e(lurl('/terms')).'">'.e($t('auth.termsLink')).'</a>',
          'privacy' => '<a href="'.e(lurl('/privacy')).'">'.e($t('auth.privacyLink')).'</a>',
      ]);
      ?>
    </p>
  </div>
</div>

<style>
  .lex-auth-overlay { position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(15, 18, 20, .6); backdrop-filter: blur(2px); }
  .lex-auth-overlay[hidden] { display: none; }
  .lex-auth-card { position: relative; width: 100%; max-width: 380px; background: #fff; color: #16181d; border-radius: 10px; padding: 1.75rem 1.5rem 1.25rem; box-shadow: 0 24px 64px rgba(0, 0, 0, .35); font-family: inherit; }
  /* This modal is dropped into whatever stylesheet the host page already has,
     and several blog themes style bare `button` and `input[type="..."]`
     selectors that outrank plain classes: uppercase display buttons, forced
     input metrics, `color: ... !important`. Everything below is scoped to
     .lex-auth-card so it wins on specificity, and the two reset blocks strip
     what a host theme injects. The colour resets need !important to match. */
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
  /* The reset above also strips the native checkbox, so draw it back. */
  .lex-auth-card input[type="checkbox"] { appearance: auto; -webkit-appearance: checkbox; display: inline-block; width: 1.05rem; height: 1.05rem; padding: 0; flex: none; accent-color: #16181d; cursor: pointer; }
  .lex-auth-card .lex-auth-check { display: flex; align-items: center; gap: .5rem; cursor: pointer; }

  .lex-auth-card .lex-auth-close { position: absolute; top: .6rem; inset-inline-end: .75rem; font-size: 1.5rem; line-height: 1; color: #9aa1ab !important; cursor: pointer; padding: .25rem; width: auto; }
  .lex-auth-card .lex-auth-close:hover { color: #16181d !important; }
  .lex-auth-card .lex-auth-title { margin: 0 0 .35rem; font-size: 1.25rem; line-height: 1.25; color: #16181d; }
  .lex-auth-card .lex-auth-sub { margin: 0 0 1rem; font-size: .85rem; color: #5c6470; }
  .lex-auth-card .lex-auth-error { margin: 0 0 .75rem; padding: .5rem .65rem; border-radius: 6px; background: #fdecec; color: #b3261e; font-size: .8rem; }
  .lex-auth-card .lex-auth-label { display: block; font-size: .75rem; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #444b55; margin-bottom: .35rem; }
  .lex-auth-card .lex-auth-labelrow { display: flex; align-items: center; justify-content: space-between; }
  .lex-auth-card .lex-auth-toggle { font-size: .8125rem; color: #5c6470 !important; cursor: pointer; padding: .25rem; width: auto; }
  .lex-auth-card .lex-auth-input { display: block; width: 100%; height: auto; padding: .7rem .75rem; margin-bottom: .75rem; border: 1px solid #cdd3db; border-radius: 6px; background: #fff; color: #16181d; font-size: 1rem; }
  /* Focus thickens the bottom edge instead of drawing a ring, as the themes do. */
  .lex-auth-card .lex-auth-input:focus { border-color: #16181d; box-shadow: inset 0 -2px 0 #16181d; outline: none; }
  .lex-auth-card .lex-auth-status { display: flex; align-items: center; gap: .5rem; margin: 0 0 .75rem; font-size: .8rem; color: #5c6470; }
  .lex-auth-card .lex-auth-status[hidden] { display: none; }
  .lex-auth-card .lex-auth-status-mark { flex: none; width: .8rem; height: .8rem; border: 2px solid #cdd3db; border-top-color: #16181d; border-radius: 50%; animation: lex-auth-spin .8s linear infinite; }
  .lex-auth-card .lex-auth-status[data-state="done"] { color: #1b7f4b; }
  .lex-auth-card .lex-auth-status[data-state="done"] .lex-auth-status-mark { border-color: #1b7f4b; background: #1b7f4b; animation: none; }
  .lex-auth-card .lex-auth-status[data-state="failed"] { color: #b3261e; }
  .lex-auth-card .lex-auth-status[data-state="failed"] .lex-auth-status-mark { border-color: #b3261e; animation: none; }
  .lex-auth-card .lex-auth-btn[aria-busy="true"] { cursor: progress; }
  @keyframes lex-auth-spin { to { transform: rotate(360deg); } }
  @media (prefers-reduced-motion: reduce) { .lex-auth-card .lex-auth-status-mark { animation: none; border-top-color: #cdd3db; } }
  .lex-auth-card .lex-auth-hint { margin: -.35rem 0 .75rem; font-size: .75rem; color: #5c6470; }
  .lex-auth-card .lex-auth-btn { display: block; width: 100%; min-height: 44px; height: auto; border-radius: 6px; background: #16181d; color: #fff !important; font-size: .9rem; font-weight: 600; text-align: center; cursor: pointer; padding: .75rem 1rem; }
  .lex-auth-card .lex-auth-btn:hover { background: #2c313a; }
  .lex-auth-card .lex-auth-btn[disabled] { opacity: .6; cursor: wait; }
  .lex-auth-card .lex-auth-links { display: flex; justify-content: space-between; align-items: center; margin-top: .75rem; }
  .lex-auth-card .lex-auth-links a, .lex-auth-card .lex-auth-linkbtn { padding: 0; width: auto; font-size: .78rem; color: #5c6470 !important; text-decoration: underline; cursor: pointer; }
  .lex-auth-card .lex-auth-links a:hover, .lex-auth-card .lex-auth-linkbtn:hover { color: #16181d !important; }
  .lex-auth-card .lex-auth-success { margin: .5rem 0 1rem; font-size: .95rem; font-weight: 600; color: #1b7f4b; }
  .lex-auth-card .lex-auth-foot { display: flex; flex-wrap: wrap; gap: .4rem; justify-content: center; margin: 1.25rem 0 0; font-size: .75rem; color: #5c6470; text-align: center; }
  .lex-auth-card .lex-auth-foot[hidden] { display: none; }
  .lex-auth-card .lex-auth-foot[data-auth-legal]:not([hidden]) { display: block; }
  .lex-auth-card .lex-auth-foot a { color: #5c6470; text-decoration: underline; }
  .lex-auth-card .lex-auth-foot a:first-child { flex-basis: 100%; }
  .lex-auth-card .lex-auth-foot a:hover { color: #16181d; }
  @media (max-width: 480px) {
    .lex-auth-overlay { align-items: flex-end; padding: 0; }
    .lex-auth-card { max-width: none; border-radius: 12px 12px 0 0; padding-bottom: max(1.25rem, env(safe-area-inset-bottom)); }
  }
</style>

<script defer src="/assets/js/auth-modal.js?v=<?= (int) filemtime(ROOT_PATH.'/public/assets/js/auth-modal.js') ?>"></script>
<?php } ?>
