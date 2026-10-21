<?php
// The one modal every panel page opens in. panel-dialog.js fills it from the link's
// own address; without scripting those links open the full page.
?>
<dialog class="panel-dialog" data-panel-dialog data-panel-error="<?= e(chrome_translate('panel.loadFailed')) ?>">
    <div class="panel-dialog-card">
        <div class="panel-dialog-body" data-panel-body tabindex="-1"></div>
        <button type="button" class="panel-dialog-close" data-panel-close aria-label="<?= e(chrome_translate('a11y.dismiss')) ?>">&times;</button>
    </div>
</dialog>
<script src="<?= e(versioned_asset('/assets/js/panel-dialog.js')) ?>" defer></script>
