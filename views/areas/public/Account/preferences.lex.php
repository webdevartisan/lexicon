{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.preferences.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
{% endblock %}

{% block body %}
<?php
$fieldErrors = errors();
$emailErrors = $fieldErrors['new_email'] ?? [];
?>
<section class="lx-wrap lx-account">
    <?php $accountSection = 'preferences'; ?>
    <header class="lx-account-head">
        <h1><?= e($t('account.preferences.heading')) ?></h1>
        <p class="lx-muted"><?= e($t('account.preferences.intro')) ?></p>
    </header>

    {% include "partials/_account_shell.lex.php" %}

    <div class="lx-account-body">
        <div class="lx-account-main">
            <section aria-labelledby="contact-heading">
                <h2 class="lx-account-section" id="contact-heading"><?= e($t('account.preferences.contactSection')) ?></h2>

                <div class="lx-readout">
                    <div class="lx-readout-text">
                        <span class="lx-field-label" id="email-label"><?= e($t('account.preferences.email')) ?></span>
                        <p class="lx-readout-value" aria-labelledby="email-label"><?= e($user['email']) ?></p>
                    </div>
                    <button type="button" class="lx-btn lx-btn-subtle lx-btn-sm" id="change-email-btn"
                            aria-haspopup="dialog"><?= e($t('account.preferences.changeEmail')) ?></button>
                </div>
                <p class="lx-field-hint"><?= e($t('account.preferences.emailHelp')) ?></p>

                <?php if ($pendingEmail !== null) { ?>
                <div class="lx-notice<?= $pendingEmail['is_expired'] ? ' lx-notice-warn' : '' ?>">
                    <p><?= e($t(
                        $pendingEmail['is_expired'] ? 'account.preferences.emailPendingExpired' : 'account.preferences.emailPending',
                        ['email' => $pendingEmail['new_email']]
                    )) ?></p>
                    <form method="post" action="<?= e(lurl('/account/email/cancel')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="lx-btn lx-btn-quiet lx-btn-sm"><?= e($t('account.preferences.cancelEmailChange')) ?></button>
                    </form>
                </div>
                <?php } ?>
            </section>

            <form method="post" action="<?= e(lurl('/account/preferences/update')) ?>" class="lx-account-form">
                <?= csrf_field() ?>

                <h2 class="lx-account-section"><?= e($t('account.preferences.interfaceSection')) ?></h2>

                <div class="lx-grid-2">
                    <div class="lx-field">
                        <label class="lx-field-label" for="timezone"><?= e($t('account.preferences.timezone')) ?></label>
                        <?php $tz = old('timezone') ?? $user['timezone']; ?>
                        <select class="lx-field-input" name="timezone" id="timezone" aria-describedby="timezone_hint">
                            <?php foreach ($timezones as $region => $zones) { ?>
                            <optgroup label="<?= e($region) ?>">
                                <?php foreach ($zones as $zone) { ?>
                                <option value="<?= e($zone) ?>" <?= $tz === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
                                <?php } ?>
                            </optgroup>
                            <?php } ?>
                        </select>
                        <p class="lx-field-hint" id="timezone_hint"><?= e($t('account.preferences.timezoneHelp')) ?></p>
                    </div>

                    <div class="lx-field">
                        <label class="lx-field-label" for="locale"><?= e($t('account.preferences.language')) ?></label>
                        <?php $lc = old('locale') ?? $user['locale']; ?>
                        <select class="lx-field-input" name="locale" id="locale">
                            <?php foreach ($locales as $code => $label) { ?>
                            <option value="<?= e($code) ?>" <?= $lc === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>

                <div class="lx-account-actions">
                    <button type="submit" class="lx-btn lx-btn-primary"><?= e($t('account.preferences.save')) ?></button>
                </div>
            </form>
        </div>

        <div class="lx-account-close">
            <h2><?= e($t('account.preferences.dangerHeading')) ?></h2>
            <p class="lx-muted"><?= e($t('account.preferences.dangerText')) ?></p>
            <a class="lx-btn lx-btn-danger-outline lx-btn-sm" href="<?= e(lurl('/account/delete')) ?>"><?= e($t('account.preferences.deleteLink')) ?></a>
        </div>
    </div>
</section>

<dialog id="email-dialog" class="lx-dialog" aria-labelledby="email-dialog-title" aria-describedby="email-dialog-text">
    <form method="post" action="<?= e(lurl('/account/email')) ?>" class="lx-dialog-form">
        <?= csrf_field() ?>
        <h2 class="lx-modal-title" id="email-dialog-title"><?= e($t('account.preferences.emailModalTitle')) ?></h2>
        <p class="lx-modal-text" id="email-dialog-text"><?= e($t('account.preferences.emailModalText')) ?></p>

        <div class="lx-field<?= $emailErrors !== [] ? ' lx-field-invalid' : '' ?>">
            <label class="lx-field-label" for="new_email"><?= e($t('account.preferences.newEmail')) ?></label>
            <input class="lx-field-input" type="email" name="new_email" id="new_email" required
                   autocomplete="email" value="<?= e(old('new_email') ?? '') ?>"
                   <?= $emailErrors !== [] ? 'aria-invalid="true"' : '' ?> aria-describedby="new_email_errors">
            <div id="new_email_errors">
                <?php foreach ($emailErrors as $message) { ?>
                <p class="lx-field-error"><?= e($message) ?></p>
                <?php } ?>
            </div>
        </div>

        <div class="lx-field">
            <label class="lx-field-label" for="current_password"><?= e($t('account.preferences.currentPassword')) ?></label>
            <input class="lx-field-input" type="password" name="current_password" id="current_password" required
                   autocomplete="current-password" aria-describedby="current_password_hint">
            <p class="lx-field-hint" id="current_password_hint"><?= e($t('account.preferences.currentPasswordHelp')) ?></p>
        </div>

        <div class="lx-modal-actions">
            <button type="button" class="lx-btn lx-btn-subtle" data-close><?= e($t('account.preferences.emailModalCancel')) ?></button>
            <button type="submit" class="lx-btn lx-btn-primary"><?= e($t('account.preferences.emailModalSubmit')) ?></button>
        </div>
    </form>
</dialog>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/libs/choices.js/public/assets/scripts/choices.min.js" nonce="<?= csp_nonce() ?>"></script>
<script nonce="<?= csp_nonce() ?>">
  document.addEventListener('DOMContentLoaded', function () {
    // Enhance the account selects with Choices so the dropdowns share one themed
    // look. Search is switched on only for long lists (the timezone picker).
    if (typeof Choices !== 'undefined') {
      document.querySelectorAll('.lx-account-form select').forEach(function (el) {
        new Choices(el, {
          shouldSort: false,
          allowHTML: false,
          searchEnabled: el.options.length > 10,
          itemSelectText: '',
          placeholder: false,
        });
      });
    }

    // showModal(), not the hidden-div pattern the older dialogs use: it traps
    // focus, closes on Escape and makes the rest of the page inert without us
    // reimplementing any of it.
    const dialog = document.getElementById('email-dialog');
    const trigger = document.getElementById('change-email-btn');
    if (!dialog || !trigger) return;

    trigger.addEventListener('click', function () { dialog.showModal(); });
    dialog.querySelectorAll('[data-close]').forEach(function (el) {
      el.addEventListener('click', function () { dialog.close(); });
    });
    // A click landing on the dialog element itself is the backdrop; anything
    // inside the form stops at the form.
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    dialog.addEventListener('close', function () { trigger.focus(); });

    // A rejected change comes back as a redirect with the errors in the session,
    // so reopen the dialog to show them where they were entered.
    <?php if ($emailErrors !== []) { ?>
    dialog.showModal();
    <?php } ?>
  });
</script>
{% endblock %}
