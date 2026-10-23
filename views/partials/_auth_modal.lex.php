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
     data-captcha-script="<?= e(versioned_asset('/assets/js/captcha.js')) ?>"
     data-captcha-worker="<?= e(versioned_asset('/assets/vendor/altcha/pbkdf2.js')) ?>"
     data-text="<?= e((string) json_encode($authModalText)) ?>">
  <div class="lex-auth-card">
    <button type="button" class="panel-dialog-close" data-auth-close aria-label="<?= e($t('authModal.close')) ?>">&times;</button>

    <div class="acct-head">
      <h2 class="acct-title" id="lex-auth-title"><?= e($t('authModal.titleLogin')) ?></h2>
      <p class="acct-intro" data-auth-sub><?= e($t('authModal.intro')) ?></p>
    </div>

    <p class="panel-flash is-error" data-auth-error role="alert" hidden></p>

    <form data-auth-step="email" novalidate>
      <?= csrf_field_lazy() ?>
      <div class="acct-field">
        <label class="acct-label" for="lex-auth-email"><?= e($t('authModal.email')) ?></label>
        <input class="acct-input" type="email" id="lex-auth-email" name="email"
               autocomplete="email" inputmode="email" required>
      </div>
      <button class="acct-btn acct-btn-primary lex-auth-submit" type="submit"><?= e($t('authModal.continue')) ?></button>
    </form>

    <form data-auth-step="password" hidden novalidate>
      <div class="acct-field">
        <div class="lex-auth-labelrow">
          <label class="acct-label" for="lex-auth-password"><?= e($t('authModal.password')) ?></label>
          <button class="lex-auth-toggle" type="button" data-auth-toggle aria-pressed="false"><?= e($t('auth.show')) ?></button>
        </div>
        <input class="acct-input" type="password" id="lex-auth-password" name="password"
               autocomplete="current-password" data-min-length="<?= $authModalMin ?>" required>
        <p class="acct-hint" data-auth-hint hidden><?= e($t('authModal.minLength', ['min' => $authModalMin])) ?></p>
      </div>
      <div class="acct-field" data-auth-register-only hidden>
        <label class="acct-check acct-hint">
          <input type="checkbox" data-auth-age value="1" data-error="<?= e($t('auth.ageRequired', ['age' => $authModalAge])) ?>">
          <?= e($t('auth.ageConfirm', ['age' => $authModalAge])) ?>
        </label>
      </div>
      <p class="lex-auth-status" data-captcha-status role="status" aria-live="polite" hidden>
        <span class="lex-auth-status-mark" aria-hidden="true"></span>
        <span data-captcha-text></span>
      </p>
      <button class="acct-btn acct-btn-primary lex-auth-submit" type="submit" data-auth-submit><?= e($t('authModal.logIn')) ?></button>
      <div class="lex-auth-links">
        <a href="<?= e(lurl('/password/forgot')) ?>" data-auth-forgot><?= e($t('authModal.forgot')) ?></a>
        <button class="lex-auth-linkbtn" type="button" data-auth-back><?= e($t('authModal.differentEmail')) ?></button>
      </div>
    </form>

    <form data-auth-step="code" hidden novalidate>
      <div class="acct-field">
        <label class="acct-label" for="lex-auth-code"><?= e($t('auth.twoFactorCodeLabel')) ?></label>
        <input class="acct-input" type="text" id="lex-auth-code" name="code"
               autocomplete="one-time-code" inputmode="text" autocapitalize="off" spellcheck="false" required>
        <p class="acct-hint"><?= e($t('auth.twoFactorHint')) ?></p>
      </div>
      <button class="acct-btn acct-btn-primary lex-auth-submit" type="submit" data-auth-code-submit><?= e($t('auth.twoFactorSubmit')) ?></button>
      <div class="lex-auth-links">
        <button class="lex-auth-linkbtn" type="button" data-auth-back><?= e($t('authModal.differentEmail')) ?></button>
      </div>
    </form>

    <form data-auth-step="forgot" hidden novalidate>
      <p class="acct-intro" data-auth-forgot-text></p>
      <button class="acct-btn acct-btn-primary lex-auth-submit" type="submit" data-auth-forgot-send><?= e($t('authModal.sendReset')) ?></button>
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

<script defer src="<?= e(versioned_asset('/assets/js/auth-modal.js')) ?>"></script>
<?php } ?>
