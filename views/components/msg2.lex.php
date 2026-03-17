<?php
[$classContainer, $classBtn, $classProgress, $icon] = match ($type) {
    'success' => [
        'relative flex items-start gap-3 p-4 pr-12 text-sm bg-green-50 border-l-4 border-green-500 rounded-md text-green-800 shadow-md dark:bg-green-900/20 dark:text-green-200',
        'absolute top-3 right-3 p-1.5 text-green-600 transition rounded-md hover:bg-green-100 dark:text-green-300 dark:hover:bg-green-800/30',
        'bg-green-500',
        'check-circle',
    ],
    'error' => [
        'relative flex items-start gap-3 p-4 pr-12 text-sm bg-red-50 border-l-4 border-red-500 rounded-md text-red-800 shadow-md dark:bg-red-900/20 dark:text-red-200',
        'absolute top-3 right-3 p-1.5 text-red-600 transition rounded-md hover:bg-red-100 dark:text-red-300 dark:hover:bg-red-800/30',
        'bg-red-500',
        'alert-circle',
    ],
    'warning' => [
        'relative flex items-start gap-3 p-4 pr-12 text-sm bg-orange-50 border-l-4 border-orange-500 rounded-md text-orange-800 shadow-md dark:bg-orange-900/20 dark:text-orange-200',
        'absolute top-3 right-3 p-1.5 text-orange-600 transition rounded-md hover:bg-orange-100 dark:text-orange-300 dark:hover:bg-orange-800/30',
        'bg-orange-500',
        'alert-triangle',
    ],
    'info' => [
        'relative flex items-start gap-3 p-4 pr-12 text-sm bg-custom-50 border-l-4 border-custom-500 rounded-md text-custom-800 shadow-md dark:bg-custom-200/20 dark:text-custom-400',
        'absolute top-3 right-3 p-1.5 text-custom-600 transition rounded-md hover:bg-custom-100 dark:text-custom-300 dark:hover:bg-custom-800/30',
        'bg-custom-500',
        'info',
    ],
    default => [
        'relative flex items-start gap-3 p-4 pr-12 text-sm bg-slate-50 border-l-4 border-slate-500 rounded-md text-slate-800 shadow-md dark:bg-zink-900/20 dark:text-zink-200',
        'absolute top-3 right-3 p-1.5 text-slate-600 transition rounded-md hover:bg-slate-100 dark:text-zink-300 dark:hover:bg-zink-800/30',
        'bg-slate-500',
        'bell',
    ],
};

$type = ucwords($type);
$autoClose = $autoClose ?? 10000;
?>

<div class="mb-4 transition-all duration-300 {{ classContainer }}" 
     data-closable 
     data-auto-close="{{ autoClose }}">
    
    <!-- Icon -->
    <div class="flex-shrink-0 mt-0.5">
        <i data-lucide="{{ icon }}" class="h-5 w-5"></i>
    </div>
    
    <!-- Content -->
    <div class="flex-1">
        <span class="font-semibold">{{ type }}:</span>
        <span class="ml-1">{{ msg }}</span>
    </div>
    
    <!-- Close Button -->
    <button class="{{ classBtn }}" data-close aria-label="Close">
        <i data-lucide="x" class="h-4 w-4"></i>
    </button>
    
    <!-- Progress Bar -->
    <?php if ($autoClose > 0) { ?>
    <div class="absolute bottom-0 left-0 right-0 h-1 bg-black/5 dark:bg-white/5 rounded-b-md overflow-hidden">
        <div class="{{ classProgress }} h-full transition-all duration-100 ease-linear" 
             data-progress-bar 
             style="width: 100%"></div>
    </div>
    <?php } ?>
</div>