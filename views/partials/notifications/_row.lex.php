<?php
// One notification, the same in the bell's panel and on both inbox pages.
//
// Expects $item (from NotificationFeed), $listPath, $notificationIcons, and
// $rowContext: 'panel' clamps the quote and links the whole row, 'page' shows
// the quote in full and adds mark read and remove.
//
// The sentence is translated with two markers standing in for the actor and
// the target, escaped as a whole, and only then are the markers swapped for
// the two escaped names. Every value in it is escaped exactly once, and every
// language gets the same emphasis without HTML in the locale files.
$rowTr = isset($t) && is_callable($t) ? $t : static fn (string $key, array $p = []): string => $key;
$rowIsPage = ($rowContext ?? 'panel') === 'page';
$rowSentence = $item['sentence'];
$rowActor = $rowSentence['actor'] ?? $rowTr('notifications.someone');
$rowTarget = $rowSentence['target'] ?? $rowTr($rowSentence['targetFallback'] ?? 'notifications.aPost');
$rowText = str_replace(
    ["\u{E000}", "\u{E001}"],
    ['<strong class="notif-actor">'.e($rowActor).'</strong>', '<strong class="notif-target">'.e($rowTarget).'</strong>'],
    e($rowTr($rowSentence['key'], array_merge($rowSentence['params'], ['actor' => "\u{E000}", 'target' => "\u{E001}"])))
);

$rowOpenUrl = lurl($listPath.'/'.$item['id'].'/open');
$rowSource = $item['scope'] === 'content'
    ? ($item['source'] ?? $rowTr('notifications.aBlogSource'))
    : ($item['scope'] === 'personal' ? $rowTr('notifications.tab.personal') : null);

// Within the week a weekday reads faster than a date, the way the reference does it.
$rowAge = $item['createdAt'] !== null ? time() - (int) strtotime($item['createdAt'].' UTC') : PHP_INT_MAX;
$rowWhen = local_datetime($item['createdAt'], $rowAge < 6 * 86400 ? 'l g:i A' : 'M j, Y g:i A');

$rowActorData = $item['actor'];
$rowClasses = 'notif-row'.($item['isUnread'] ? ' is-unread' : '').($rowIsPage && ($focusId ?? 0) === $item['id'] ? ' is-focused' : '');
?>
<li class="<?= $rowClasses ?>"<?= $rowIsPage ? ' id="notification-'.(int) $item['id'].'"' : '' ?> data-scope="<?= e($item['scope']) ?>">
    <?php if ($rowActorData !== null && !empty($rowActorData['avatar_url'])) { ?>
    <span class="notif-avatar"><img src="<?= e($rowActorData['avatar_url']) ?>" alt="" loading="lazy"></span>
    <?php } elseif ($rowActorData !== null) {
        $rowInitialSeed = (string) ($rowActorData['name'] ?? '?');
        $rowInitial = mb_strtoupper(mb_substr(ltrim($rowInitialSeed, '@') !== '' ? ltrim($rowInitialSeed, '@') : '?', 0, 1)); ?>
    <span class="notif-avatar is-letter" style="--avatar-hue: <?= crc32($rowInitialSeed) % 360 ?>" aria-hidden="true"><?= e($rowInitial) ?></span>
    <?php } else { ?>
    <span class="notif-avatar is-icon" data-tone="<?= e($item['tone']) ?>" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            <?= $notificationIcons[$item['icon']] ?? $notificationIcons['bell'] ?>
        </svg>
    </span>
    <?php } ?>

    <div class="notif-body">
        <div class="notif-line">
            <p class="notif-sentence">
                <?php if (!$rowIsPage || $item['href'] !== '') { ?>
                <a href="<?= e($rowOpenUrl) ?>" rel="nofollow"><?= $rowText ?></a>
                <?php } else { ?>
                <?= $rowText ?>
                <?php } ?>
            </p>
            <?php if ($item['isUnread']) { ?>
            <span class="notif-dot" role="img" aria-label="<?= e($rowTr('reader.unread')) ?>"></span>
            <?php } ?>
        </div>

        <p class="notif-meta">
            <span class="notif-meta-start">
                <?php if ($rowSource !== null) { ?>
                <span class="notif-source<?= $item['scope'] === 'personal' ? ' is-personal' : '' ?>"><?= e($rowSource) ?></span>
                <?php } ?>
                <time datetime="<?= e(iso_datetime($item['createdAt'])) ?>"><?= e($rowWhen) ?></time>
            </span>
            <span class="notif-ago"><?= e(relative_time($item['createdAt'])) ?></span>
        </p>

        <?php if ($item['quote'] !== '') { ?>
        <p class="notif-quote<?= $rowIsPage ? '' : ' is-clamped' ?>"><?= e($item['quote']) ?></p>
        <?php } ?>

        <?php if (!empty($item['invite'])) {
            // The invite endpoints do every check themselves (CSRF, the address
            // the invitation was sent to, whether it is still open). The id lets
            // them mark this notification read once it has been answered.
            ?>
        <div class="notif-actions">
            <form method="post" action="<?= e(buildLocalizedUrl($item['invite']['decline'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="notification" value="<?= (int) $item['id'] ?>">
                <button type="submit" class="notif-action-btn"><?= e($rowTr('notifications.decline')) ?></button>
            </form>
            <form method="post" action="<?= e(buildLocalizedUrl($item['invite']['accept'])) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="notification" value="<?= (int) $item['id'] ?>">
                <button type="submit" class="notif-action-btn is-primary"><?= e($rowTr('notifications.accept')) ?></button>
            </form>
        </div>
        <?php } ?>
    </div>

    <?php if ($rowIsPage) { ?>
    <div class="notif-act">
        <?php if ($item['isUnread']) { ?>
        <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/'.$item['id'].'/read')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="notif-icon-btn" title="<?= e($rowTr('notifications.markRead')) ?>" aria-label="<?= e($rowTr('notifications.markRead')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
            </button>
        </form>
        <?php } ?>
        <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/'.$item['id'].'/delete')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="notif-icon-btn is-danger" title="<?= e($rowTr('reader.remove')) ?>" aria-label="<?= e($rowTr('reader.remove')) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </form>
    </div>
    <?php } ?>
</li>
