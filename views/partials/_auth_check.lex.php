<?php
// Sign-in check inside a full-page auth form. The form itself carries the
// challenge in data-captcha; captcha.js solves it and fills the hidden field.
// Set before including: $authCheckNoscript (text), $authCheckDone (text),
// $authCheckReset (true adds the password reset link to the no-JS note).
?>
<noscript>
    <p class="lx-authalert" role="alert">
        <?= e($authCheckNoscript) ?>
        <?php if (!empty($authCheckReset)) { ?>
            <a href="<?= e(lurl('/password/forgot')) ?>"><?= e($t('auth.resetInstead')) ?></a>
        <?php } ?>
    </p>
</noscript>
<input type="hidden" name="altcha" value="">
<p class="lx-check" data-captcha-status role="status" aria-live="polite" hidden
   data-checking="<?= e($t('auth.checking')) ?>"
   data-done="<?= e($authCheckDone) ?>"
   data-failed="<?= e($t('auth.checkFailed')) ?>">
    <span class="lx-check-mark" aria-hidden="true"></span>
    <span data-captcha-text></span>
</p>
<script src="/assets/js/captcha.js?v=<?= (int) filemtime(ROOT_PATH.'/public/assets/js/captcha.js') ?>"
        data-worker="/assets/vendor/altcha/pbkdf2.js?v=<?= (int) filemtime(ROOT_PATH.'/public/assets/vendor/altcha/pbkdf2.js') ?>" defer></script>
