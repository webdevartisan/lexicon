<?php
[$classContainer, $classBtn, $classProgress, $icon] = match ($type) {
    'success' => [
        'bg-green-50 border-green-500 text-green-800 dark:bg-zink-700 dark:text-green-200',
        'text-green-600 hover:bg-green-100 dark:text-green-300 dark:hover:bg-green-800/30',
        'bg-green-500',
        'check-circle',
    ],
    'error' => [
        'bg-red-50 border-red-500 text-red-800 dark:bg-zink-700 dark:text-red-200',
        'text-red-600 hover:bg-red-100 dark:text-red-300 dark:hover:bg-red-800/30',
        'bg-red-500',
        'alert-circle',
    ],
    'warning' => [
        'bg-orange-50 border-orange-500 text-orange-800 dark:bg-zink-700 dark:text-orange-200',
        'text-orange-600 hover:bg-orange-100 dark:text-orange-300 dark:hover:bg-orange-800/30',
        'bg-orange-500',
        'alert-triangle',
    ],
    'info' => [
        'bg-custom-50 border-custom-500 text-custom-800 dark:bg-zink-700 dark:text-custom-400',
        'text-custom-600 hover:bg-custom-100 dark:text-custom-300 dark:hover:bg-custom-800/30',
        'bg-custom-500',
        'info',
    ],
    default => [
        'bg-slate-50 border-slate-500 text-slate-800 dark:bg-zink-700 dark:text-zink-200',
        'text-slate-600 hover:bg-slate-100 dark:text-zink-300 dark:hover:bg-zink-800/30',
        'bg-slate-500',
        'bell',
    ],
};

$type = ucwords($type);
$autoClose = $autoClose ?? 10000;
?>

<div class="toast-enter relative flex items-start gap-3 w-full p-4 pr-12 text-sm border-l-4 rounded-md shadow-lg pointer-events-auto transition-all duration-300 {{ classContainer }}"
     data-closable
     data-auto-close="{{ autoClose }}">
    
    <!-- Icon -->
    <div class="flex-shrink-0 mt-0.5">
        {% cache 'lucide:msg2-icon:' . $icon ttl=31536000 %}<i data-lucide="{{ icon }}" class="h-5 w-5"></i>{% endcache %}
    </div>
    
    <!-- Content -->
    <div class="flex-1">
        <span class="font-semibold">{{ type }}:</span>
        <span class="ml-1">{{ msg }}</span>
    </div>
    
    <!-- Close Button -->
    <button class="absolute top-3 right-3 p-1.5 transition rounded-md {{ classBtn }}" data-close aria-label="Close">
        {% cache 'lucide:x:msg2-close' ttl=31536000 %}<i data-lucide="x" class="h-4 w-4"></i>{% endcache %}
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