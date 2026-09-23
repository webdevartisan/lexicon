{% extends "front.lex.php" %}

{% block title %}<?= e($t('reader.notificationsTitle')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
// A reader surface like /saved and /replies, and built from the same pieces:
// the shared header, list rows and pagination. It used to carry control panel
// markup, which meant none of its classes existed out here and the page landed
// unstyled.
$surface = $onlyUnread ? 'notifications-unread' : 'notifications';
$listBase = $listPath.($onlyUnread ? '?filter=unread' : '');
$totalPages = (int) ceil($total / max(1, $perPage));
$pagination = [
    'page' => $page,
    'totalPages' => $totalPages,
    'basePath' => $listBase,
    'outOfRange' => $page > 1 && $notificationRows === [],
];
$readerTabBadges = ['notifications-unread' => $unreadCount];
?>
<section class="lx-reader lx-notifications">
    {% include "partials/public/_reader_header.lex.php" %}

    <?php if ($notificationRows !== []) { ?>
    <div class="lx-notif-bar">
        <p class="lx-notif-tally">
            <?= e($t('notifications.tally', ['total' => $total, 'unread' => $unreadCount])) ?>
        </p>

        <div class="lx-notif-bar-actions">
            <?php if ($unreadCount > 0) { ?>
            <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/read-all')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="lx-btn lx-btn-quiet lx-btn-small"><?= e($t('notifications.markAllRead')) ?></button>
            </form>
            <?php } ?>

            <?php
            // A submit button, not a plain one: the shared dialog intercepts the
            // click, and without scripting the form still posts rather than
            // leaving the control dead.
            ?>
            <form id="notifications-clear-all" method="post" action="<?= e(buildLocalizedUrl($listPath.'/clear-all')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="lx-btn lx-btn-small lx-btn-danger-outline"
                        data-confirm-open="confirm-clear-notifications"><?= e($t('notifications.clearAll')) ?></button>
            </form>
        </div>
    </div>
    <?php } ?>

    <?php if ($notificationRows === []) {
        $readerEmptyMessage = $t($onlyUnread ? 'notifications.emptyUnread' : 'notifications.empty'); ?>
        {% include "partials/public/_reader_empty.lex.php" %}
    <?php } else { ?>

    {% include "partials/public/_notification_icons.lex.php" %}

    <ul class="lx-reader-list">
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
                };
                ?>
        <li class="lx-notif-day"><?= e($dayLabel) ?></li>
        <?php }

            $openUrl = lurl($listPath.'/'.$item['id'].'/open');
            $isFocused = $focusId === $item['id'];
            ?>
        <li id="notification-<?= $item['id'] ?>"
            class="lx-reader-row lx-notif-row<?= $item['isUnread'] ? ' is-unread' : '' ?><?= $isFocused ? ' is-focused' : '' ?>">
            <span class="lx-notif-icon" data-tone="<?= e($item['tone']) ?>" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <?= $notificationIcons[$item['icon']] ?? $notificationIcons['bell'] ?>
                </svg>
            </span>

            <div class="lx-reader-body">
                <p class="lx-notif-title">
                    <?php if ($item['href'] !== '') { ?>
                    <a href="<?= e($openUrl) ?>" rel="nofollow"><?= e($item['title']) ?></a>
                    <?php } else { ?>
                    <?= e($item['title']) ?>
                    <?php } ?>
                </p>

                <?php if ($item['message'] !== '') { ?>
                <?php // The whole message, not a preview: for a moderator's note there is nowhere else to read it.?>
                <p class="lx-notif-message"><?= e($item['message']) ?></p>
                <?php } ?>

                <p class="lx-reader-meta">
                    <time datetime="<?= e(iso_datetime($item['createdAt'])) ?>">
                        <?= e(local_datetime($item['createdAt'], 'M j, Y · g:i a')) ?>
                    </time>
                    <?php if ($item['isUnread']) { ?>
                    <span class="lx-reader-dot"><?= e($t('reader.unread')) ?></span>
                    <?php } ?>
                </p>
            </div>

            <div class="lx-reader-act lx-notif-act">
                <?php if ($item['isUnread']) { ?>
                <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/'.$item['id'].'/read')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="lx-reader-act-btn" title="<?= e($t('notifications.markRead')) ?>">
                        <?= e($t('notifications.markRead')) ?>
                    </button>
                </form>
                <?php } ?>

                <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/'.$item['id'].'/delete')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="lx-reader-act-btn" title="<?= e($t('reader.remove')) ?>"
                            aria-label="<?= e($t('reader.remove')) ?>">&times;</button>
                </form>
            </div>
        </li>
        <?php } ?>
    </ul>

    {% include "partials/public/_reader_pagination.lex.php" %}
    <?php } ?>
</section>

<?php if ($notificationRows !== []) {
    $cmId = 'confirm-clear-notifications';
    $cmFormId = 'notifications-clear-all';
    $cmTitle = $t('notifications.clearAll');
    $cmMessage = $t('notifications.clearAllConfirm');
    $cmConfirm = $t('notifications.clearAll');
    $cmCancel = $t('notifications.cancel');
    ?>
{% include "partials/_confirm_modal.lex.php" %}
<?php } ?>
{% endblock %}

{% block scripts %}
{% include "partials/_confirm_modal_js.lex.php" %}
{% endblock %}
