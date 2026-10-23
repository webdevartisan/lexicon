<?php
/**
 * Strip shown on the front while the signed-in account has not confirmed its
 * email address, with a button that mails the link again.
 *
 * No early return: includes are inlined into the layout at compile time, so a
 * return here would end the whole page.
 */
$verifyUser = auth()->user();
if (email_unverified($verifyUser)) {
?>
<link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/verify-banner.css')) ?>">
<div class="lx-verify" role="region" aria-label="<?= e(chrome_translate('auth.verifyResend')) ?>">
    <p class="lx-verify-text"><?= e(chrome_translate('auth.verifyBanner', ['email' => $verifyUser['email']])) ?></p>
    <form method="post" action="<?= e(lurl('/account/verify-email/resend')) ?>" class="lx-verify-form">
        <?= csrf_field() ?>
        <button type="submit" class="lx-verify-resend"><?= e(chrome_translate('auth.verifyResend')) ?></button>
    </form>
</div>
<?php } ?>
