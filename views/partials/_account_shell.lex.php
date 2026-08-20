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
    ['key' => 'profile',       'href' => '/account/profile',       'label' => 'account.nav.profile'],
    ['key' => 'preferences',   'href' => '/account/preferences',   'label' => 'account.nav.preferences'],
    ['key' => 'notifications', 'href' => '/account/notifications', 'label' => 'account.nav.notifications'],
    ['key' => 'security',      'href' => '/account/security',      'label' => 'account.nav.security'],
];
?>
<nav class="lx-account-nav" aria-label="<?= e($tr('navigation.account')) ?>">
    <ul>
        <?php foreach ($items as $item) {
            $isCurrent = $section === $item['key'];
        ?>
        <li>
            <a href="<?= e(lurl($item['href'])) ?>"<?= $isCurrent ? ' aria-current="page"' : '' ?>>
                <?= e($tr($item['label'])) ?>
            </a>
        </li>
        <?php } ?>
    </ul>
</nav>
