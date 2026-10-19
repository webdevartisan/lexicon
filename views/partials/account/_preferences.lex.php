<?php
// Language, timezone and the sign-in address, shared by the /account/preferences
// page and the account dialog.
//
// The email change is a details block rather than a nested dialog: a dialog
// inside the account dialog stacks two backdrops, and this needs no script.
$fieldErrors = errors();
$emailErrors = $fieldErrors['new_email'] ?? [];
?>
{% include "partials/account/_flash.lex.php" %}

<div class="acct-head">
    <h2 class="acct-title"><?= e($t('account.preferences.heading')) ?></h2>
    <p class="acct-intro"><?= e($t('account.preferences.intro')) ?></p>
</div>

<section aria-labelledby="contact-heading">
    <h3 class="acct-section" id="contact-heading"><?= e($t('account.preferences.contactSection')) ?></h3>

    <p class="acct-label" id="email-label"><?= e($t('account.preferences.email')) ?></p>
    <p class="acct-value" aria-labelledby="email-label"><?= e($user['email']) ?></p>
    <p class="acct-hint"><?= e($t('account.preferences.emailHelp')) ?></p>

    <?php if ($pendingEmail !== null) { ?>
    <div class="acct-notice<?= $pendingEmail['is_expired'] ? ' is-warn' : '' ?>">
        <p><?= e($t(
            $pendingEmail['is_expired'] ? 'account.preferences.emailPendingExpired' : 'account.preferences.emailPending',
            ['email' => $pendingEmail['new_email']]
        )) ?></p>
        <form method="post" action="<?= e(lurl('/account/email/cancel')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="acct-btn"><?= e($t('account.preferences.cancelEmailChange')) ?></button>
        </form>
    </div>
    <?php } ?>

    <details class="acct-details"<?= $emailErrors !== [] ? ' open' : '' ?>>
        <summary class="acct-btn"><?= e($t('account.preferences.changeEmail')) ?></summary>
        <form method="post" action="<?= e(lurl('/account/email')) ?>" class="acct-form">
            <?= csrf_field() ?>
            <p class="acct-intro"><?= e($t('account.preferences.emailModalText')) ?></p>

            <div class="acct-field<?= $emailErrors !== [] ? ' is-invalid' : '' ?>">
                <label class="acct-label" for="new_email"><?= e($t('account.preferences.newEmail')) ?></label>
                <input class="acct-input" type="email" name="new_email" id="new_email" required
                       autocomplete="email" value="<?= e(old('new_email') ?? '') ?>"
                       <?= $emailErrors !== [] ? 'aria-invalid="true"' : '' ?> aria-describedby="new_email_errors">
                <div id="new_email_errors">
                    <?php foreach ($emailErrors as $message) { ?>
                    <p class="acct-error"><?= e($message) ?></p>
                    <?php } ?>
                </div>
            </div>

            <div class="acct-field">
                <label class="acct-label" for="current_password"><?= e($t('account.preferences.currentPassword')) ?></label>
                <input class="acct-input" type="password" name="current_password" id="current_password" required
                       autocomplete="current-password" aria-describedby="current_password_hint">
                <p class="acct-hint" id="current_password_hint"><?= e($t('account.preferences.currentPasswordHelp')) ?></p>
            </div>

            <div class="acct-actions">
                <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.preferences.emailModalSubmit')) ?></button>
            </div>
        </form>
    </details>
</section>

<form method="post" action="<?= e(lurl('/account/preferences/update')) ?>" class="acct-form">
    <?= csrf_field() ?>

    <h3 class="acct-section"><?= e($t('account.preferences.interfaceSection')) ?></h3>

    <div class="acct-grid">
        <div class="acct-field">
            <label class="acct-label" for="timezone"><?= e($t('account.preferences.timezone')) ?></label>
            <?php $tz = old('timezone') ?? $user['timezone']; ?>
            <select class="acct-input" name="timezone" id="timezone" aria-describedby="timezone_hint">
                <?php foreach ($timezones as $region => $zones) { ?>
                <optgroup label="<?= e($region) ?>">
                    <?php foreach ($zones as $zone) { ?>
                    <option value="<?= e($zone) ?>" <?= $tz === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
                    <?php } ?>
                </optgroup>
                <?php } ?>
            </select>
            <p class="acct-hint" id="timezone_hint"><?= e($t('account.preferences.timezoneHelp')) ?></p>
        </div>

        <div class="acct-field">
            <label class="acct-label" for="locale"><?= e($t('account.preferences.language')) ?></label>
            <?php $lc = old('locale') ?? $user['locale']; ?>
            <select class="acct-input" name="locale" id="locale">
                <?php foreach ($locales as $code => $label) { ?>
                <option value="<?= e($code) ?>" <?= $lc === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php } ?>
            </select>
        </div>
    </div>

    <div class="acct-actions">
        <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.preferences.save')) ?></button>
    </div>
</form>
