<?php
// The bell's panel, fetched by platform-menu.js the first time the bell opens
// and dropped into the empty list the bell renders.
//
// Rendered on its own with no layout and styled by platform-chrome.css, so it
// is the same on the front, in every theme and in the control panel.
//
// The tabs switch in place, so they are a real ARIA tab widget with arrow keys
// (platform-menu.js), unlike the inbox page, whose tabs are links to URLs.
// Each tab panel holds its own rows from the one query the controller ran:
// $panelItems carries up to $panelLimit rows of every scope, and All takes the
// newest of them.
$panelTabs = $tabs !== [] ? $tabs : [['key' => 'all', 'label' => 'notifications.tabAll', 'unread' => $unreadCount]];
$panelEmpty = [
    'all' => ['icon' => 'bell-off', 'text' => $listPath === '/admin/notifications' ? 'notifications.emptyAdmin' : 'notifications.empty'],
    'personal' => ['icon' => 'inbox', 'text' => 'notifications.emptyPersonal'],
    'content' => ['icon' => 'inbox', 'text' => 'notifications.emptyContent'],
];
?>
{% include "partials/public/_notification_icons.lex.php" %}

<div class="notif-panel-head">
    <span class="notif-panel-title"><?= e($t('notifications.panelTitle')) ?></span>

    <div class="notif-panel-tools">
        <?php if ($unreadCount > 0) { ?>
        <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/read-all')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="tab" value="all" data-notif-tab-field>
            <button type="submit" class="notif-icon-btn" title="<?= e($t('notifications.markAllRead')) ?>" aria-label="<?= e($t('notifications.markAllRead')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $notificationIcons['check-check'] ?></svg>
            </button>
        </form>
        <?php } ?>

        <?php if (!empty($settingsPath)) { ?>
        <a class="notif-icon-btn" href="<?= e(lurl($settingsPath)) ?>" title="<?= e($t('notifications.settings')) ?>" aria-label="<?= e($t('notifications.settings')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $notificationIcons['settings'] ?></svg>
        </a>
        <?php } ?>
    </div>
</div>

<?php if ($tabs !== []) { ?>
<div class="notif-tabs" role="tablist" aria-label="<?= e($t('notifications.panelTitle')) ?>" data-notif-tabs>
    <?php foreach ($tabs as $i => $panelTab) { ?>
    <button type="button" class="notif-tab" role="tab" id="notif-tab-<?= e($panelTab['key']) ?>"
            aria-controls="notif-tabpanel-<?= e($panelTab['key']) ?>"
            aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>"
            data-notif-tab="<?= e($panelTab['key']) ?>">
        <?= e($t($panelTab['label'])) ?>
        <?php if ($panelTab['unread'] > 0) { ?>
        <span class="notif-tab-count"><?= $panelTab['unread'] > 99 ? '99+' : (int) $panelTab['unread'] ?></span>
        <?php } ?>
    </button>
    <?php } ?>
</div>
<?php } ?>

<?php foreach ($panelTabs as $i => $panelTab) {
    $panelRows = $panelTab['key'] === 'all'
        ? array_slice($panelItems, 0, $panelLimit)
        : array_values(array_filter($panelItems, static fn (array $row): bool => $row['scope'] === $panelTab['key']));
    $panelEmptyState = $panelEmpty[$panelTab['key']] ?? $panelEmpty['all'];
    ?>
<div class="notif-tabpanel"
     <?php if ($tabs !== []) { ?>role="tabpanel" id="notif-tabpanel-<?= e($panelTab['key']) ?>" aria-labelledby="notif-tab-<?= e($panelTab['key']) ?>"<?php } ?>
     <?= $i > 0 ? 'hidden' : '' ?>>
    <?php if ($panelRows === []) { ?>
    <div class="notif-empty">
        <span class="notif-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $notificationIcons[$panelEmptyState['icon']] ?></svg>
        </span>
        <p><?= e($t($panelEmptyState['text'])) ?></p>
    </div>
    <?php } else { ?>
    <ul class="notif-list">
        <?php foreach ($panelRows as $item) {
            $rowContext = 'panel'; ?>
            {% include "partials/notifications/_row.lex.php" %}
        <?php } ?>
    </ul>
    <?php } ?>
</div>
<?php } ?>

<a class="notif-panel-foot" href="<?= e(lurl($listPath)) ?>"><?= e($t('notifications.seeAll')) ?></a>
