<?php
// The notification bell, the same markup on the Lexicon front, inside every
// blog theme, in the dashboard topbar and in the admin topbar.
//
// It is a link first, so without JavaScript it goes to the inbox.
// platform-menu.js turns it into a disclosure and loads the panel from
// {listPath}/panel the first time it opens. Nothing per-person is rendered
// here except the count, which every caller already has.
//
// Expects $bellCount (unread) and $bellListPath (unlocalized inbox path).
// $bellClass adds the caller's own layout classes to the wrapper.
$bellTr = isset($t) && is_callable($t) ? $t : static fn (string $key, array $p = []): string => $key;
$bellCount = (int) ($bellCount ?? 0);
$bellListPath = (string) ($bellListPath ?? '/notifications');

// The count is part of the name, so a screen reader hears "Notifications,
// 6 unread" and the badge itself stays out of the tree.
$bellLabel = $bellCount > 0
    ? $bellTr('notifications.bellUnread', ['count' => $bellCount])
    : $bellTr('reader.notificationsTitle');
?>
<div class="nav-bell platform-menu<?= !empty($bellClass) ? ' '.e($bellClass) : '' ?>" data-platform-menu>
    <a class="nav-bell-btn" href="<?= e(lurl($bellListPath)) ?>"
       data-platform-menu-toggle
       aria-expanded="false" aria-controls="notification-panel"
       aria-label="<?= e($bellLabel) ?>">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
        </svg>
        <?php if ($bellCount > 0) { ?>
        <span class="nav-badge nav-bell-badge" aria-hidden="true"><?= $bellCount > 99 ? '99+' : $bellCount ?></span>
        <?php } ?>
    </a>

    <div class="platform-menu-list notif-panel" id="notification-panel" data-platform-menu-list
         data-panel-url="<?= e(lurl($bellListPath.'/panel')) ?>"
         data-panel-error="<?= e($bellTr('notifications.loadFailed')) ?>" hidden>
        <p class="notif-panel-status"><?= e($bellTr('notifications.loading')) ?></p>
    </div>
</div>
