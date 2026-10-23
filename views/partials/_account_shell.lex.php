<?php
// Shared shell for the /account pages: the heading and the section-nav rail.
// Included by each page, which is what extends front.lex.php. $accountSection is
// set by the including page and decides which item carries aria-current.
//
// This is page navigation, not tabs. Five server-rendered URLs with five POST
// targets are pages: ARIA tab roles would promise a screen reader same-page
// panels and arrow-key switching, then deliver a full page load instead.
//
// $t is the injected translation closure; fall back to the raw key for a
// standalone render, matching _auth_nav.lex.php.
$tr = isset($t) && is_callable($t) ? $t : static fn (string $k, array $p = []): string => $k;
$section = $accountSection ?? '';

$items = [
    ['key' => 'profile',       'href' => '/account/profile',       'label' => 'account.nav.profile',
        'icon' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>'],
    ['key' => 'public',        'href' => '/account/public',        'label' => 'account.nav.public',
        'icon' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/>'],
    ['key' => 'social',        'href' => '/account/social',        'label' => 'account.nav.social',
        'icon' => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>'],
    ['key' => 'preferences',   'href' => '/account/preferences',   'label' => 'account.nav.preferences',
        'icon' => '<path d="M4 6h10"/><path d="M18 6h2"/><circle cx="16" cy="6" r="2"/><path d="M4 12h2"/><path d="M10 12h10"/><circle cx="8" cy="12" r="2"/><path d="M4 18h10"/><path d="M18 18h2"/><circle cx="16" cy="18" r="2"/>'],
    ['key' => 'notifications', 'href' => '/account/notifications', 'label' => 'account.nav.notifications',
        'icon' => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>'],
    ['key' => 'security',      'href' => '/account/security',      'label' => 'account.nav.security',
        'icon' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>'],
    ['key' => 'delete',        'href' => '/account/delete',        'label' => 'account.nav.danger',
        'icon' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>'],
];
?>
<nav class="lx-account-nav" aria-label="<?= e($tr('navigation.account')) ?>">
    <ul>
        <?php foreach ($items as $item) {
            $isCurrent = $section === $item['key'];
            ?>
        <li>
            <a href="<?= e(lurl($item['href'])) ?>" data-panel-link<?= $isCurrent ? ' aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $item['icon'] ?></svg>
                <?= e($tr($item['label'])) ?>
            </a>
        </li>
        <?php } ?>
    </ul>
</nav>
