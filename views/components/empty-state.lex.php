<?php
/**
 * Empty State Card
 *
 * Centered placeholder shown when a listing has no rows.
 *
 * Attributes:
 * - icon: lucide icon name
 * - title: short headline
 * - message: supporting text
 */
$icon = (string) ($icon ?? 'inbox');
$title = (string) ($title ?? 'Nothing here yet');
$message = (string) ($message ?? '');
?>
<div class="card">
    <div class="card-body text-center py-12">
        {% cache 'lucide:empty-state:' . $icon ttl=3600 %}<i data-lucide="<?= e($icon) ?>" class="size-12 text-slate-400 mx-auto mb-3"></i>{% endcache %}
        <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1"><?= e($title) ?></h3>
        <?php if ($message !== '') { ?>
        <p class="text-sm text-slate-500 dark:text-zink-300"><?= e($message) ?></p>
        <?php } ?>
    </div>
</div>
