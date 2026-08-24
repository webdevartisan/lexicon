{% extends "front.lex.php" %}

{% block title %}<?= e($t('account.notifications.heading')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
/**
 * One notification toggle. $key is a NOTIFY_KEYS column; the stored value drives
 * the checked state.
 */
$toggle = function (string $key, string $label, string $help) use ($toggles, $applicable): void {
    // A key the user's roles do not warrant is never rendered, and (crucially)
    // never posted back, so the controller leaves its stored value untouched.
    if (!in_array($key, $applicable, true)) {
        return;
    }
    ?>
    <label class="lx-check">
        <input type="checkbox" name="<?= e($key) ?>" value="1" <?= !empty($toggles[$key]) ? 'checked' : '' ?>>
        <span>
            <span class="lx-check-title"><?= e($label) ?></span>
            <span class="lx-check-help"><?= e($help) ?></span>
        </span>
    </label>
<?php };

/**
 * True when at least one of the given keys applies, so a whole section can be
 * dropped when none of its toggles are relevant.
 */
$anyApplies = fn (string ...$keys): bool => (bool) array_intersect($keys, $applicable);
?>
<section class="lx-wrap lx-account">
    <?php $accountSection = 'notifications'; ?>
    <header class="lx-account-head">
        <h1><?= e($t('account.notifications.heading')) ?></h1>
        <p class="lx-muted"><?= e($t('account.notifications.intro')) ?></p>
        <p class="lx-muted"><?= e($t('account.notifications.inAppNote')) ?></p>
    </header>

    {% include "partials/_account_shell.lex.php" %}

    <div class="lx-account-body">
        <form method="post" action="<?= e(lurl('/account/notifications/update')) ?>" class="lx-account-form">
            <?= csrf_field() ?>

            <?php if ($anyApplies('notify_post_status')) { ?>
            <h2 class="lx-account-section"><?= e($t('account.notifications.yourPostsSection')) ?></h2>
            <?php $toggle('notify_post_status', $t('account.notifications.postStatus'), $t('account.notifications.postStatusHelp')); ?>
            <?php } ?>

            <?php if ($anyApplies('notify_review_requests')) { ?>
            <h2 class="lx-account-section"><?= e($t('account.notifications.reviewsSection')) ?></h2>
            <?php $toggle('notify_review_requests', $t('account.notifications.review'), $t('account.notifications.reviewHelp')); ?>
            <?php } ?>

            <h2 class="lx-account-section"><?= e($t('account.notifications.commentsSection')) ?></h2>
            <?php if ($anyApplies('notify_comments_moderation', 'notify_comments_blog')) { ?>
            <p class="lx-muted"><?= e($t('account.notifications.commentsFallthrough')) ?></p>
            <?php } ?>
            <?php $toggle('notify_comment_replies', $t('account.notifications.reply'), $t('account.notifications.replyHelp')); ?>
            <?php $toggle('notify_comments_authored', $t('account.notifications.authored'), $t('account.notifications.authoredHelp')); ?>
            <?php $toggle('notify_comments_moderation', $t('account.notifications.moderation'), $t('account.notifications.moderationHelp')); ?>
            <?php $toggle('notify_comments_blog', $t('account.notifications.blog'), $t('account.notifications.blogHelp')); ?>

            <?php if ($anyApplies('notify_role_changes', 'notify_invites')) { ?>
            <h2 class="lx-account-section"><?= e($t('account.notifications.teamSection')) ?></h2>
            <?php $toggle('notify_role_changes', $t('account.notifications.role'), $t('account.notifications.roleHelp')); ?>
            <?php $toggle('notify_invites', $t('account.notifications.invites'), $t('account.notifications.invitesHelp')); ?>
            <?php } ?>

            <div class="lx-account-actions">
                <button type="reset" class="lx-btn lx-btn--subtle"><?= e($t('account.common.reset')) ?></button>
                <button type="submit" class="lx-btn lx-btn--primary"><?= e($t('account.notifications.save')) ?></button>
            </div>
        </form>

        <aside class="lx-account-aside">
            <section class="lx-card">
                <h2 class="lx-card-title"><?= e($t('account.notifications.alwaysHeading')) ?></h2>
                <p class="lx-muted"><?= e($t('account.notifications.alwaysIntro')) ?></p>
                <div class="lx-always">
                    <p class="lx-check-title"><?= e($t('account.notifications.alwaysInvites')) ?></p>
                    <p class="lx-check-help"><?= e($t('account.notifications.alwaysInvitesHelp')) ?></p>
                </div>
                <div class="lx-always">
                    <p class="lx-check-title"><?= e($t('account.notifications.alwaysAccount')) ?></p>
                    <p class="lx-check-help"><?= e($t('account.notifications.alwaysAccountHelp')) ?></p>
                </div>
                <div class="lx-always">
                    <p class="lx-check-title"><?= e($t('account.notifications.alwaysFollow')) ?></p>
                    <p class="lx-check-help">
                        <?= e($t('account.notifications.alwaysFollowHelp')) ?>
                        <a href="<?= e(lurl('/subscriptions')) ?>"><?= e($t('account.notifications.subscriptionsLink')) ?></a>
                    </p>
                </div>
            </section>
        </aside>
    </div>
</section>
{% endblock %}
