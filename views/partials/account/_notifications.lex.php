<?php
// The notification toggles, shared by the /account/notifications page and the
// account dialog. Styled by platform-chrome.css so it looks the same on the
// front and in the control panel.
//
// A key the user's roles do not warrant is never rendered, and (crucially) never
// posted back, so the controller leaves its stored value untouched.
$toggle = function (string $key, string $label, string $help) use ($toggles, $applicable): void {
    if (!in_array($key, $applicable, true)) {
        return;
    }
    ?>
    <label class="acct-check">
        <input type="checkbox" role="switch" class="acct-switch" name="<?= e($key) ?>" value="1" <?= !empty($toggles[$key]) ? 'checked' : '' ?>>
        <span>
            <span class="acct-check-title"><?= e($label) ?></span>
            <span class="acct-check-help"><?= e($help) ?></span>
        </span>
    </label>
<?php };

$anyApplies = fn (string ...$keys): bool => (bool) array_intersect($keys, $applicable);
?>
{% include "partials/account/_flash.lex.php" %}

<div class="acct-head">
    <h2 class="acct-title"><?= e($t('account.notifications.heading')) ?></h2>
    <p class="acct-intro"><?= e($t('account.notifications.intro')) ?></p>
    <p class="acct-intro"><?= e($t('account.notifications.inAppNote')) ?></p>
</div>

<form method="post" action="<?= e(lurl('/account/notifications/update')) ?>" class="acct-form" data-account-form>
    <?= csrf_field() ?>

    <?php if ($anyApplies('notify_post_status')) { ?>
    <h3 class="acct-section"><?= e($t('account.notifications.yourPostsSection')) ?></h3>
    <?php $toggle('notify_post_status', $t('account.notifications.postStatus'), $t('account.notifications.postStatusHelp')); ?>
    <?php } ?>

    <?php if ($anyApplies('notify_review_requests')) { ?>
    <h3 class="acct-section"><?= e($t('account.notifications.reviewsSection')) ?></h3>
    <?php $toggle('notify_review_requests', $t('account.notifications.review'), $t('account.notifications.reviewHelp')); ?>
    <?php } ?>

    <h3 class="acct-section"><?= e($t('account.notifications.commentsSection')) ?></h3>
    <?php if ($anyApplies('notify_comments_moderation', 'notify_comments_blog')) { ?>
    <p class="acct-intro"><?= e($t('account.notifications.commentsFallthrough')) ?></p>
    <?php } ?>
    <?php $toggle('notify_comment_replies', $t('account.notifications.reply'), $t('account.notifications.replyHelp')); ?>
    <?php $toggle('notify_comments_authored', $t('account.notifications.authored'), $t('account.notifications.authoredHelp')); ?>
    <?php $toggle('notify_comments_moderation', $t('account.notifications.moderation'), $t('account.notifications.moderationHelp')); ?>
    <?php $toggle('notify_comments_blog', $t('account.notifications.blog'), $t('account.notifications.blogHelp')); ?>

    <?php if ($anyApplies('notify_role_changes', 'notify_invites')) { ?>
    <h3 class="acct-section"><?= e($t('account.notifications.teamSection')) ?></h3>
    <?php $toggle('notify_role_changes', $t('account.notifications.role'), $t('account.notifications.roleHelp')); ?>
    <?php $toggle('notify_invites', $t('account.notifications.invites'), $t('account.notifications.invitesHelp')); ?>
    <?php } ?>

    <?php if ($anyApplies('notify_insights_digest', 'notify_insights_milestones', 'notify_insights_spikes')) { ?>
    <h3 class="acct-section"><?= e($t('account.notifications.analyticsSection')) ?></h3>
    <?php $toggle('notify_insights_digest', $t('account.notifications.analyticsDigest'), $t('account.notifications.analyticsDigestHelp')); ?>
    <?php $toggle('notify_insights_milestones', $t('account.notifications.analyticsMilestones'), $t('account.notifications.analyticsMilestonesHelp')); ?>
    <?php $toggle('notify_insights_spikes', $t('account.notifications.analyticsSpikes'), $t('account.notifications.analyticsSpikesHelp')); ?>
    <?php } ?>

    <div class="acct-actions">
        <button type="submit" class="acct-btn acct-btn-primary"><?= e($t('account.notifications.save')) ?></button>
    </div>
</form>

<section class="acct-card">
    <h3 class="acct-card-title"><?= e($t('account.notifications.alwaysHeading')) ?></h3>
    <p class="acct-intro"><?= e($t('account.notifications.alwaysIntro')) ?></p>
    <div class="acct-always">
        <p class="acct-check-title"><?= e($t('account.notifications.alwaysInvites')) ?></p>
        <p class="acct-check-help"><?= e($t('account.notifications.alwaysInvitesHelp')) ?></p>
    </div>
    <div class="acct-always">
        <p class="acct-check-title"><?= e($t('account.notifications.alwaysAccount')) ?></p>
        <p class="acct-check-help"><?= e($t('account.notifications.alwaysAccountHelp')) ?></p>
    </div>
    <div class="acct-always">
        <p class="acct-check-title"><?= e($t('account.notifications.alwaysFollow')) ?></p>
        <p class="acct-check-help">
            <?= e($t('account.notifications.alwaysFollowHelp')) ?>
            <a href="<?= e(lurl('/subscriptions')) ?>"><?= e($t('account.notifications.subscriptionsLink')) ?></a>
        </p>
    </div>
</section>
