<?php /* Shared validation error block for admin forms */ ?>
{% if errors|notempty %}
<div class="px-4 py-3 mb-4 text-sm text-red-600 border border-red-200 rounded-md bg-red-50 dark:bg-red-500/10 dark:border-red-500/40 dark:text-red-300">
    <div class="flex items-center gap-2 mb-1 font-medium">
        <i data-lucide="alert-circle" class="size-4"></i> Please fix the following:
    </div>
    <ul class="list-disc ltr:pl-5 rtl:pr-5">
        {% foreach ($errors as $error): %}
        <li><?= e(is_array($error) ? implode(' ', $error) : $error) ?></li>
        {% endforeach; %}
    </ul>
</div>
{% endif %}
