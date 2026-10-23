<?php
/**
 * Modal dialog, opened by any element with data-modal-target="{id}" and
 * driven by /cp-assets/js/modal.js (focus, Escape, and Tab kept inside).
 *
 * Attributes:
 * - message: plain text, escaped here
 * - body: pre-escaped HTML shown instead of message, for a modal holding form
 *         fields; the caller is responsible for escaping everything in it
 * - form: id of the form the confirm button submits
 * - openOnLoad: open as soon as the page loads, e.g. to show validation errors
 * - noConfirm: only the cancel button, for a modal with nothing to confirm
 * - bare: no header or footer bar; the body is the whole dialog, with a floating close button
 */
$id = $id ?? 'modal';
$title = $title ?? 'Modal Title';
$icon = $icon ?? null;
$variant = $variant ?? 'default';
$size = $size ?? 'md';
$message = $message ?? '';
$body = $body ?? null;
$cancelText = $cancelText ?? 'Cancel';
$confirmText = $confirmText ?? 'Confirm';
$confirmIcon = $confirmIcon ?? null;
$form = $form ?? null;
$openOnLoad = !empty($openOnLoad);
$noConfirm = !empty($noConfirm);
$bare = !empty($bare);

// danger and warning tint the card like the shared confirmation dialog; everything else is the default blue
$tone = in_array($variant, ['danger', 'warning'], true) ? $variant : 'default';
$badgeIcon = $icon ?? match ($tone) {
    'default' => 'info',
    default => 'alert-triangle',
};

$modalWidths = match ($size) {
    'sm' => 'md:w-[24rem]',
    'md' => 'md:w-[28rem]',
    'lg' => 'md:w-[40rem]',
    'xl' => 'md:w-[50rem]',
    default => 'md:w-[28rem]'
};
?>

<div id="<?= e($id) ?>" modal-center role="dialog" aria-modal="true" aria-labelledby="<?= e($id) ?>-title" tabindex="-1"
    <?= $openOnLoad ? 'data-modal-open-on-load' : '' ?>
    class="fixed flex flex-col hidden transition-all duration-300 ease-in-out left-2/4 z-drawer -translate-x-2/4 -translate-y-2/4 show">
    <div class="confirm-card w-[calc(100vw-2rem)] <?= $modalWidths ?> overflow-hidden flex flex-col max-h-[calc(100vh-2rem)] <?= $bare ? 'relative' : '' ?>" data-tone="<?= e($tone) ?>">

        <?php if ($bare) { ?>
        <h5 id="<?= e($id) ?>-title" class="sr-only"><?= e($title) ?></h5>
        <button type="button" data-modal-close="<?= e($id) ?>" aria-label="Close"
            class="absolute top-3 ltr:right-3 rtl:left-3 z-10 focus:outline-none inline-flex items-center justify-center size-8 rounded-lg confirm-message hover:bg-slate-500/15 transition-colors">
            <i data-lucide="x" class="size-4"></i>
        </button>
        <div class="overflow-y-auto">
            <?= $body ?>
        </div>
        <?php } else { ?>

        <div class="flex flex-col items-center gap-2 px-6 pt-8 pb-4 text-center">
            <div class="confirm-badge flex items-center justify-center rounded-full size-12">
                {% cache 'lucide:modal-badge:' . $badgeIcon ttl=31536000 %}<i data-lucide="<?= e($badgeIcon) ?>" class="size-6" aria-hidden="true"></i>{% endcache %}
            </div>
            <h2 id="<?= e($id) ?>-title" class="text-base font-semibold"><?= e($title) ?></h2>
        </div>

        <div class="px-6 pb-6 overflow-y-auto">
            <?php if ($body !== null) { ?>
                <?= $body ?>
            <?php } else { ?>
                <p class="confirm-message text-sm font-medium text-center"><?= e($message) ?></p>
            <?php } ?>
        </div>

        <div class="confirm-actions grid <?= $noConfirm ? 'grid-cols-1' : 'grid-cols-2' ?> mt-auto">
            <button type="button" data-modal-close="<?= e($id) ?>"
                class="confirm-button h-12 text-sm font-medium transition-colors"><?= e($cancelText) ?></button>
            <?php if (!$noConfirm) { ?>
            <button type="<?= $form ? 'submit' : 'button' ?>"
                    <?php if ($form) { ?>form="<?= e($form) ?>"<?php } ?>
                    class="confirm-button confirm-button-ok h-12 text-sm font-medium transition-colors">
                <?php if ($confirmIcon) { ?>
                    {% cache 'lucide:modal-confirm:' . $confirmIcon ttl=31536000 %}<i data-lucide="<?= e($confirmIcon) ?>" class="inline-block size-4 mr-1 align-[-2px]" aria-hidden="true"></i>{% endcache %}
                <?php } ?>
                <?= e($confirmText) ?>
            </button>
            <?php } ?>
        </div>
        <?php } ?>

    </div>
</div>
