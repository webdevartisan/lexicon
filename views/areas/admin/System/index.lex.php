{% extends "back.lex.php" %}

{% block title %}System{% endblock %}
{% block subtitle %}Runtime configuration, database footprint, and application logs.{% endblock %}

{% block body %}
<?php
$fmtBytes = static function ($bytes): string {
    $bytes = (float) $bytes;
    foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
        if ($bytes < 1024) {
            return round($bytes, 1).' '.$unit;
        }
        $bytes /= 1024;
    }

    return round($bytes, 1).' TB';
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
        <!-- PHP runtime -->
        <div class="card">
            <div class="card-body">
                <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-3">PHP runtime</h3>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                    <?php
                    $phpLabels = [
                        'version' => 'Version', 'sapi' => 'SAPI', 'memory_limit' => 'Memory limit',
                        'max_execution_time' => 'Max execution', 'upload_max_filesize' => 'Upload max',
                        'post_max_size' => 'POST max', 'opcache' => 'OPcache', 'extensions' => 'Extensions',
                    ];
foreach ($phpLabels as $key => $label) { ?>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1"><?= e($label) ?></dt>
                        <dd class="text-slate-900 dark:text-zink-50"><?= e((string) $php[$key]) ?></dd>
                    </div>
                    <?php } ?>
                </dl>
            </div>
        </div>

        <!-- Database -->
        <div class="card">
            <div class="card-body p-0">
                <div class="p-4 pb-2 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50">Database tables</h3>
                    <span class="text-xs text-slate-500 dark:text-zink-300">
                        Total: <?= $fmtBytes(array_sum(array_column($tables, 'size_bytes'))) ?>
                    </span>
                </div>
                <div class="overflow-x-auto max-h-72 overflow-y-auto">
                    <table class="w-full whitespace-nowrap text-sm">
                        <thead class="text-left bg-slate-100 dark:bg-zink-600 sticky top-0">
                            <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                                <th class="px-4 py-2 font-semibold">Table</th>
                                <th class="px-4 py-2 font-semibold text-right">Rows (est.)</th>
                                <th class="px-4 py-2 font-semibold text-right">Size</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-zink-600">
                            <?php foreach ($tables as $t) { ?>
                            <tr>
                                <td class="px-4 py-1.5 font-medium text-slate-900 dark:text-zink-50"><?= e($t['name']) ?></td>
                                <td class="px-4 py-1.5 text-right text-slate-500 dark:text-zink-300"><?= number_format((int) $t['rows_estimate']) ?></td>
                                <td class="px-4 py-1.5 text-right text-slate-500 dark:text-zink-300"><?= $fmtBytes($t['size_bytes']) ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Logs -->
    <div class="card">
        <div class="card-body">
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-3">Application logs</h3>

            <?php if (empty($logs)) { ?>
            <p class="text-sm text-slate-500 dark:text-zink-300 py-4 text-center">No log files in storage/logs.</p>
            <?php } else { ?>
            <div class="flex flex-wrap gap-2 mb-4">
                <?php foreach ($logs as $name => $info) {
                    $isActive = $name === $selectedLog; ?>
                <a href="/admin/system?log=<?= e(urlencode($name)) ?>"
                   class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full border transition-colors <?= $isActive
                       ? 'text-white bg-custom-500 border-custom-500'
                       : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:border-zink-400' ?>">
                    <?= e($name) ?>
                    <span class="text-[10px] opacity-70"><?= $fmtBytes($info['size']) ?></span>
                </a>
                <?php } ?>
            </div>

            <?php if ($selectedLog !== '') { ?>
            <p class="text-xs text-slate-500 dark:text-zink-300 mb-2">
                Last 200 lines of <?= e($selectedLog) ?> · updated <?= e(date('M j, Y · g:i a', $logs[$selectedLog]['modified'])) ?>
            </p>
            <pre class="p-4 text-xs leading-relaxed rounded-md bg-slate-900 text-slate-100 dark:bg-zink-900 overflow-x-auto max-h-96 overflow-y-auto whitespace-pre-wrap break-words"><?= e($logContent === '' ? '(empty file)' : $logContent) ?></pre>
            <?php } else { ?>
            <p class="text-sm text-slate-500 dark:text-zink-300">Pick a log file to view its tail.</p>
            <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>
{% endblock %}
