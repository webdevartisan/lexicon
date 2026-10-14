<?php
// Card header for the invitation wizard. Needs $inviteStep (1-3, or 4 once
// everything is done) and optionally $inviteStepsInteractive, which turns the
// steps into buttons so finished ones can be revisited.
$inviteStepNames = [$t('auth.inviteStepInvitation'), $t('auth.inviteStepAccount'), $t('auth.inviteStepJoined')];
$inviteStepIcons = [
    '<path d="M4 6h16v12H4z"/><path d="M4 7l8 6 8-6"/>',
    '<circle cx="12" cy="8" r="4"/><path d="M4 20c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
    '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
];
$inviteStepsInteractive = !empty($inviteStepsInteractive);
$inviteShown = min($inviteStep, 3);
$inviteCountTpl = $t('auth.inviteStepCount', ['n' => '{n}', 'total' => '{total}']);
$inviteProgress = round($inviteShown / 3 * 100);
?>
<header class="lx-invite-head">
    <div class="lx-invite-head-row">
        <p class="lx-invite-badge" data-steps-count data-tpl="<?= e($inviteCountTpl) ?>" aria-live="polite"><?= e($t('auth.inviteStepCount', ['n' => $inviteShown, 'total' => 3])) ?></p>
    </div>

    <div class="lx-invite-progress" aria-hidden="true"><span data-steps-bar style="width: <?= $inviteProgress ?>%"></span></div>

    <ol class="lx-invite-steps" aria-label="<?= e($t('auth.inviteStepsLabel')) ?>">
        <?php foreach ($inviteStepNames as $i => $inviteStepName) {
            $n = $i + 1;
            $state = $n < $inviteStep ? ' is-done' : ($n === $inviteStep ? ' is-current' : '');
            $tag = $inviteStepsInteractive ? 'button' : 'span';
            $attrs = $inviteStepsInteractive
                ? ' type="button"'.($n > $inviteStep ? ' disabled' : '').($n === $inviteStep ? ' aria-current="step"' : '')
                : ($n === $inviteShown ? ' aria-current="step"' : ''); ?>
            <li class="lx-invite-step<?= $state ?>">
                <<?= $tag ?> class="lx-invite-step-btn"<?= $attrs ?>>
                    <span class="lx-invite-step-icon" aria-hidden="true">
                        <svg class="lx-invite-step-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $inviteStepIcons[$i] ?></svg>
                        <svg class="lx-invite-step-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                    </span>
                    <span class="lx-invite-step-name"><?= e($inviteStepName) ?></span>
                </<?= $tag ?>>
            </li>
        <?php } ?>
    </ol>
</header>
