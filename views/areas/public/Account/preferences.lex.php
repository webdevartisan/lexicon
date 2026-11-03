{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.preferences.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'preferences'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        <div class="acct-head">
            <h1 class="acct-title"><?= e($t('account.preferences.heading')) ?></h1>
            <p class="acct-intro"><?= e($t('account.preferences.intro')) ?></p>
        </div>

        <section class="acct-appearance" aria-labelledby="appearance-heading">
            <h2 class="acct-section" id="appearance-heading"><?= e($t('layout.themeMenu.label')) ?></h2>
            <p class="acct-hint"><?= e($t('account.preferences.appearanceHelp')) ?></p>
            <?php $themeChoicesLabelled = true; ?>
            {% include "partials/_theme_choices.lex.php" %}
            <?php $themeChoicesLabelled = false; ?>
        </section>

        <form method="post" action="<?= e(lurl('/account/preferences/update')) ?>" class="acct-form">
            <?= csrf_field() ?>

            <h2 class="acct-section"><?= e($t('account.preferences.interfaceSection')) ?></h2>

            <div class="acct-grid">
                <div class="acct-field">
                    <label class="acct-label" for="timezone"><?= e($t('account.preferences.timezone')) ?></label>
                    <?php $tz = old('timezone') ?? $user['timezone']; ?>
                    <select class="acct-input" name="timezone" id="timezone" aria-describedby="timezone_hint">
                        <option value="auto" <?= $tz === 'auto' ? 'selected' : '' ?>><?= e($deviceZone === null
                            ? $t('account.preferences.timezoneAuto')
                            : $t('account.preferences.timezoneAutoNow', ['zone' => \App\Helpers\TimezoneHelper::label($deviceZone)])) ?></option>
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

            <h2 class="acct-section"><?= e($t('account.preferences.privacySection')) ?></h2>

            <label class="acct-check">
                <input type="checkbox" role="switch" class="acct-switch" name="is_public" value="1" <?= $user['is_public'] ? 'checked' : '' ?>>
                <span>
                    <span class="acct-check-title"><?= e($t('account.profile.makePublic')) ?></span>
                    <span class="acct-check-help"><?= e($t('account.profile.makePublicHelp')) ?></span>
                </span>
            </label>

            <div class="acct-actions">
                <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.preferences.save')) ?></button>
            </div>
        </form>
    </div>
</section>
{% endblock %}

{% block scripts %}
<script src="<?= e(versioned_asset('/cp-assets/js/select-menu.js')) ?>" defer></script>
{% endblock %}
