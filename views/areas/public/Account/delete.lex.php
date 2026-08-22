{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.delete.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php $fieldErrors = errors(); ?>
<section class="lx-wrap lx-account-narrow">
    <header class="lx-account-head">
        <h1><?= e($t('account.delete.heading')) ?></h1>
        <p class="lx-muted"><?= e($t('account.delete.subtitle')) ?></p>
    </header>

    <?php if (empty($canDelete)) { ?>
    <div class="lx-danger-zone">
        <p><strong><?= e($t('account.delete.cannotDeleteStrong')) ?></strong> <?= e($deleteReason ?? '') ?></p>
        <a class="lx-btn lx-btn--ghost lx-btn--fit" href="<?= e(lurl('/account/preferences')) ?>"><?= e($t('account.delete.backToPreferences')) ?></a>
    </div>
    <?php } else { ?>

    <div class="lx-danger-zone">
        <p><strong><?= e($t('account.delete.warningStrong')) ?></strong> <?= e($t('account.delete.warningText')) ?></p>
    </div>

    <h2 class="lx-account-section"><?= e($t('account.delete.removedHeading')) ?></h2>
    <ul class="lx-delete-list">
        <li><?= e($t('account.delete.removedAccount')) ?></li>
        <li><?= e($t('account.delete.removedIdentity')) ?></li>
        <li><?= e($t('account.delete.removedFiles')) ?></li>
        <li><?= e($t('account.delete.keptPostsComments')) ?></li>
    </ul>

    <form method="post" action="<?= e(lurl('/account/delete')) ?>" id="deleteForm" class="lx-account-form">
        <?= csrf_field() ?>

        <div class="lx-field<?= !empty($fieldErrors['password']) ? ' lx-field--invalid' : '' ?>">
            <label class="lx-field__label" for="password"><?= e($t('account.delete.confirmPassword')) ?></label>
            <input class="lx-field__input" type="password" name="password" id="password"
                   autocomplete="current-password" placeholder="<?= e($t('account.delete.confirmPasswordPlaceholder')) ?>"
                   aria-describedby="pw_hint" required>
            <p class="lx-field__hint" id="pw_hint"><?= e($t('account.delete.confirmPasswordHelp')) ?></p>
        </div>

        <label class="lx-check">
            <input type="checkbox" id="confirmCheck" required>
            <span class="lx-check-title"><?= e($t('account.delete.confirmCheckbox')) ?></span>
        </label>

        <div class="lx-account-actions lx-account-actions--split">
            <a class="lx-btn lx-btn--ghost lx-btn--fit" href="<?= e(lurl('/account/preferences')) ?>"><?= e($t('account.delete.cancel')) ?></a>
            <button type="submit" class="lx-btn lx-btn--danger"><?= e($t('account.delete.submit')) ?></button>
        </div>
    </form>
    <?php } ?>
</section>
{% endblock %}

{% block scripts %}
<script nonce="<?= csp_nonce() ?>">
  // Belt-and-braces confirmation on top of the required checkbox, since deletion
  // cannot be undone.
  document.getElementById('deleteForm')?.addEventListener('submit', function (e) {
    const check = document.getElementById('confirmCheck');
    const pw = document.getElementById('password');
    if (!check?.checked || !pw?.value) { return; }
    if (!confirm('This permanently closes your account. Continue?')) { e.preventDefault(); }
  });
</script>
{% endblock %}
