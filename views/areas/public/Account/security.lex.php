{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.security.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
$fieldErrors = errors();
$signInEmail = (string) ($user['email'] ?? '');

$passwordField = function (string $name, string $label, string $autocomplete) use ($fieldErrors): void {
    $invalid = !empty($fieldErrors[$name]);
    ?>
    <div class="lx-field<?= $invalid ? ' lx-field-invalid' : '' ?>">
        <label class="lx-field-label" for="<?= e($name) ?>"><?= e($label) ?></label>
        <input class="lx-field-input" type="password" name="<?= e($name) ?>" id="<?= e($name) ?>"
               autocomplete="<?= e($autocomplete) ?>"
               <?= $invalid ? 'aria-invalid="true" aria-describedby="'.e($name).'_error"' : '' ?> required>
        <?php foreach ($fieldErrors[$name] ?? [] as $message) { ?>
        <p class="lx-field-error" id="<?= e($name) ?>_error"><?= e($message) ?></p>
        <?php } ?>
    </div>
<?php };
?>
<section class="lx-wrap lx-account">
    <?php $accountSection = 'security'; ?>
    <header class="lx-account-head">
        <h1><?= e($t('account.security.heading')) ?></h1>
        <p class="lx-muted"><?= e($t('account.security.intro')) ?></p>
    </header>

    {% include "partials/_account_shell.lex.php" %}

    <div class="lx-account-body">
        <form method="post" action="<?= e(lurl('/account/security/password')) ?>" class="lx-account-form" autocomplete="on">
            <?= csrf_field() ?>
            <?php // The sign-in identifier, so a password manager files the new credential under it.?>
            <input type="text" name="username" value="<?= e($signInEmail) ?>" autocomplete="username" class="lx-visually-hidden" aria-hidden="true" tabindex="-1" readonly>

            <?php $passwordField('current_password', $t('account.security.currentPassword'), 'current-password'); ?>
            <div class="lx-grid-2">
                <?php $passwordField('new_password', $t('account.security.newPassword'), 'new-password'); ?>
                <?php $passwordField('new_password_confirm', $t('account.security.confirmPassword'), 'new-password'); ?>
            </div>

            <div class="lx-account-actions">
                <button type="submit" class="lx-btn lx-btn-primary"><?= e($t('account.security.save')) ?></button>
            </div>
        </form>

        <aside class="lx-account-aside">
            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.security.activityHeading')) ?></h2>
                <p class="lx-stat">
                    <span><?= e($t('account.security.lastLogin')) ?></span>
                    <strong><?= !empty($user['last_login']) ? e($user['last_login']) : e($t('account.security.never')) ?></strong>
                </p>
            </section>
            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.twoFactor.heading')) ?></h2>
                <p class="lx-stat">
                    <span><?= e($t('account.twoFactor.status')) ?></span>
                    <strong><?= !empty($user['two_factor_confirmed_at']) ? e($t('account.twoFactor.statusOn')) : e($t('account.twoFactor.statusOff')) ?></strong>
                </p>
                <a class="lx-btn lx-btn-subtle lx-btn-sm" href="<?= e(lurl('/account/security/two-factor')) ?>"><?= e($t('account.twoFactor.manage')) ?></a>
            </section>
        </aside>

        <section class="lx-account-main lx-sessions" aria-labelledby="sessions-heading">
            <div class="lx-sessions-head">
                <div>
                    <h2 class="lx-account-section" id="sessions-heading"><?= e($t('account.sessions.heading')) ?></h2>
                    <p class="lx-muted"><?= e($t('account.sessions.intro')) ?></p>
                </div>
                <?php if (count($sessions) > 1) { ?>
                <button type="button" class="lx-btn lx-btn-subtle lx-btn-sm" data-session-signout
                        data-action="<?= e(lurl('/account/security/sessions/sign-out-others')) ?>"
                        data-title="<?= e($t('account.sessions.othersTitle')) ?>"
                        data-text="<?= e($t('account.sessions.othersText')) ?>"><?= e($t('account.sessions.signOutOthers')) ?></button>
                <?php } ?>
            </div>

            <ul class="lx-sessions-list">
                <?php foreach ($sessions as $s) { ?>
                <li class="lx-session<?= $s['current'] ? ' is-current' : '' ?>">
                    <span class="lx-session-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <?php if ($s['device'] === 'mobile') { ?>
                            <rect x="6" y="2" width="12" height="20" rx="2"/><path d="M12 18h.01"/>
                        <?php } elseif ($s['device'] === 'tablet') { ?>
                            <rect x="4" y="2" width="16" height="20" rx="2"/><path d="M12 18h.01"/>
                        <?php } elseif ($s['device'] === 'desktop') { ?>
                            <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>
                        <?php } else { ?>
                            <circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                        <?php } ?>
                        </svg>
                    </span>
                    <div class="lx-session-body">
                        <p class="lx-session-client">
                            <?= e($s['client']) ?>
                            <?php if ($s['current']) { ?><span class="lx-badge lx-badge-on"><?= e($t('account.sessions.thisDevice')) ?></span><?php } ?>
                        </p>
                        <?php if ($s['place'] !== null || $s['ip'] !== null) { ?>
                        <p class="lx-session-meta">
                            <?php if ($s['place'] !== null) { ?><span><?= e($s['place']) ?></span><?php } ?>
                            <?php if ($s['ip'] !== null) { ?><span dir="ltr"><?= e($s['ip']) ?></span><?php } ?>
                        </p>
                        <?php } ?>
                        <p class="lx-session-meta">
                            <span><?= e($t('account.sessions.signedIn', ['date' => $s['signed_in']])) ?></span>
                            <span><?= $s['active_now'] ? e($t('account.sessions.activeNow')) : e($t('account.sessions.lastActive', ['date' => $s['last_active']])) ?></span>
                        </p>
                    </div>
                    <button type="button" class="lx-btn lx-btn-subtle lx-btn-sm" data-session-signout
                            data-action="<?= e(lurl('/account/security/sessions/'.$s['hash'].'/sign-out')) ?>"
                            data-title="<?= e($t($s['current'] ? 'account.sessions.currentTitle' : 'account.sessions.oneTitle')) ?>"
                            data-text="<?= e($s['current'] ? $t('account.sessions.currentText') : $t('account.sessions.oneText', ['device' => $s['client']])) ?>"
                            aria-label="<?= e($t('account.sessions.signOutLabel', ['device' => $s['client']])) ?>"><?= e($t('account.sessions.signOut')) ?></button>
                </li>
                <?php } ?>
            </ul>

            <?php if (count($sessions) === 1) { ?>
            <p class="lx-muted lx-sessions-note"><?= e($t('account.sessions.onlyDevice')) ?></p>
            <?php } ?>
            <p class="lx-field-hint lx-sessions-note">
                <?= e($t('account.sessions.approximate')) ?>
                <a href="https://db-ip.com" rel="noopener" target="_blank">IP Geolocation by DB-IP</a>
            </p>
        </section>
    </div>
</section>

<dialog id="session-dialog" class="lx-dialog" aria-labelledby="session-dialog-title" aria-describedby="session-dialog-text">
    <form method="post" action="" class="lx-dialog-form">
        <?= csrf_field() ?>
        <h2 class="lx-modal-title" id="session-dialog-title"></h2>
        <p class="lx-modal-text" id="session-dialog-text"></p>

        <?php if ($needsPassword) { ?>
        <div class="lx-field">
            <label class="lx-field-label" for="session_password"><?= e($t('account.security.currentPassword')) ?></label>
            <input class="lx-field-input" type="password" name="current_password" id="session_password" required
                   autocomplete="current-password" aria-describedby="session_password_hint">
            <p class="lx-field-hint" id="session_password_hint"><?= e($t('account.sessions.passwordHint')) ?></p>
        </div>
        <?php } ?>

        <div class="lx-modal-actions">
            <button type="button" class="lx-btn lx-btn-subtle" data-close><?= e($t('account.sessions.cancel')) ?></button>
            <button type="submit" class="lx-btn lx-btn-primary"><?= e($t('account.sessions.signOut')) ?></button>
        </div>
    </form>
</dialog>
{% endblock %}

{% block scripts %}
<script nonce="<?= csp_nonce() ?>">
  document.addEventListener('DOMContentLoaded', function () {
    const dialog = document.getElementById('session-dialog');
    if (!dialog) return;

    const form = dialog.querySelector('form');
    const title = document.getElementById('session-dialog-title');
    const text = document.getElementById('session-dialog-text');
    let opener = null;

    // One dialog for every row: each button carries its own target and wording.
    document.querySelectorAll('[data-session-signout]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        opener = btn;
        form.action = btn.dataset.action;
        title.textContent = btn.dataset.title;
        text.textContent = btn.dataset.text;
        dialog.showModal();
      });
    });

    dialog.querySelectorAll('[data-close]').forEach(function (el) {
      el.addEventListener('click', function () { dialog.close(); });
    });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) dialog.close(); });
    dialog.addEventListener('close', function () { if (opener) opener.focus(); });
  });
</script>
{% endblock %}
