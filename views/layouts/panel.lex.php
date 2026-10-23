<?php
// A page shown inside the modal: no site header, menu or footer around it.
// The language travels with it so the modal can tell when a save switched it.
?>
<div class="panel-page" lang="{{ chromeLang }}" dir="{{ chromeDir }}">
<?php foreach (flash() as $flashType => $flashMessages) {
    foreach ($flashMessages as $flashMessage) { ?>
<p class="panel-flash is-<?= e($flashType) ?>" role="status"><?= e($flashMessage) ?></p>
<?php }
} ?>
{% yield body %}
</div>
{% yield scripts %}
