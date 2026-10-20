{% include "partials/public/_notification_icons.lex.php" %}
<?php
// The avatar menu, the same everywhere a signed-in person sees Lexicon: the
// front, every blog theme and the dashboard. It is a disclosure, not an ARIA
// menu: the rows are ordinary links, reached with Tab (platform-menu.js).
//
// Expects $viewer (ViewerContext, a global on every page). Optional:
// $userMenuClass for the caller's layout classes, $userMenuReturn for where
// sign-out comes back to.
$umTr = isset($t) && is_callable($t) ? $t : static fn (string $key, array $p = []): string => $key;
$umViewer = $viewer ?? [];
$umUnread = (int) ($umViewer['unread_replies'] ?? 0);
$umName = (string) ($umViewer['name'] ?? '');
$umAvatar = static function () use ($umViewer, $umName): void { ?>
    <span class="nav-avatar">
        <?php if (!empty($umViewer['avatar_url'])) { ?>
            <img src="<?= e($umViewer['avatar_url']) ?>" alt="" />
        <?php } else { ?>
            <?= e(mb_substr($umName !== '' ? $umName : '?', 0, 1)) ?>
        <?php } ?>
    </span>
<?php };

// The count is part of the button's name, so a screen reader hears "Your
// account, 14 unread replies"; the dot is the same fact drawn.
$umLabel = $umUnread > 0
    ? $umTr('reader.accountMenuUnread', ['count' => $umUnread])
    : $umTr('reader.accountMenu');

// The comment and settings glyphs come from the notification set the bell uses.
$umIcons = [
    'saved' => '<path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>',
    'replies' => $notificationIcons['message-circle'],
    'subscriptions' => '<path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>',
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    'admin' => '<path d="M21 4h-7M10 4H3M21 12h-9M8 12H3M21 20h-5M12 20H3M14 2v4M8 10v4M16 18v4"/>',
    'settings' => $notificationIcons['settings'],
    'appearance' => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor"/>',
    'signout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
];
$umIcon = static function (string $name) use ($umIcons): void { ?>
    <svg class="nav-row-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $umIcons[$name] ?></svg>
<?php };
?>
<div class="nav-user platform-menu<?= !empty($userMenuClass) ? ' '.e($userMenuClass) : '' ?>" data-platform-menu>
    <button type="button" class="nav-user-btn" data-platform-menu-toggle
            aria-expanded="false" aria-controls="account-menu" aria-label="<?= e($umLabel) ?>">
        <span class="nav-avatar-wrap">
            <?php $umAvatar(); ?>
            <?php if ($umUnread > 0) { ?>
            <span class="nav-dot" data-reader-dot aria-hidden="true"></span>
            <?php } ?>
        </span>
        <span class="nav-user-name"><?= e($umName) ?></span>
        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
    </button>

    <nav class="platform-menu-list nav-user-menu" id="account-menu" data-platform-menu-list hidden
         aria-label="<?= e($umTr('reader.accountMenu')) ?>">
        <a class="nav-user-who" href="<?= e(lurl('/account/profile')) ?>" data-panel="wide">
            <?php $umAvatar(); ?>
            <span>
                <strong><?= e($umName) ?></strong>
                <?php if (!empty($umViewer['email'])) { ?>
                <small><?= e($umViewer['email']) ?></small>
                <?php } ?>
            </span>
        </a>

        <hr />

        <ul>
            <li><a href="<?= e(lurl('/saved')) ?>" data-panel><?php $umIcon('saved'); ?><span><?= e($umTr('reader.savedTitle')) ?></span></a></li>
            <li>
                <a href="<?= e(lurl('/replies')) ?>" data-panel>
                    <?php $umIcon('replies'); ?><span><?= e($umTr('reader.repliesTitle')) ?></span>
                    <?php if ($umUnread > 0) { ?>
                    <span class="nav-badge" data-reader-badge aria-hidden="true"><?= $umUnread > 99 ? '99+' : $umUnread ?></span>
                    <?php } ?>
                </a>
            </li>
            <li><a href="<?= e(lurl('/subscriptions')) ?>" data-panel><?php $umIcon('subscriptions'); ?><span><?= e($umTr('reader.subscriptionsTitle')) ?></span></a></li>
        </ul>

        <hr />

        <ul>
            <?php if (empty($umViewer['is_reader'])) { ?>
            <li><a href="<?= e(lurl('/dashboard')) ?>"><?php $umIcon('dashboard'); ?><span><?= e($umTr('header.dashboard')) ?></span></a></li>
            <?php } ?>
            <?php if (!empty($umViewer['is_admin'])) { ?>
            <li><a href="<?= e(lurl('/admin')) ?>"><?php $umIcon('admin'); ?><span><?= e($umTr('layout.profileDropdown.links.controlPanel')) ?></span></a></li>
            <?php } ?>
            <li><a href="<?= e(lurl('/account/profile')) ?>" data-panel="wide"><?php $umIcon('settings'); ?><span><?= e($umTr('navigation.account')) ?></span></a></li>
        </ul>

        <div class="nav-user-theme">
            <?php $umIcon('appearance'); ?><span><?= e($umTr('layout.themeMenu.label')) ?></span>
            {% include "partials/_theme_choices.lex.php" %}
        </div>

        <hr />

        <form method="post" action="<?= e(lurl('/logout')) ?>">
            <?= csrf_field() ?>
            <?php if (!empty($userMenuReturn)) { ?>
            <input type="hidden" name="return_to" value="<?= e($userMenuReturn) ?>">
            <?php } ?>
            <button type="submit"><?php $umIcon('signout'); ?><span><?= e($umTr('header.signOut')) ?></span></button>
        </form>
    </nav>
    {% include "components/panel-dialog.lex.php" %}
</div>
