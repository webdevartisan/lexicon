{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.profile.nameHeading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'name'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        <?php
        $handle = (string) $user['handle'];
        $profileUrl = rtrim((string) ($profileUrlPrefix ?? ''), '/').'/'.$handle;
        ?>
        {% include "partials/account/_field.lex.php" %}

        <div class="acct-head">
            <h1 class="acct-title"><?= e($t('account.profile.nameHeading')) ?></h1>
            <p class="acct-intro"><?= e($t('account.profile.nameIntro')) ?></p>
        </div>

        <form method="post" action="<?= e(lurl('/account/profile/update')) ?>" class="acct-form">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="name">

            <h2 class="acct-section"><?= e($t('account.profile.nameSection')) ?></h2>
            <div class="acct-grid">
                <?php $field('first_name', $t('account.profile.firstName'), 'text', (string) ($user['first_name'] ?? '')); ?>
                <?php $field('last_name', $t('account.profile.lastName'), 'text', (string) ($user['last_name'] ?? '')); ?>
            </div>

            <h2 class="acct-section"><?= e($t('account.profile.tagSection')) ?></h2>
            <?php
            // The handle is the @tag as well as the URL, so the hint names the tag.
            $field('handle', $t('account.profile.tag'), 'text', $handle, $t('account.profile.tagPlaceholder'), $t('account.profile.tagHelp', ['tag' => $handle]));
            ?>
            <?php if (!empty($user['is_public'])) { ?>
            <p class="acct-hint">
                <?= e($t('account.profile.address', ['url' => $profileUrl])) ?>
                <a href="<?= e(lurl('/profile/'.$handle)) ?>" target="_blank" rel="noopener"><?= e($t('account.profile.openProfile')) ?></a>
            </p>
            <?php } else { ?>
            <p class="acct-hint">
                <?= e($t('account.profile.hidden')) ?>
                <a href="<?= e(lurl('/account/preferences')) ?>" data-panel-link><?= e($t('account.profile.changeVisibility')) ?></a>
            </p>
            <?php } ?>

            <label class="acct-check">
                <input type="checkbox" role="switch" class="acct-switch" name="show_name" value="1" <?= $user['show_name'] ? 'checked' : '' ?>>
                <span>
                    <span class="acct-check-title"><?= e($t('account.profile.showName')) ?></span>
                    <span class="acct-check-help"><?= e($t('account.profile.showNameHelp', ['tag' => $handle])) ?></span>
                </span>
            </label>

            <div class="acct-actions">
                <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.profile.save')) ?></button>
            </div>
        </form>
    </div>
</section>
{% endblock %}
