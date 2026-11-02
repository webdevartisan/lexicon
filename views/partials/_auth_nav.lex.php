<?php
// Masthead auth controls, included by every layout on each page load. A
// sign-in through the modal reloads the page, so this is always current.
//
// Variants: 'theme' for the five blog themes (identical markup in all of them),
// 'platform' for the Lexicon front layout. The two differ only in the wrapper
// each layout expects and in which button styles the surrounding CSS provides;
// the account menu is _user_menu.lex.php, shared with the dashboard, because it is Lexicon
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
$userMenuHome = $navVariant === 'theme';
$navReturn = $authNavReturnTo ?? (string) ($_SERVER['REQUEST_URI'] ?? '/');
$navViewer = $viewer ?? null;

$loginUrl = lurl('/login').'?return_to='.urlencode($navReturn);

$navIsGuest = !auth()->check() || empty($navViewer);
$navIsReader = !empty($navViewer['is_reader']);
$navUnreadNotifications = (int) ($navViewer['unread_notifications'] ?? 0);

// The bell comes from the shared partial the dashboard and admin topbars use
// too. Its count is ViewerContext's, the same number every surface shows.
$bellCount = $navUnreadNotifications;
$bellListPath = '/notifications';
$userMenuReturn = $navReturn;

if ($navVariant === 'platform') {
    // No data-auth-login here on purpose. On the platform pages "Sign in" sits
    // next to "Create a Blog", and one opening a modal while the other
    // navigates is the inconsistency. The modal stays bound to the blog themes'
    // .nav-pill trigger, where interrupting the reader is the whole point.
    if ($navIsGuest) { ?>
        <li><a href="<?= e($loginUrl) ?>" class="lx-btn lx-btn-quiet"><?= e($t('header.signIn')) ?></a></li>
        <?php if (registration_open()) { ?>
        <li><a href="<?= e(blog_signup_url()) ?>" class="lx-btn lx-btn-primary"><?= e($t('navigation.createBlog')) ?></a></li>
        <?php } ?>
    <?php } else { ?>
        <?php if (!$navIsReader || !empty($navViewer['can_create_blog'])) { ?>
        <li<?= $navIsReader ? '' : ' class="lx-nav-dash"' ?>>
            <?php
            // A reader is already registered, so this goes to the form that
            // makes a blog, not to sign-up. A creator gets their dashboard.
        ?>
            <a href="<?= e(lurl($navIsReader ? '/start' : '/dashboard')) ?>" class="lx-btn lx-btn-primary"<?= $navIsReader ? ' data-panel' : '' ?>>
                <?= e($t($navIsReader ? 'navigation.createBlog' : 'header.dashboard')) ?>
            </a>
        </li>
        <?php } ?>
        <li>{% include "partials/notifications/_bell.lex.php" %}</li>
        <li>{% include "partials/_user_menu.lex.php" %}</li>
    <?php }
    } else {
        if ($navIsGuest) { ?>
        <a href="<?= e($loginUrl) ?>" class="nav-pill"><?= e($t('header.signIn')) ?></a>
    <?php } else { ?>
        {% include "partials/notifications/_bell.lex.php" %}
        {% include "partials/_user_menu.lex.php" %}
    <?php }
    }
// Must close the tag. Includes are spliced into the layout as raw text, so
// leaving PHP mode open here would swallow the markup that follows.
?>
