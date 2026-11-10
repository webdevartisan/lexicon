{% extends "back.lex.php" %}

{% block title %}Cache Management{% endblock %}
{% block subtitle %}Monitor and manage application cache system{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    {% if cacheStats.enabled == false %}
    <div class="mb-5 px-4 py-3 rounded-md border border-amber-200 bg-amber-50 text-amber-800 dark:bg-amber-900/40 dark:border-amber-800 dark:text-amber-100 flex items-start gap-3">
        {% cache 'lucide:info' ttl=31536000 %}<i data-lucide="info" class="size-4 mt-0.5 shrink-0"></i>{% endcache %}
        <div class="text-sm">
            <p class="font-medium">Response caching is off (CACHE_ENABLED=false).</p>
            <p class="mt-0.5">Pages are rendered fresh on every request. Templates still compile, but remember the setting they were built under. After switching caching back on, use Rebuild templates below.</p>
        </div>
    </div>
    {% endif %}

    <?php
    $staleViews = (int) ($cacheStats['compiled_views_stale'] ?? 0);
    $layerCards = [
        ['key' => 'pages', 'icon' => 'file-text', 'title' => 'Pages',
            'text' => 'Whole pages saved for visitors who are signed out. Clearing them is safe, each page is saved again on its next visit.'],
        ['key' => 'fragments', 'icon' => 'puzzle', 'title' => 'Fragments',
            'text' => 'Small saved pieces inside pages, like the sidebar and icons. Clear them after changing menus or icons.'],
    ];
    ?>

    <?php if ($staleViews > 0) { ?>
    <div class="mb-5 px-4 py-3 rounded-md border border-orange-200 bg-orange-50 text-orange-800 dark:bg-orange-500/10 dark:border-orange-500/30 dark:text-orange-200 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-start gap-3">
            {% cache 'lucide:alert-triangle:stale' ttl=31536000 %}<i data-lucide="alert-triangle" class="size-4 mt-0.5 shrink-0"></i>{% endcache %}
            <div class="text-sm">
                <p class="font-medium"><?= number_format($staleViews) ?> compiled templates were built <?= $cacheStats['enabled'] ? 'while caching was off' : 'while caching was on' ?>.</p>
                <p class="mt-0.5">They keep that behaviour until rebuilt, so icons may appear late and caching may not apply on some pages.</p>
            </div>
        </div>
        <form action="<?= buildLocalizedUrl('/admin/cache/rebuild-views') ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-orange-500 border border-orange-500 rounded-md hover:bg-orange-600 transition-colors">
                Rebuild templates
            </button>
        </form>
    </div>
    <?php } ?>

    <div class="mb-3 flex justify-end gap-4">
        <a href="<?= buildLocalizedUrl('/admin/cache/browse') ?>" class="text-sm font-medium text-custom-500 hover:underline">Browse stored entries</a>
        <a href="<?= buildLocalizedUrl('/admin/cache/browse-compiled') ?>" class="text-sm font-medium text-custom-500 hover:underline">Browse compiled templates</a>
    </div>

    <?php /* One card per stored layer, so each clear button says what it touches */ ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        <?php foreach ($layerCards as $layerCard) { ?>
        <div class="card">
            <div class="card-body flex flex-col h-full">
                <h6 class="mb-2 text-15 flex items-center gap-2">
                    {% cache 'lucide:layer:' . $layerCard['icon'] ttl=31536000 %}<i class="size-5 text-custom-500" data-lucide="<?= e($layerCard['icon']) ?>"></i>{% endcache %}
                    <?= e($layerCard['title']) ?>
                </h6>
                <p class="text-2xl font-semibold text-slate-900 dark:text-zink-50">
                    <?= number_format($cacheStats['layers'][$layerCard['key']]['count']) ?>
                    <span class="text-sm font-normal text-slate-500 dark:text-zink-300">
                        entries, <?= number_format($cacheStats['layers'][$layerCard['key']]['size_bytes'] / 1024, 1) ?> KB
                    </span>
                </p>
                <p class="text-sm text-slate-500 dark:text-zink-200 mt-2 mb-4"><?= e($layerCard['text']) ?></p>
                <form action="<?= buildLocalizedUrl('/admin/cache/clear-layer') ?>" method="POST" class="mt-auto">
                    <?= csrf_field() ?>
                    <input type="hidden" name="layer" value="<?= e($layerCard['key']) ?>">
                    <button type="submit" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/20 transition-colors"
                            data-confirm="Clear all <?= e(strtolower($layerCard['title'])) ?>?">
                        Clear <?= e(strtolower($layerCard['title'])) ?>
                    </button>
                </form>
            </div>
        </div>
        <?php } ?>

        <div class="card">
            <div class="card-body flex flex-col h-full">
                <h6 class="mb-2 text-15 flex items-center gap-2">
                    {% cache 'lucide:layer:code' ttl=31536000 %}<i class="size-5 text-custom-500" data-lucide="code"></i>{% endcache %}
                    Compiled templates
                </h6>
                <p class="text-2xl font-semibold text-slate-900 dark:text-zink-50">
                    <?= number_format((int) $cacheStats['compiled_views_count']) ?>
                    <span class="text-sm font-normal text-slate-500 dark:text-zink-300">
                        files, <?= number_format($cacheStats['compiled_views_size_bytes'] / 1024, 1) ?> KB
                    </span>
                </p>
                <p class="text-sm text-slate-500 dark:text-zink-200 mt-2 mb-4">
                    Your page templates turned into PHP. They remember whether caching was on when they were built, so rebuild them after switching caching on or off.
                </p>
                <form action="<?= buildLocalizedUrl('/admin/cache/rebuild-views') ?>" method="POST" class="mt-auto">
                    <?= csrf_field() ?>
                    <button type="submit" class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 text-sm font-medium text-red-600 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/20 transition-colors"
                            data-confirm="Rebuild all compiled templates?">
                        Rebuild templates
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php /* Cache Statistics Card */ ?>
    <div class="mb-5">
        {% cmp="cache-stats-card" stats="{$cacheStats}" isAdmin="true" %}
    </div>

    <?php /* Advanced Actions Grid */ ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-5">
        <?php /* Pattern-Based Deletion */ ?>
        <div class="card">
            <div class="card-body">
                <h6 class="mb-4 text-15 flex items-center gap-2">
                    <i class="size-5 text-orange-500 dark:text-orange-400" data-lucide="filter"></i>
                    Delete by Pattern
                </h6>
                
                <p class="text-sm text-slate-500 dark:text-zink-200 mb-4">
                    Remove cache files matching a specific pattern. Useful for targeted invalidation without clearing all cache.
                </p>
                
                <form action="<?= buildLocalizedUrl('/admin/cache/delete-pattern') ?>" method="POST">
                    <?= csrf_field() ?>
                    
                    <div class="mb-4">
                        <label for="pattern" class="inline-block mb-2 text-base font-medium">
                            Cache Pattern
                        </label>
                        <input type="text" 
                               name="pattern" 
                               id="pattern" 
                               placeholder="e.g., en:*, *blogs*, fr:GET:/products*"
                               class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 disabled:bg-slate-100 dark:disabled:bg-zink-600 disabled:border-slate-300 dark:disabled:border-zink-500 dark:disabled:text-zink-200 disabled:text-slate-500 dark:text-zink-100 dark:bg-zink-700 dark:focus:border-custom-800 placeholder:text-slate-400 dark:placeholder:text-zink-200"
                               required>
                    </div>

                    <div class="p-3 mb-4 rounded-md bg-slate-50 dark:bg-zink-600 border border-slate-200 dark:border-zink-500">
                        <p class="text-xs font-medium text-slate-700 dark:text-zink-200 mb-2 flex items-center gap-2">
                            <i class="size-3" data-lucide="lightbulb"></i>
                            Common Patterns:
                        </p>
                        <ul class="text-xs text-slate-600 dark:text-zink-300 space-y-1.5">
                            <li class="flex items-start gap-2">
                                <code class="px-2 py-0.5 rounded bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 font-mono">en:*</code>
                                <span class="text-slate-500 dark:text-zink-400">All English pages</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-2 py-0.5 rounded bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 font-mono">*blogs*</code>
                                <span class="text-slate-500 dark:text-zink-400">All blog-related pages</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-2 py-0.5 rounded bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 font-mono">*:GET:/products*</code>
                                <span class="text-slate-500 dark:text-zink-400">All product pages (any locale)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-2 py-0.5 rounded bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 font-mono">fr:GET:/blog/*/post/*</code>
                                <span class="text-slate-500 dark:text-zink-400">All French blog posts</span>
                            </li>
                        </ul>
                    </div>

                    <button type="submit" 
                            class="w-full text-white btn bg-orange-500 border-orange-500 hover:text-white hover:bg-orange-600 hover:border-orange-600 focus:text-white focus:bg-orange-600 focus:border-orange-600 focus:ring focus:ring-orange-100 active:text-white active:bg-orange-600 active:border-orange-600 active:ring active:ring-orange-100 dark:ring-orange-400/20"
                            data-confirm="Are you sure you want to delete cache files matching this pattern?">
                        <i class="size-4 mr-2" data-lucide="trash-2"></i>
                        Delete Matching Files
                    </button>
                </form>
            </div>
        </div>

        <?php /* Cache Information */ ?>
        <div class="card">
            <div class="card-body">
                <h6 class="mb-4 text-15 flex items-center gap-2">
                    <i class="size-5 text-sky-500 dark:text-sky-400" data-lucide="info"></i>
                    Cache System Information
                </h6>
                
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="font-medium text-slate-700 dark:text-zink-200">Cache Type</dt>
                        <dd class="mt-1 text-slate-500 dark:text-zink-300">File-based (TTL with automatic cleanup)</dd>
                    </div>
                    
                    <div>
                        <dt class="font-medium text-slate-700 dark:text-zink-200">Storage Path</dt>
                        <dd class="mt-1">
                            <code class="text-xs font-mono text-slate-600 dark:text-zink-300 bg-slate-100 dark:bg-zink-600 px-2 py-1 rounded border border-slate-200 dark:border-zink-500">
                                /storage/cache
                            </code>
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-700 dark:text-zink-200">Garbage Collection</dt>
                        <dd class="mt-1 text-slate-500 dark:text-zink-300">
                            Probabilistic (<?= e($cacheStats['gc_probability']) ?>)
                        </dd>
                    </div>

                    <div>
                        <dt class="font-medium text-slate-700 dark:text-zink-200">File Limit</dt>
                        <dd class="mt-1 text-slate-500 dark:text-zink-300">
                            <?= number_format($cacheStats['max_files']) ?> files (LRU eviction when exceeded)
                        </dd>
                    </div>

                    <div class="pt-4 border-t border-slate-200 dark:border-zink-500">
                        <p class="text-xs text-slate-600 dark:text-zink-400 leading-relaxed">
                            <strong class="text-slate-700 dark:text-zink-200">Automatic Cleanup:</strong> Expired files are deleted automatically when accessed 
                            or during probabilistic garbage collection (runs on ~<?= e($cacheStats['gc_probability']) ?> of write operations).
                        </p>
                    </div>

                    <div class="pt-2">
                        <p class="text-xs text-slate-600 dark:text-zink-400 leading-relaxed">
                            <strong class="text-slate-700 dark:text-zink-200">Manual Cleanup:</strong> Use "Prune Expired" to immediately delete all expired files, 
                            or "Clear All Cache" to remove everything (including fresh cache).
                        </p>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <?php /* Cache Management Tips */ ?>
    <div class="card bg-sky-50 dark:bg-sky-500/10 border-sky-200 dark:border-sky-500/30">
        <div class="card-body">
            <div class="flex items-start gap-3">
                <i class="size-6 text-sky-500 dark:text-sky-400 mt-0.5 flex-shrink-0" data-lucide="info"></i>
                <div>
                    <h6 class="text-sm font-semibold text-sky-900 dark:text-sky-200 mb-3">Cache Management Tips</h6>
                    <ul class="text-sm text-sky-800 dark:text-sky-300 space-y-2">
                        <li class="flex items-start gap-2">
                            <i class="size-4 mt-0.5 text-sky-600 dark:text-sky-400 flex-shrink-0" data-lucide="check"></i>
                            <span><strong class="font-semibold">Prune Expired:</strong> Safe operation, only removes files past their TTL</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="size-4 mt-0.5 text-sky-600 dark:text-sky-400 flex-shrink-0" data-lucide="check"></i>
                            <span><strong class="font-semibold">Clear All:</strong> Removes all cache including fresh files. Use when deploying updates</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="size-4 mt-0.5 text-sky-600 dark:text-sky-400 flex-shrink-0" data-lucide="check"></i>
                            <span><strong class="font-semibold">Pattern Delete:</strong> Target specific content (e.g., clear only French pages or blog posts)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="size-4 mt-0.5 text-sky-600 dark:text-sky-400 flex-shrink-0" data-lucide="check"></i>
                            <span><strong class="font-semibold">File Limit:</strong> System auto-evicts oldest files when limit is reached (LRU strategy)</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="size-4 mt-0.5 text-sky-600 dark:text-sky-400 flex-shrink-0" data-lucide="check"></i>
                            <span><strong class="font-semibold">Monitoring:</strong> Check "Files over limit". If it is consistently high, consider increasing max_files in config</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
{% endblock %}
