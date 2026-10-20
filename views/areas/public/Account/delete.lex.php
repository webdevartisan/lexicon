{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.delete.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'delete'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        <?php
        // The last step is a details block: the password and the tick box sit behind a
        // deliberate click, and it needs no script.
        $fieldErrors = errors();
        ?>

        <div class="acct-head">
            <h2 class="acct-title"><?= e($t('account.delete.heading')) ?></h2>
            <p class="acct-intro"><?= e($t('account.delete.subtitle')) ?></p>
        </div>

        <?php if (empty($canDelete)) { ?>
        <section class="acct-card acct-card-danger">
            <p><strong><?= e($t('account.delete.cannotDeleteStrong')) ?></strong></p>
            <?php if ($blockers['last_administrator']) { ?>
            <p class="acct-intro"><?= e($t('account.delete.blockedLastAdmin')) ?></p>
            <?php } ?>
            <?php if ($blockers['reported_content']) { ?>
            <p class="acct-intro"><?= e($t('account.delete.blockedReportedContent')) ?></p>
            <?php } ?>
            <?php if ($blockers['shared_blogs'] !== []) { ?>
            <p class="acct-intro"><?= e($t('account.delete.blockedSharedBlogs')) ?></p>
            <ul class="acct-list">
                <?php foreach ($blockers['shared_blogs'] as $sharedBlog) { ?>
                <li>
                    <?= e($sharedBlog['blog_name']) ?> &middot;
                    <a href="<?= e(lurl('/dashboard/blog/'.$sharedBlog['id'].'/team')) ?>"><?= e($t('account.delete.transferBlog')) ?></a>
                </li>
                <?php } ?>
            </ul>
            <?php } ?>
        </section>
        <?php } else { ?>
        <section class="acct-card acct-card-danger">
            <p><strong><?= e($t('account.delete.warningStrong')) ?></strong> <?= e($t('account.delete.warningText')) ?></p>
        </section>

        <h3 class="acct-section"><?= e($t('account.delete.removedHeading')) ?></h3>
        <ul class="acct-list">
            <li><?= e($t('account.delete.removedAccount')) ?></li>
            <li><?= e($t('account.delete.removedTiming', ['days' => erasure_grace_period_days()])) ?></li>
            <li><?= e($t('account.delete.removedContent')) ?></li>
            <li><?= e($t('account.delete.removedIdentity')) ?></li>
            <li><?= e($t('account.delete.removedFiles')) ?></li>
        </ul>

        <details class="acct-details"<?= !empty($fieldErrors['password']) ? ' open' : '' ?>>
            <summary class="acct-btn acct-btn-danger"><?= e($t('account.delete.submit')) ?></summary>
            <form method="post" action="<?= e(lurl('/account/delete')) ?>" class="acct-form">
                <?= csrf_field() ?>
                <p class="acct-label"><?= e($t('account.delete.confirmModalTitle')) ?></p>
                <p class="acct-intro"><?= e($t('account.delete.confirmModalText')) ?></p>

                <div class="acct-field<?= !empty($fieldErrors['password']) ? ' is-invalid' : '' ?>">
                    <label class="acct-label" for="password"><?= e($t('account.delete.confirmPassword')) ?></label>
                    <input class="acct-input" type="password" name="password" id="password"
                           autocomplete="current-password" placeholder="<?= e($t('account.delete.confirmPasswordPlaceholder')) ?>"
                           aria-describedby="pw_hint" required>
                    <p class="acct-hint" id="pw_hint"><?= e($t('account.delete.confirmPasswordHelp')) ?></p>
                    <?php foreach ($fieldErrors['password'] ?? [] as $message) { ?>
                    <p class="acct-error"><?= e($message) ?></p>
                    <?php } ?>
                </div>

                <label class="acct-check">
                    <input type="checkbox" required>
                    <span class="acct-check-title"><?= e($t('account.delete.confirmCheckbox')) ?></span>
                </label>

                <div class="acct-actions">
                    <button type="submit" class="acct-btn acct-btn-danger"><?= e($t('account.delete.submit')) ?></button>
                </div>
            </form>
        </details>
        <?php } ?>
    </div>
</section>
{% endblock %}
