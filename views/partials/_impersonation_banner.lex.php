<?php
/**
 * Persistent banner shown on every page while an administrator is signed in as
 * someone else. Included first inside <body> by the back and front layouts and
 * by all five theme base layouts, so it is also the first thing a keyboard or
 * screen reader reaches.
 *
 * A region rather than an alert: an alert would be re-announced on every page
 * load, which trains people to tune it out.
 *
 * No early return: includes are inlined into the layout at compile time, so a
 * return here would end the whole page.
 */
if (!empty($impersonation)) {
    $impTarget = '@'.$impersonation['target_handle'];
    $impAdmin = '@'.$impersonation['admin_handle'];
    $impMinutes = (int) $impersonation['minutes_left'];
?>
<link rel="stylesheet" href="/assets/css/impersonation-banner.css">
<div class="lx-impersonation" role="region" aria-label="Signed in as another account">
    <p class="lx-impersonation-text">
        <strong>You are signed in as <?= e($impTarget) ?>.</strong>
        Anything you do is saved as them and recorded against <?= e($impAdmin) ?>.
        <span class="lx-impersonation-time">Ends automatically in <?= e((string) $impMinutes) ?> min.</span>
    </p>
    <form method="post" action="<?= e(lurl('/impersonation/exit')) ?>" class="lx-impersonation-form">
        <?= csrf_field() ?>
        <button type="submit" class="lx-impersonation-exit">Return to <?= e($impAdmin) ?></button>
    </form>
</div>
<?php } ?>
