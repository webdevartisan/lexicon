<?php
// Masthead auth controls, rendered two ways. The layouts include it on every
// page load, and GET /auth/nav re-renders it after a modal login so the header
// can flip to the logged-in state without reloading the page.
//
// Variants: 'theme' for the five blog themes (identical markup in all of them),
// 'platform' for the Lexicon front layout. The two differ only in the wrapper
// each layout expects and in which button styles the surrounding CSS provides;
// the account menu itself is the same markup on both, because it is Lexicon
// speaking rather than the blog, and it should not change shape as a reader
// moves between the platform and somebody's theme.
//
//   Guest            Sign in + [Create a Blog]
//   Reader, no blog  [Create a Blog] + account menu
//   Creator          [Dashboard] + account menu
//
// Sign out lives in the menu rather than on the bar. It is the one control on
// the old bar that nobody reaches for often, and it was sitting in the space
// the reader's own things needed.
$navVariant = ($authNavVariant ?? 'theme') === 'platform' ? 'platform' : 'theme';
$navReturn = $authNavReturnTo ?? (string) ($_SERVER['REQUEST_URI'] ?? '/');
$navViewer = $viewer ?? null;

$loginUrl = lurl('/login').'?return_to='.urlencode($navReturn);
$logoutUrl = lurl('/logout');

// $t is injected as a shared view var. Fall back to the raw key so a standalone
// render never fatals if the helper is missing.
$tr = isset($t) && is_callable($t) ? $t : static fn (string $key, array $p = []): string => $key;

$navIsGuest = !auth()->check() || empty($navViewer);
$navIsReader = !empty($navViewer['is_reader']);
$navUnread = (int) ($navViewer['unread_replies'] ?? 0);

// The count is part of the button's name, not a bare number floating next to
// it: "Your account, 14 unread replies" is what a screen reader should say.
// The pill is the same fact drawn, so it is hidden from the tree.
$navMenuLabel = $navUnread > 0
    ? $tr('reader.accountMenuUnread', ['count' => $navUnread])
    : $tr('reader.accountMenu');

// Both variants use the same id, and only one of them is ever on a page.
$navMenuId = 'account-menu';

// A disclosure, not an ARIA menu. The contents are ordinary navigation links,
// so role="menu" would promise arrow-key semantics that do not exist here.
$renderAccountMenu = static function () use ($navViewer, $navIsReader, $navUnread, $navMenuLabel, $navMenuId, $logoutUrl, $navReturn, $tr): void { ?>
    <div class="nav-user platform-menu" data-platform-menu>
        <button type="button" class="nav-user-btn" data-platform-menu-toggle
                aria-expanded="false" aria-controls="<?= e($navMenuId) ?>"
                aria-label="<?= e($navMenuLabel) ?>">
            <span class="nav-avatar-wrap">
                <span class="nav-avatar">
                    <?php if (!empty($navViewer['avatar_url'])) { ?>
                        <img src="<?= e($navViewer['avatar_url']) ?>" alt="" />
                    <?php } else { ?>
                        <?= e(mb_substr((string) ($navViewer['name'] ?? '?'), 0, 1)) ?>
                    <?php } ?>
                </span>
                <?php if ($navUnread > 0) { ?>
                <?php
                // On the bar the count is noise: the reader only needs to know
                // there is something new, and the number is waiting one click
                // away next to Replies. A dot on the avatar is what every other
                // platform does, and it survives the narrow bar where the name
                // is already hidden.
                    ?>
                <span class="nav-dot" data-reader-dot aria-hidden="true"></span>
                <?php } ?>
            </span>
            <span class="nav-user-name"><?= e($navViewer['name'] ?? '') ?></span>
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
        </button>

        <nav class="platform-menu-list nav-user-menu" id="<?= e($navMenuId) ?>" data-platform-menu-list hidden
             aria-label="<?= e($tr('reader.accountMenu')) ?>">
            <ul>
                <li><a href="<?= e(lurl('/saved')) ?>"><?= e($tr('reader.savedTitle')) ?></a></li>
                <li>
                    <a href="<?= e(lurl('/replies')) ?>">
                        <?= e($tr('reader.repliesTitle')) ?>
                        <?php if ($navUnread > 0) { ?>
                        <span class="nav-badge" data-reader-badge aria-hidden="true"><?= $navUnread > 99 ? '99+' : $navUnread ?></span>
                        <?php } ?>
                    </a>
                </li>
                <li><a href="<?= e(lurl('/subscriptions')) ?>"><?= e($tr('reader.subscriptionsTitle')) ?></a></li>
            </ul>

            <hr />

            <ul>
                <?php if (!$navIsReader) { ?>
                <li><a href="<?= e(lurl('/dashboard')) ?>"><?= e($tr('header.dashboard')) ?></a></li>
                <?php } ?>
                <li><a href="<?= e(lurl('/account/profile')) ?>"><?= e($tr('navigation.account')) ?></a></li>
            </ul>

            <form method="post" action="<?= e($logoutUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e($navReturn) ?>">
                <button type="submit"><?= e($tr('header.signOut')) ?></button>
            </form>
        </nav>
    </div>
<?php };

if ($navVariant === 'platform') {
    // No data-auth-login here on purpose. On the platform pages "Sign in" sits
    // next to "Create a Blog", and one opening a modal while the other
    // navigates is the inconsistency. The modal stays bound to the blog themes'
    // .nav-pill trigger, where interrupting the reader is the whole point.
    if ($navIsGuest) { ?>
        <li><a href="<?= e($loginUrl) ?>" class="lx-btn lx-btn--quiet"><?= e($tr('header.signIn')) ?></a></li>
        <li><a href="<?= e(lurl('/register')) ?>" class="lx-btn lx-btn--primary"><?= e($tr('navigation.createBlog')) ?></a></li>
    <?php } else { ?>
        <li>
            <?php
            // A reader is already registered, so this goes to the form that
            // makes a blog, not to sign-up. A creator gets their dashboard.
        ?>
            <a href="<?= e(lurl($navIsReader ? '/dashboard/blog/new' : '/dashboard')) ?>" class="lx-btn lx-btn--primary">
                <?= e($tr($navIsReader ? 'navigation.createBlog' : 'header.dashboard')) ?>
            </a>
        </li>
        <li><?php $renderAccountMenu(); ?></li>
    <?php }
    } else {
        if ($navIsGuest) { ?>
        <a href="<?= e($loginUrl) ?>" class="nav-pill"><?= e($tr('header.signIn')) ?></a>
    <?php } else {
        $renderAccountMenu();
    }
    }
// Must close the tag. Includes are spliced into the layout as raw text, so
// leaving PHP mode open here would swallow the markup that follows.
?>
