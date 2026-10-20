{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.profile.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<section class="lx-wrap lx-account">
    <?php $accountSection = 'profile'; ?>
    {% include "partials/_account_shell.lex.php" %}

    <div class="acct-page">
        <?php
        $handle = (string) $user['handle'];
        $hasAvatar = !empty($user['avatar_url']);
        $hasSource = !empty($user['avatar_source_url']);
        ?>
        {% include "partials/account/_field.lex.php" %}

        <div class="acct-head">
            <h2 class="acct-title"><?= e($t('account.profile.heading')) ?></h2>
            <p class="acct-intro"><?= e($t('account.profile.intro')) ?></p>
        </div>

        <section class="acct-avatar" data-account-profile data-crop-error="<?= e($t('account.flash.avatarError')) ?>">
            <div class="acct-avatar-box" id="avatar-box">
                <?php if ($hasAvatar) { ?>
                <img id="avatar-preview" class="acct-avatar-img" src="<?= e($user['avatar_url']) ?>" alt="">
                <?php } else { ?>
                <div id="avatar-placeholder" class="acct-avatar-initials"><?= e($user['initials'] ?? '?') ?></div>
                <?php } ?>
            </div>

            <div class="acct-avatar-side">
                <h3 class="acct-card-title"><?= e($t('account.profile.avatarHeading')) ?></h3>

                <form id="avatar-form" method="post" action="<?= e(lurl('/account/profile/avatar')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input id="avatar-input" name="avatar" type="file" class="acct-sr-only"
                           accept="image/jpeg,image/png,image/webp" data-max-size="20971520">
                    <div class="acct-avatar-controls">
                        <label for="avatar-input" class="acct-btn" id="avatar-upload-btn"><?= e($t('account.profile.changeAvatar')) ?></label>
                        <button type="button" id="recrop-avatar-btn" class="acct-btn"
                                data-source="<?= e($user['avatar_source_url'] ?? '') ?>" data-crop="<?= e($user['avatar_crop'] ?? '') ?>"
                                <?= $hasSource ? '' : 'hidden' ?>><?= e($t('account.profile.recropAvatar')) ?></button>
                    </div>
                    <p id="upload-loading" class="acct-hint" hidden><?= e($t('account.profile.uploading')) ?></p>
                </form>

                <details class="acct-details" id="remove-avatar-box"<?= $hasAvatar ? '' : ' hidden' ?>>
                    <summary class="acct-btn acct-btn-danger"><?= e($t('account.profile.removeAvatar')) ?></summary>
                    <form method="post" action="<?= e(lurl('/account/profile/avatar/remove')) ?>">
                        <?= csrf_field() ?>
                        <p class="acct-intro"><?= e($t('account.profile.removeAvatarConfirmText')) ?></p>
                        <button type="submit" class="acct-btn acct-btn-danger"><?= e($t('account.profile.removeAvatar')) ?></button>
                    </form>
                </details>

                <?php foreach ($fieldErrors['avatar'] ?? [] as $message) { ?>
                <p class="acct-error"><?= e($message) ?></p>
                <?php } ?>
            </div>
        </section>

        <dialog id="avatar-cropper-modal" class="acct-crop" aria-labelledby="avatar-cropper-title">
            <h3 class="acct-card-title" id="avatar-cropper-title"><?= e($t('account.profile.cropTitle')) ?></h3>
            <div class="acct-crop-stage">
                <img id="avatar-cropper-image" alt="">
            </div>
            <div class="acct-actions">
                <button type="button" class="acct-btn" data-crop-close><?= e($t('account.profile.cropCancel')) ?></button>
                <button type="button" id="avatar-cropper-apply" class="acct-btn acct-btn-primary"><?= e($t('account.profile.cropApply')) ?></button>
            </div>
        </dialog>

        <form method="post" action="<?= e(lurl('/account/profile/update')) ?>" class="acct-form">
            <?= csrf_field() ?>
            <input type="hidden" name="section" value="profile">

            <h3 class="acct-section"><?= e($t('account.profile.nameSection')) ?></h3>
            <div class="acct-grid">
                <?php $field('first_name', $t('account.profile.firstName'), 'text', (string) ($user['first_name'] ?? '')); ?>
                <?php $field('last_name', $t('account.profile.lastName'), 'text', (string) ($user['last_name'] ?? '')); ?>
            </div>

            <h3 class="acct-section"><?= e($t('account.profile.aboutSection')) ?></h3>
            <div class="acct-grid">
                <?php $field('occupation', $t('account.profile.occupation'), 'text', (string) ($user['occupation'] ?? ''), $t('account.profile.occupationPlaceholder')); ?>
                <?php $field('location', $t('account.profile.location'), 'text', (string) ($user['location'] ?? '')); ?>
            </div>
            <?php $field('bio', $t('account.profile.bio'), 'textarea', (string) ($user['bio'] ?? '')); ?>

            <div class="acct-actions">
                <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.profile.save')) ?></button>
            </div>
        </form>

        <section class="acct-card">
            <h3 class="acct-card-title"><?= e($t('account.profile.activityHeading')) ?></h3>
            <dl class="acct-stats">
                <div>
                    <dd><?= e((string) ($user['post_count'] ?? 0)) ?></dd>
                    <dt><?= e($t('account.profile.totalPosts')) ?></dt>
                </div>
                <div>
                    <dd><?= e((string) ($user['comment_count'] ?? 0)) ?></dd>
                    <dt><?= e($t('account.profile.commentsReceived')) ?></dt>
                </div>
                <?php if (!empty($user['created_at'])) { ?>
                <div>
                    <dd><?= e(date('M Y', strtotime((string) $user['created_at']))) ?></dd>
                    <dt><?= e($t('account.profile.memberSince')) ?></dt>
                </div>
                <?php } ?>
            </dl>
        </section>
    </div>
</section>
{% endblock %}

{% block scripts %}
<script src="/assets/js/account-profile.js" defer></script>
{% endblock %}
