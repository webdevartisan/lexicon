<?php
// The account dialog. Included by the front and the control panel layouts for a
// signed-in user; account-modal.js fills the main area from the /account pages.
// Every trigger is a real link to its /account page, so without scripting the
// menu still works.
$accountTr = static fn (string $k): string => chrome_translate($k);
$accountUser = auth()->user() ?: [];
$accountFirst = trim((string) ($accountUser['first_name'] ?? ''));
$accountLast = trim((string) ($accountUser['last_name'] ?? ''));
$accountName = trim($accountFirst.' '.$accountLast);
$accountName = $accountName !== '' ? $accountName : (string) ($accountUser['handle'] ?? $accountUser['email'] ?? '');
$accountInitials = strtoupper(mb_substr($accountFirst, 0, 1).mb_substr($accountLast, 0, 1));
$accountInitials = $accountInitials !== '' ? $accountInitials : strtoupper(mb_substr($accountName, 0, 1));

$accountIcons = [
    'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
    'public' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>',
    'social' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
    'preferences' => '<path d="M4 6h10"/><path d="M18 6h2"/><circle cx="16" cy="6" r="2"/><path d="M4 12h2"/><path d="M10 12h10"/><circle cx="8" cy="12" r="2"/><path d="M4 18h10"/><path d="M18 18h2"/><circle cx="16" cy="18" r="2"/>',
    'notifications' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
    'security' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'delete' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
];
// The danger tab is the /account/delete page, so its key is the URL segment.
$accountPages = ['profile' => 'profile', 'public' => 'public', 'social' => 'social', 'preferences' => 'preferences', 'notifications' => 'notifications', 'security' => 'security', 'delete' => 'danger'];
?>
<dialog class="acct-dialog" id="account-dialog" aria-labelledby="account-dialog-title" data-account-dialog>
    <div class="acct-dialog-card">
        <nav class="acct-dialog-nav" aria-label="<?= e($accountTr('navigation.account')) ?>">
            <p class="acct-dialog-who">
                <span class="acct-dialog-badge" aria-hidden="true"><?= e($accountInitials) ?></span>
                <span><?= e($accountName) ?></span>
            </p>
            <p class="acct-dialog-nav-title" id="account-dialog-title"><?= e($accountTr('navigation.account')) ?></p>
            <?php foreach ($accountPages as $accountPage => $accountLabel) { ?>
            <button type="button" data-account-page="<?= e($accountPage) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $accountIcons[$accountPage] ?></svg>
                <?= e($accountTr('account.nav.'.$accountLabel)) ?>
            </button>
            <?php } ?>
        </nav>
        <div class="acct-dialog-main" data-account-body tabindex="-1"></div>
        <button type="button" class="acct-dialog-close" data-account-close aria-label="<?= e($accountTr('a11y.dismiss')) ?>">&times;</button>
    </div>
</dialog>
