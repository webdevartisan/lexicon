<?php
// calculate health status for badge display.
$isWarning = $stats['files_over_limit'] > 0;
$needsCleanup = !$isWarning && $stats['expired_files'] > ($stats['live_files'] * 0.2);
$isHealthy = !$isWarning && !$needsCleanup;

$compiledCount = (int) ($stats['compiled_views_count'] ?? 0);
$compiledBytes = (int) ($stats['compiled_views_size_bytes'] ?? 0);
?>

<div class="card">
    <div class="card-body">
        <?php /* Card Header */ ?>
        <div class="flex items-center justify-between mb-4">
            <h6 class="text-15 flex items-center gap-2">
                <i class="size-5 text-custom-500" data-lucide="database"></i>
                Cache Statistics
            </h6>
            
            <?php /* Health Badge */ ?>
            <?php if ($isWarning) { ?>
                <span class="px-3 py-1 text-xs font-medium rounded-md bg-yellow-100 text-yellow-600 dark:bg-yellow-500/20 dark:text-yellow-300">
                    Warning
                </span>
            <?php } elseif ($needsCleanup) { ?>
                <span class="px-3 py-1 text-xs font-medium rounded-md bg-orange-100 text-orange-600 dark:bg-orange-500/20 dark:text-orange-300">
                    Needs Cleanup
                </span>
            <?php } else { ?>
                <span class="px-3 py-1 text-xs font-medium rounded-md bg-green-100 text-green-600 dark:bg-green-500/20 dark:text-green-300">
                    Healthy
                </span>
            <?php } ?>
        </div>

        <?php /* Statistics Grid */ ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
            <div class="p-4 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">
                    <?= number_format($stats['total_files']) ?>
                </div>
                <div class="text-xs text-slate-500 dark:text-zink-300 mt-1">Total Files</div>
            </div>

            <div class="p-4 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">
                    <?= number_format($stats['live_files']) ?>
                </div>
                <div class="text-xs text-slate-500 dark:text-zink-300 mt-1">Live Files</div>
            </div>

            <div class="p-4 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">
                    <?= number_format($stats['expired_files']) ?>
                </div>
                <div class="text-xs text-slate-500 dark:text-zink-300 mt-1">Expired Files</div>
            </div>

            <div class="p-4 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">
                    <?= number_format($stats['total_size_bytes'] / 1024 / 1024, 1) ?> MB
                </div>
                <div class="text-xs text-slate-500 dark:text-zink-300 mt-1">Total Size</div>
            </div>

            <div class="p-4 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">
                    <?= number_format($compiledCount) ?>
                </div>
                <div class="text-xs text-slate-500 dark:text-zink-300 mt-1">Compiled Views</div>
                <div class="text-xs text-slate-400 dark:text-zink-400 mt-0.5">
                    <?= number_format($compiledBytes / 1024, 1) ?> KB
                </div>
            </div>
        </div>

        <?php /* Additional Details */ ?>
        <div class="space-y-3 text-sm border-t border-slate-200 dark:border-zink-500 pt-4">
            <div class="flex justify-between items-center">
                <span class="text-slate-600 dark:text-zink-300">Avg TTL Remaining:</span>
                <span class="font-medium text-slate-900 dark:text-zink-50">
                    <?php
                    if ($stats['avg_ttl_remaining'] >= 3600) {
                        echo number_format($stats['avg_ttl_remaining'] / 3600, 1).' hours';
                    } elseif ($stats['avg_ttl_remaining'] >= 60) {
                        echo round($stats['avg_ttl_remaining'] / 60).' minutes';
                    } else {
                        echo $stats['avg_ttl_remaining'].' seconds';
                    }
?>
                </span>
            </div>
            
            <div class="flex justify-between items-center">
                <span class="text-slate-600 dark:text-zink-300">GC Probability:</span>
                <span class="font-medium text-slate-900 dark:text-zink-50"><?= e($stats['gc_probability']) ?></span>
            </div>
            
            <div class="flex justify-between items-center">
                <span class="text-slate-600 dark:text-zink-300">Max Files Limit:</span>
                <span class="font-medium text-slate-900 dark:text-zink-50"><?= number_format($stats['max_files']) ?></span>
            </div>

            <div class="flex justify-between items-center">
                <span class="text-slate-600 dark:text-zink-300">Compiled Views:</span>
                <span class="font-medium text-slate-900 dark:text-zink-50">
                    <?= number_format($compiledCount) ?> files
                    &mdash;
                    <?= number_format($compiledBytes / 1024, 1) ?> KB
                </span>
            </div>

            <?php if ($stats['files_over_limit'] > 0) { ?>
            <div class="flex items-center justify-between p-3 bg-yellow-50 dark:bg-yellow-500/10 border border-yellow-200 dark:border-yellow-500/30 rounded-md mt-3">
                <span class="text-yellow-700 dark:text-yellow-300 font-medium flex items-center gap-2">
                    <i class="size-4" data-lucide="alert-triangle"></i>
                    Files over limit
                </span>
                <span class="font-semibold text-yellow-900 dark:text-yellow-200">+<?= number_format($stats['files_over_limit']) ?></span>
            </div>
            <?php } ?>
        </div>

        <?php /* Actions */ ?>
        <?php if ($isAdmin) { ?>
        <div class="flex flex-wrap gap-2 justify-end mt-5 pt-4 border-t border-slate-200 dark:border-zink-500">
            <form action="<?= buildLocalizedUrl('/admin/cache/prune') ?>" method="POST" class="inline-block">
                <?= csrf_field() ?>
                <button type="submit" 
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-orange-600 bg-orange-50 border border-orange-200 rounded-md hover:bg-orange-100 dark:bg-orange-500/10 dark:border-orange-500/30 dark:text-orange-300 dark:hover:bg-orange-500/20 transition-colors"
                        onclick="return confirm('Delete all expired cache files? This is safe and recommended.')">
                    <i class="size-4" data-lucide="trash-2"></i>
                    Prune Expired
                </button>
            </form>

            <form action="<?= buildLocalizedUrl('/admin/cache/clear') ?>" method="POST" class="inline-block">
                <?= csrf_field() ?>
                <button type="submit" 
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/20 transition-colors"
                        onclick="return confirm('WARNING: This will delete ALL cache files. Are you absolutely sure?')">
                    <i class="size-4" data-lucide="x-circle"></i>
                    Clear All Cache
                </button>
            </form>

            <a href="<?= buildLocalizedUrl('/admin/cache') ?>" 
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 hover:border-custom-600 dark:bg-custom-500 dark:border-custom-500 dark:hover:bg-custom-600 dark:hover:border-custom-600 transition-colors">
                View Details
                <i class="size-4" data-lucide="chevron-right"></i>
            </a>
        </div>
        <?php } ?>
    </div>
</div>
