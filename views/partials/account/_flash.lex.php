<?php
// Flash messages for an account panel. The dialog has no layout to render the
// toast, and leaving them in the session would replay them on the next full page.
foreach (flash() as $flashType => $flashMessages) {
    foreach ($flashMessages as $flashMessage) { ?>
<p class="acct-flash is-<?= e($flashType) ?>" role="status"><?= e($flashMessage) ?></p>
<?php }
} ?>
