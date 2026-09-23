<?php
// The masthead bell's panel, fetched by platform-menu.js the first time the
// bell is opened and dropped into the empty list it finds there.
//
// Rendered on its own with no layout, and styled by platform-chrome.css, so it
// looks and behaves the same on the Lexicon front and inside every blog theme.
// Each row is a real link to the open endpoint, which marks the notification
// read and then sends the reader on: no JavaScript needed to follow one, and
// middle-click still opens it in a tab.
?>
{% include "partials/public/_notification_icons.lex.php" %}

<div class="notif-panel-head">
    <span class="notif-panel-title"><?= e($t('reader.notificationsTitle')) ?></span>

    <?php if ($unreadCount > 0) { ?>
    <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/read-all')) ?>">
        <?= csrf_field() ?>
        <button type="submit" class="notif-panel-action"><?= e($t('notifications.markAllRead')) ?></button>
    </form>
    <?php } ?>
</div>

<?php if ($panelItems === []) { ?>
<p class="notif-panel-empty"><?= e($t('notifications.empty')) ?></p>
<?php } else { ?>
<ul class="notif-panel-list">
    <?php foreach ($panelItems as $item) { ?>
    <li class="notif-row<?= $item['isUnread'] ? ' is-unread' : '' ?>">
        <a href="<?= e(lurl($listPath.'/'.$item['id'].'/open')) ?>" rel="nofollow">
            <span class="notif-row-icon" data-tone="<?= e($item['tone']) ?>" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <?= $notificationIcons[$item['icon']] ?? $notificationIcons['bell'] ?>
                </svg>
            </span>
            <span class="notif-row-body">
                <span class="notif-row-title"><?= e($item['title']) ?></span>
                <span class="notif-row-time"><?= e(local_datetime($item['createdAt'], 'M j, g:i a')) ?></span>
            </span>
            <?php if ($item['isUnread']) { ?>
            <span class="notif-row-dot" aria-label="<?= e($t('reader.unread')) ?>"></span>
            <?php } ?>
        </a>
    </li>
    <?php } ?>
</ul>
<?php } ?>

<a class="notif-panel-foot" href="<?= e(lurl($listPath)) ?>"><?= e($t('notifications.seeAll')) ?></a>
