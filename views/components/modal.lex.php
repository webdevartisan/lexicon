<?php
$id = $id ?? 'modal';
$title = $title ?? 'Modal Title';
$icon = $icon ?? null;
$variant = $variant ?? 'default';
$size = $size ?? 'md';
$message = $message ?? ''; // Simple text message
$cancelText = $cancelText ?? 'Cancel';
$confirmText = $confirmText ?? 'Confirm';
$confirmIcon = $confirmIcon ?? null;
$form = $form ?? null;

// Variant-based styling
$headerClasses = match ($variant) {
    'danger' => 'bg-red-500 dark:bg-red-600 border-red-200 dark:border-red-700 text-white',
    'warning' => 'bg-yellow-500 dark:bg-yellow-600 border-yellow-200 dark:border-yellow-700 text-white',
    'success' => 'bg-green-500 dark:bg-green-600 border-green-200 dark:border-green-700 text-white',
    'info' => 'bg-custom-500 dark:bg-custom-600 border-custom-200 dark:border-custom-700 text-white',
    default => 'bg-white dark:bg-zink-600 border-slate-200 dark:border-zink-500 text-slate-800 dark:text-zink-100'
};

$confirmBtnClasses = match ($variant) {
    'danger' => 'text-white btn bg-red-500 border-red-500 hover:text-white hover:bg-red-600 hover:border-red-600 focus:text-white focus:bg-red-600 focus:border-red-600 focus:ring focus:ring-red-100 active:text-white active:bg-red-600 active:border-red-600 active:ring active:ring-red-100 dark:ring-red-400/20',
    'warning' => 'text-white btn bg-yellow-500 border-yellow-500 hover:text-white hover:bg-yellow-600 hover:border-yellow-600 focus:text-white focus:bg-yellow-600 focus:border-yellow-600 focus:ring focus:ring-yellow-100 active:text-white active:bg-yellow-600 active:border-yellow-600 active:ring active:ring-yellow-100 dark:ring-yellow-400/20',
    'success' => 'text-white btn bg-green-500 border-green-500 hover:text-white hover:bg-green-600 hover:border-green-600 focus:text-white focus:bg-green-600 focus:border-green-600 focus:ring focus:ring-green-100 active:text-white active:bg-green-600 active:border-green-600 active:ring active:ring-green-100 dark:ring-green-400/20',
    default => 'text-white btn bg-custom-500 border-custom-500 hover:text-white hover:bg-custom-600 hover:border-custom-600 focus:text-white focus:bg-custom-600 focus:border-custom-600 focus:ring focus:ring-custom-100 active:text-white active:bg-custom-600 active:border-custom-600 active:ring active:ring-custom-100 dark:ring-custom-400/20'
};

$modalWidths = match ($size) {
    'sm' => 'md:w-[24rem]',
    'md' => 'md:w-[30rem]',
    'lg' => 'md:w-[40rem]',
    'xl' => 'md:w-[50rem]',
    default => 'md:w-[30rem]'
};

$headerTextColor = ($variant === 'default')
    ? 'text-slate-800 dark:text-zink-100'
    : 'text-white';
?>

<div id="<?= $id ?>" modal-center
    class="fixed flex flex-col hidden transition-all duration-300 ease-in-out left-2/4 z-drawer -translate-x-2/4 -translate-y-2/4 show">
    <div class="w-screen <?= $modalWidths ?> bg-white shadow rounded-md dark:bg-zink-600 flex flex-col h-full">
        
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b <?= $headerClasses ?>">
            <h5 class="text-16 <?= $headerTextColor ?> flex items-center gap-2">
                <?php if ($icon) { ?>
                    <i data-lucide="<?= $icon ?>" class="size-5"></i>
                <?php } ?>
                <?= $title ?>
            </h5>
            <button data-modal-close="<?= $id ?>"
                class="transition-all duration-200 ease-linear <?= ($variant === 'default') ? 'text-slate-500 hover:text-slate-700' : 'text-white hover:text-white/80' ?>">
                <i data-lucide="x" class="size-5"></i>
            </button>
        </div>

        <!-- Content -->
        <div class="max-h-[calc(theme('height.screen')_-_180px)] p-4 overflow-y-auto">
            <p class="text-slate-600 dark:text-zink-300"><?= $message ?></p>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-end gap-2 p-4 mt-auto border-t border-slate-200 dark:border-zink-500">
            <button type="button" data-modal-close="<?= $id ?>"
                class="text-slate-500 btn bg-slate-200 border-slate-200 hover:text-slate-600 hover:bg-slate-300 hover:border-slate-300 focus:text-slate-600 focus:bg-slate-300 focus:border-slate-300 focus:ring focus:ring-slate-100 active:text-slate-600 active:bg-slate-300 active:border-slate-300 active:ring active:ring-slate-100 dark:bg-zink-600 dark:hover:bg-zink-500 dark:border-zink-600 dark:hover:border-zink-500 dark:text-zink-200 dark:ring-zink-400/50">
                <?= $cancelText ?>
            </button>
            <button type="<?= $form ? 'submit' : 'button' ?>" 
                    <?php if ($form) { ?>form="<?= $form ?>"<?php } ?>
                    class="<?= $confirmBtnClasses ?>">
                <?php if ($confirmIcon) { ?>
                    <i data-lucide="<?= $confirmIcon ?>" class="inline-block size-4 mr-1"></i>
                <?php } ?>
                <?= $confirmText ?>
            </button>
        </div>

    </div>
</div>
