<?php
/**
 * Shared yes/no confirmation, opened through cpConfirm() in cp-assets/js/confirm.js
 * and driven by modal.js. The title, message and button labels are filled in
 * when it opens, so only the tone is fixed here.
 *
 * Attributes:
 * - id: DOM id the hidden trigger in the layout points at
 * - tone: 'default', 'danger' or 'warning'
 */
$id = $id ?? 'confirmModal';
$tone = $tone ?? 'default';

$iconName = $tone === 'default' ? 'shield-question' : 'alert-triangle';
?>
<div id="<?= e($id) ?>" modal-center role="alertdialog" aria-modal="true" aria-labelledby="<?= e($id) ?>-title" aria-describedby="<?= e($id) ?>-message" tabindex="-1"
    class="fixed flex flex-col hidden transition-all duration-300 ease-in-out left-2/4 z-drawer -translate-x-2/4 -translate-y-2/4 show">
    <div class="confirm-card w-[calc(100vw-2rem)] overflow-hidden md:w-[24rem] rounded-2xl" data-tone="<?= e($tone) ?>">
        <div class="flex flex-col items-center gap-2 p-8 text-center">
            <div class="confirm-badge flex items-center justify-center rounded-full size-12">
                <i data-lucide="<?= e($iconName) ?>" class="size-6" aria-hidden="true"></i>
            </div>
            <h2 id="<?= e($id) ?>-title" data-modal-title class="text-base font-semibold">Are you sure?</h2>
            <p id="<?= e($id) ?>-message" data-modal-message class="confirm-message text-sm font-medium"></p>
        </div>
        <div class="confirm-actions grid grid-cols-2">
            <button type="button" data-modal-close="<?= e($id) ?>" data-modal-cancel
                class="confirm-button h-12 text-sm font-medium transition-colors">Cancel</button>
            <button type="button" data-modal-confirm
                class="confirm-button confirm-button-ok h-12 text-sm font-medium transition-colors">Confirm</button>
        </div>
    </div>
</div>
