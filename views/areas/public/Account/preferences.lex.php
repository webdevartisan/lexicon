{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.preferences.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
$fieldErrors = errors();
$username = (string) ($user['username'] ?? '');
?>
<section class="lx-wrap lx-account">
    <?php $accountSection = 'preferences'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="lx-account-body">
        <header class="lx-account-head">
            <h1><?= e($t('account.preferences.heading')) ?></h1>
            <p class="lx-muted"><?= e($t('account.preferences.intro')) ?></p>
        </header>

        <form method="post" action="<?= e(lurl('/account/preferences/update')) ?>" class="lx-account-form" autocomplete="on">
            <?= csrf_field() ?>
            <?php
            // The username is here so a password manager can associate the
            // current-password field below with this account.
            ?>
            <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" class="lx-visually-hidden" aria-hidden="true" tabindex="-1" readonly>

            <h2 class="lx-account-section"><?= e($t('account.preferences.signInSection')) ?></h2>

            <div class="lx-field">
                <label class="lx-field__label" for="username_display"><?= e($t('account.preferences.username')) ?></label>
                <input class="lx-field__input" id="username_display" type="text" value="<?= e($username) ?>" disabled>
                <p class="lx-field__hint"><?= e($t('account.preferences.usernameDisabled')) ?></p>
            </div>

            <div class="lx-field<?= !empty($fieldErrors['email']) ? ' lx-field--invalid' : '' ?>">
                <label class="lx-field__label" for="email"><?= e($t('account.preferences.email')) ?></label>
                <input class="lx-field__input" type="email" name="email" id="email"
                       value="<?= e(old('email') ?? ($user['email'] ?? '')) ?>" autocomplete="email"
                       <?= !empty($fieldErrors['email']) ? 'aria-invalid="true" aria-describedby="email_error"' : 'aria-describedby="email_hint"' ?>>
                <p class="lx-field__hint" id="email_hint"><?= e($t('account.preferences.emailHelp')) ?></p>
                <?php foreach ($fieldErrors['email'] ?? [] as $message) { ?>
                <p class="lx-field__error" id="email_error"><?= e($message) ?></p>
                <?php } ?>
            </div>

            <div class="lx-field<?= !empty($fieldErrors['current_password']) ? ' lx-field--invalid' : '' ?>">
                <label class="lx-field__label" for="current_password"><?= e($t('account.preferences.currentPassword')) ?></label>
                <input class="lx-field__input" type="password" name="current_password" id="current_password"
                       autocomplete="current-password"
                       <?= !empty($fieldErrors['current_password']) ? 'aria-invalid="true" aria-describedby="cp_error"' : 'aria-describedby="cp_hint"' ?>>
                <p class="lx-field__hint" id="cp_hint"><?= e($t('account.preferences.currentPasswordHelp')) ?></p>
                <?php foreach ($fieldErrors['current_password'] ?? [] as $message) { ?>
                <p class="lx-field__error" id="cp_error"><?= e($message) ?></p>
                <?php } ?>
            </div>

            <h2 class="lx-account-section"><?= e($t('account.preferences.prefsSection')) ?></h2>

            <div class="lx-grid-2">
                <div class="lx-field">
                    <label class="lx-field__label" for="display_name"><?= e($t('account.preferences.displayName')) ?></label>
                    <?php $dn = old('display_name') ?? ($user['display_name'] ?? 'username'); ?>
                    <select class="lx-field__input" name="display_name" id="display_name">
                        <option value="name" <?= $dn === 'name' ? 'selected' : '' ?>><?= e($t('account.preferences.displayNameFull')) ?></option>
                        <option value="username" <?= $dn === 'username' ? 'selected' : '' ?>><?= e($t('account.preferences.displayNameUsername')) ?></option>
                    </select>
                </div>

                <div class="lx-field">
                    <label class="lx-field__label" for="default_visibility"><?= e($t('account.preferences.defaultVisibility')) ?></label>
                    <?php $dv = old('default_visibility') ?? ($user['default_visibility'] ?? 'public'); ?>
                    <select class="lx-field__input" name="default_visibility" id="default_visibility">
                        <option value="public" <?= $dv === 'public' ? 'selected' : '' ?>><?= e($t('account.preferences.visibilityPublic')) ?></option>
                        <option value="unlisted" <?= $dv === 'unlisted' ? 'selected' : '' ?>><?= e($t('account.preferences.visibilityUnlisted')) ?></option>
                        <option value="private" <?= $dv === 'private' ? 'selected' : '' ?>><?= e($t('account.preferences.visibilityPrivate')) ?></option>
                    </select>
                </div>
            </div>

            <div class="lx-grid-2">
                <div class="lx-field">
                    <label class="lx-field__label" for="timezone"><?= e($t('account.preferences.timezone')) ?></label>
                    <?php $tz = old('timezone') ?? ($user['timezone'] ?? 'UTC'); ?>
                    <select class="lx-field__input" name="timezone" id="timezone">
                        <?php foreach ($timezones as $region => $zones) { ?>
                        <optgroup label="<?= e($region) ?>">
                            <?php foreach ($zones as $zone) { ?>
                            <option value="<?= e($zone) ?>" <?= $tz === $zone ? 'selected' : '' ?>><?= e($zone) ?></option>
                            <?php } ?>
                        </optgroup>
                        <?php } ?>
                    </select>
                </div>

                <div class="lx-field">
                    <label class="lx-field__label" for="locale"><?= e($t('account.preferences.language')) ?></label>
                    <?php $lc = old('locale') ?? ($user['locale'] ?? 'auto'); ?>
                    <select class="lx-field__input" name="locale" id="locale">
                        <?php foreach ($locales as $code => $label) { ?>
                        <option value="<?= e($code) ?>" <?= $lc === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="lx-account-actions">
                <button type="submit" class="lx-btn lx-btn--primary"><?= e($t('account.preferences.save')) ?></button>
            </div>
        </form>

        <div class="lx-danger-zone">
            <h2><?= e($t('account.preferences.dangerHeading')) ?></h2>
            <p class="lx-muted"><?= e($t('account.preferences.dangerText')) ?></p>
            <a class="lx-btn lx-btn--danger lx-btn--fit" href="<?= e(lurl('/account/delete')) ?>"><?= e($t('account.preferences.deleteLink')) ?></a>
        </div>
    </div>
</section>
{% endblock %}
