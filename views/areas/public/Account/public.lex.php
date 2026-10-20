{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.profile.publicPageSection')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'public'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        <?php
        $handle = (string) $user['handle'];
        $isPublic = !empty($user['is_public']);
        ?>
        {% include "partials/account/_field.lex.php" %}

        <div class="acct-head">
            <h2 class="acct-title"><?= e($t('account.profile.publicPageSection')) ?></h2>
        </div>

        <form method="post" action="<?= e(lurl('/account/profile/update')) ?>" class="acct-form">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="public">
            <?php
            // The handle is the @tag as well as the URL, so the hint names the tag.
            $field('handle', $t('account.profile.tag'), 'text', $handle, $t('account.profile.tagPlaceholder'), $t('account.profile.tagHelp', ['tag' => $handle]));
            ?>

            <label class="acct-check">
                <input type="checkbox" role="switch" class="acct-switch" name="show_name" value="1" <?= $user['show_name'] ? 'checked' : '' ?>>
                <span>
                    <span class="acct-check-title"><?= e($t('account.profile.showName')) ?></span>
                    <span class="acct-check-help"><?= e($t('account.profile.showNameHelp', ['tag' => $handle])) ?></span>
                </span>
            </label>

            <label class="acct-check">
                <input type="checkbox" role="switch" class="acct-switch" name="is_public" value="1" <?= $isPublic ? 'checked' : '' ?>>
                <span>
                    <span class="acct-check-title"><?= e($t('account.profile.makePublic')) ?></span>
                    <span class="acct-check-help"><?= e($t('account.profile.makePublicHelp')) ?></span>
                </span>
            </label>

            <div class="acct-actions">
                <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.profile.save')) ?></button>
            </div>
        </form>

        <section class="acct-card">
            <h3 class="acct-card-title"><?= e($t('account.profile.publicPageHeading')) ?>
                <span class="acct-badge<?= $isPublic ? ' is-on' : '' ?>"><?= e($isPublic ? $t('account.profile.statusLive') : $t('account.profile.statusHidden')) ?></span>
            </h3>
            <p class="acct-hint"><?= e(rtrim((string) ($profileUrlPrefix ?? ''), '/').'/'.$handle) ?></p>
            <p><a class="acct-btn" href="<?= e(lurl('/profile/'.$handle)) ?>" target="_blank" rel="noopener"><?= e($t('account.profile.viewPublicProfile')) ?></a></p>
            <?php if (!$isPublic) { ?>
            <p class="acct-hint"><?= e($t('account.profile.makePublicHelp')) ?></p>
            <?php } ?>
        </section>
    </div>
</section>
{% endblock %}
