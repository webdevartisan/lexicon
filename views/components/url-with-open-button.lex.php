<?php
$previewUrl = !empty($previewUrl) ? $previewUrl : '';
?>
<div class="flex align-middle w-full rounded border border-slate-300 dark:border-zink-600 bg-white dark:bg-zink-700 overflow-hidden">
    <span class="flex-1 px-3 py-2 text-sm text-slate-700 dark:text-zink-100 truncate">
        {{ previewUrl }}
    </span>
    <a 
    href="{{ previewUrl }}"
    target="_blank"
    rel="noopener"
    aria-label="Open in a new tab"
    title="Open in a new tab"
    class="flex items-center justify-center px-3 bg-slate-100 dark:bg-zink-600 text-slate-700 dark:text-zink-100 hover:bg-slate-200 dark:hover:bg-zink-500 border-l border-slate-300 dark:border-zink-600"
    >
    {% cache 'lucide:eye:hidden' ttl=31536000 %}<i data-lucide="eye" class="size-4" aria-hidden="true"></i>{% endcache %}
    </a>
</div>