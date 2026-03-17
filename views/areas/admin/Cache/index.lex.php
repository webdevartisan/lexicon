{% extends "back.lex.php" %}

{% block title %}Cache Management - Control Panel{% endblock %}
{% block subtitle %}Monitor and manage application cache system{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

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
                
                <form action="/admin/cache/delete-pattern" method="POST">
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
                                <span class="text-slate-500 dark:text-zink-400">- All English pages</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-2 py-0.5 rounded bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 font-mono">*blogs*</code>
                                <span class="text-slate-500 dark:text-zink-400">- All blog-related pages</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-2 py-0.5 rounded bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 font-mono">*:GET:/products*</code>
                                <span class="text-slate-500 dark:text-zink-400">- All product pages (any locale)</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-2 py-0.5 rounded bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 font-mono">fr:GET:/blog/*/post/*</code>
                                <span class="text-slate-500 dark:text-zink-400">- All French blog posts</span>
                            </li>
                        </ul>
                    </div>

                    <button type="submit" 
                            class="w-full text-white btn bg-orange-500 border-orange-500 hover:text-white hover:bg-orange-600 hover:border-orange-600 focus:text-white focus:bg-orange-600 focus:border-orange-600 focus:ring focus:ring-orange-100 active:text-white active:bg-orange-600 active:border-orange-600 active:ring active:ring-orange-100 dark:ring-orange-400/20"
                            onclick="return confirm('Are you sure you want to delete cache files matching this pattern?')">
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
                            <span><strong class="font-semibold">Prune Expired:</strong> Safe operation - only removes files past their TTL</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <i class="size-4 mt-0.5 text-sky-600 dark:text-sky-400 flex-shrink-0" data-lucide="check"></i>
                            <span><strong class="font-semibold">Clear All:</strong> Removes all cache including fresh files - use when deploying updates</span>
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
                            <span><strong class="font-semibold">Monitoring:</strong> Check "Files over limit" - if consistently high, consider increasing max_files in config</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
{% endblock %}
