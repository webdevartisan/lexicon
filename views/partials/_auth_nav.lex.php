<?php
// Masthead auth controls, rendered two ways. The layouts include it on every
// page load, and GET /auth/nav re-renders it after a modal login so the header
// can flip to the logged-in state without reloading the page.
//
// Variants: 'theme' for the five blog themes (identical markup in all of them),
// 'platform' for the Lexicon front layout.
$navVariant = ($authNavVariant ?? 'theme') === 'platform' ? 'platform' : 'theme';
$navReturn = $authNavReturnTo ?? (string) ($_SERVER['REQUEST_URI'] ?? '/');
$navViewer = $viewer ?? null;

$loginUrl = lurl('/login').'?return_to='.urlencode($navReturn);
$logoutUrl = lurl('/logout');

// $t is injected as a shared view var. Fall back to the raw key so a standalone
// render never fatals if the helper is missing.
$tr = isset($t) && is_callable($t) ? $t : static fn (string $key): string => $key;

if ($navVariant === 'platform') { ?>
    <!-- <li><a href="#" class="icon brands fa-twitter"><span class="label">Twitter</span></a></li>
    <li><a href="#" class="icon brands fa-facebook-f"><span class="label">Facebook</span></a></li>
    <li><a href="#" class="icon brands fa-snapchat-ghost"><span class="label">Snapchat</span></a></li>
    <li><a href="#" class="icon brands fa-instagram"><span class="label">Instagram</span></a></li>
    <li><a href="#" class="icon brands fa-medium-m"><span class="label">Medium</span></a></li> -->
    <?php if (!auth()->check()) { ?>
        <li><a href="<?= e($loginUrl) ?>" class="button" data-auth-login><?= e($tr('header.signIn')) ?></a></li>
    <?php } else { ?>
        <li><a href="/dashboard" class="button"><?= e($tr('header.dashboard')) ?></a></li>
        <li>
            <form method="post" action="<?= e($logoutUrl) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="return_to" value="<?= e($navReturn) ?>">
                <button type="submit" class="button"><?= e($tr('header.signOut')) ?></button>
            </form>
        </li>
    <?php }
    } else {
        if (!empty($navViewer)) { ?>
        <div class="nav-user">
          <button class="nav-user-btn" type="button" aria-haspopup="true" aria-expanded="false">
            <span class="nav-avatar">
              <?php if (!empty($navViewer['avatar_url'])) { ?>
                <img src="<?= e($navViewer['avatar_url']) ?>" alt="" />
              <?php } else { ?>
                <?= e(mb_substr((string) ($navViewer['name'] ?? '?'), 0, 1)) ?>
              <?php } ?>
            </span>
            <span class="nav-user-name"><?= e($navViewer['name'] ?? '') ?></span>
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
          </button>
          <div class="nav-user-menu" role="menu">
            <?php if (!empty($navViewer['is_reader'])) { ?>
              <a href="<?= e(lurl('/library')) ?>" role="menuitem">My Library</a>
            <?php } else { ?>
              <a href="<?= e(lurl('/dashboard')) ?>" role="menuitem">Dashboard</a>
            <?php } ?>
            <form method="post" action="<?= e($logoutUrl) ?>" class="nav-user-logout">
              <?= csrf_field() ?>
              <input type="hidden" name="return_to" value="<?= e($navReturn) ?>">
              <button type="submit" role="menuitem">Log out</button>
            </form>
          </div>
        </div>
    <?php } else { ?>
        <a href="<?= e($loginUrl) ?>" class="nav-pill">Log in</a>
    <?php }
    }
// Must close the tag. Includes are spliced into the layout as raw text, so
// leaving PHP mode open here would swallow the markup that follows.
?>
