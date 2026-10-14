<?php
// The inbox page body, shared by /notifications (front), /dashboard/notifications
// and /admin/notifications. Each page view only adds its own shell around it.
//
// Tabs here are links to real URLs, not a tab widget: each one is its own page
// with its own pagination, so they carry aria-current and nothing more.
//
// $pageSkin 'front' asks for confirmation through the front's confirm modal,
// 'cp' through the control panel's data-confirm; both post the same form.
$pageSkin = ($pageSkin ?? 'front') === 'cp' ? 'cp' : 'front';
$pageQuery = static function (array $changes) use ($tab, $onlyUnread): string {
    $query = array_filter(array_merge(
        ['tab' => $tab === 'all' ? null : $tab, 'unread' => $onlyUnread ? '1' : null],
        $changes
    ), static fn ($value): bool => $value !== null);

    return $query === [] ? '' : '?'.http_build_query($query);
};
$pageBase = lurl($listPath);
$totalPages = (int) ceil($total / max(1, $perPage));
$clearConfirm = $t($tab === 'all' ? 'notifications.clearAllConfirm' : 'notifications.clearTabConfirm.'.$tab);
$emptyKey = match (true) {
    $onlyUnread => 'notifications.emptyUnread',
    $tab === 'personal' => 'notifications.emptyPersonal',
    $tab === 'content' => 'notifications.emptyContent',
    $listPath === '/admin/notifications' => 'notifications.emptyAdmin',
    default => 'notifications.empty',
};
$rowContext = 'page';
?>
{% include "partials/public/_notification_icons.lex.php" %}

<div class="notif-page">
    <div class="notif-page-bar">
        <?php if ($tabs !== []) { ?>
        <nav class="notif-tabs" aria-label="<?= e($t('reader.notificationsTitle')) ?>">
            <?php foreach ($tabs as $pageTab) {
                $isCurrent = $pageTab['key'] === $tab; ?>
            <a class="notif-tab" href="<?= e($pageBase.$pageQuery(['tab' => $pageTab['key'] === 'all' ? null : $pageTab['key'], 'page' => null])) ?>"
               <?= $isCurrent ? 'aria-current="page"' : '' ?>>
                <?= e($t($pageTab['label'])) ?>
                <?php if ($pageTab['unread'] > 0) { ?>
                <span class="notif-tab-count"><?= $pageTab['unread'] > 99 ? '99+' : (int) $pageTab['unread'] ?></span>
                <?php } ?>
            </a>
            <?php } ?>
        </nav>
        <?php } ?>

        <div class="notif-page-tools">
            <a class="notif-toggle" href="<?= e($pageBase.$pageQuery(['unread' => $onlyUnread ? null : '1', 'page' => null])) ?>"
               aria-pressed="<?= $onlyUnread ? 'true' : 'false' ?>" role="button">
                <span class="notif-toggle-box" aria-hidden="true"></span>
                <?= e($t('notifications.unreadOnly')) ?>
            </a>

            <?php if ($tabUnread > 0) { ?>
            <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/read-all')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <button type="submit" class="notif-text-btn"><?= e($t('notifications.markAllRead')) ?></button>
            </form>
            <?php } ?>

            <?php if ($total > 0 && !$onlyUnread) { ?>
            <form id="notifications-clear-all" method="post" action="<?= e(buildLocalizedUrl($listPath.'/clear-all')) ?>"
                  <?= $pageSkin === 'cp' ? 'data-confirm="'.e($clearConfirm).'"' : '' ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="tab" value="<?= e($tab) ?>">
                <?php
                // A submit button either way: the front's dialog intercepts the
                // click, and with no scripting the form still posts.
                ?>
                <button type="submit" class="notif-text-btn is-danger"
                        <?= $pageSkin === 'front' ? 'data-confirm-open="confirm-clear-notifications"' : '' ?>><?= e($t('notifications.clearAll')) ?></button>
            </form>
            <?php } ?>
        </div>
    </div>

    <?php if ($notificationRows === []) { ?>
    <div class="notif-empty">
        <span class="notif-empty-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $notificationIcons[$tab === 'all' ? 'bell-off' : 'inbox'] ?></svg>
        </span>
        <p><?= e($t($emptyKey)) ?></p>
    </div>
    <?php } else { ?>
    <ul class="notif-list">
        <?php
        $lastDay = '';
        // Compared in the reader's own timezone, so "Today" means today where
        // they are rather than wherever UTC has got to.
        $today = local_datetime(gmdate('Y-m-d H:i:s'), 'Y-m-d');
        $yesterday = local_datetime(gmdate('Y-m-d H:i:s', time() - 86400), 'Y-m-d');

        foreach ($notificationRows as $item) {
            $day = local_datetime($item['createdAt'], 'Y-m-d');

            if ($day !== $lastDay) {
                $lastDay = $day;
                $dayLabel = match ($day) {
                    $today => $t('notifications.today'),
                    $yesterday => $t('notifications.yesterday'),
                    default => local_datetime($item['createdAt'], 'F j, Y'),
                }; ?>
        <li class="notif-day" role="presentation"><?= e($dayLabel) ?></li>
        <?php } ?>
            {% include "partials/notifications/_row.lex.php" %}
        <?php } ?>
    </ul>

    <?php if ($totalPages > 1) { ?>
    <nav class="notif-pager" aria-label="<?= e($t('notifications.pages')) ?>">
        <span>
            <?php if ($page > 1) { ?>
            <a href="<?= e($pageBase.$pageQuery(['page' => $page - 1 > 1 ? $page - 1 : null])) ?>"><?= e($t('notifications.newer')) ?></a>
            <?php } ?>
        </span>
        <span><?= e($t('notifications.pageOf', ['page' => $page, 'pages' => $totalPages])) ?></span>
        <span>
            <?php if ($page < $totalPages) { ?>
            <a href="<?= e($pageBase.$pageQuery(['page' => $page + 1])) ?>"><?= e($t('notifications.older')) ?></a>
            <?php } ?>
        </span>
    </nav>
    <?php } ?>
    <?php } ?>
</div>

<?php if ($pageSkin === 'front' && $total > 0 && !$onlyUnread) {
    $cmId = 'confirm-clear-notifications';
    $cmFormId = 'notifications-clear-all';
    $cmTitle = $t('notifications.clearAll');
    $cmMessage = $clearConfirm;
    $cmConfirm = $t('notifications.clearAll');
    $cmCancel = $t('notifications.cancel');
    ?>
{% include "partials/_confirm_modal.lex.php" %}
<?php } ?>
