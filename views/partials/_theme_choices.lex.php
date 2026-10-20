<?php
// Light, dark or device: the three buttons in the user menu and on the
// Preferences page. appearance.js wires every [data-theme-choice] it finds and
// keeps their aria-pressed in step, so both stay in sync. Set
// $themeChoicesLabelled to show each name beside its icon.
$tcTr = isset($t) && is_callable($t) ? $t : static fn (string $key, array $p = []): string => $key;
$tcChoices = [
    'light' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
    'dark' => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9z"/>',
    'auto' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
];
?>
<span class="nav-theme-choices<?= !empty($themeChoicesLabelled) ? ' is-labelled' : '' ?>" role="group" aria-label="<?= e($tcTr('layout.themeMenu.label')) ?>">
    <?php foreach ($tcChoices as $tcChoice => $tcIcon) { ?>
    <button type="button" data-theme-choice="<?= e($tcChoice) ?>" aria-pressed="false"
            title="<?= e($tcTr('layout.themeMenu.'.$tcChoice)) ?>" aria-label="<?= e($tcTr('layout.themeMenu.'.$tcChoice)) ?>">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $tcIcon ?></svg>
        <?php if (!empty($themeChoicesLabelled)) { ?><span><?= e($tcTr('layout.themeMenu.'.$tcChoice)) ?></span><?php } ?>
    </button>
    <?php } ?>
</span>
