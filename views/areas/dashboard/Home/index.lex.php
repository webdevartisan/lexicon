{% extends "back.lex.php" %}
{% block title %}{{ t('dashboard.pageTitle') }}{% endblock %}
{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    {% if hasNoBlogs|notempty %}
    <div class="flex items-center justify-center min-h-[60vh]">
        <div class="text-center max-w-md px-6">
            <div class="mb-6">
                {% cache 'lucide:book-open' ttl=3600 %}<i class="inline-flex items-center justify-center size-16 text-custom-500 bg-custom-100 dark:bg-custom-500/20 rounded-full"
                    data-lucide="book-open"></i>{% endcache %}
            </div>

            <h2 class="text-2xl font-semibold text-slate-900 dark:text-zink-50 mb-3">
                {{ t('dashboard.emptyState.title') }}
            </h2>

            <p class="text-slate-600 dark:text-zink-300 mb-6">
                {{ t('dashboard.emptyState.description') }}
            </p>

            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                {% cmp="btn" href="/dashboard/blog/new" variant="blue" icon="plus" label="Create your first blog" %}

                <a href="/help/getting-started"
                    class="inline-flex items-center justify-center px-6 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    {% cache 'lucide:help-circle' ttl=3600 %}<i class="size-4 mr-2" data-lucide="help-circle"></i>{% endcache %}
                    {{ t('dashboard.emptyState.actions.gettingStarted') }}
                </a>
            </div>

            <div class="mt-8 p-4 bg-slate-50 dark:bg-zink-700 rounded-lg text-left">
                <h3 class="font-semibold text-slate-900 dark:text-zink-50 mb-3 flex items-center gap-2">
                    {% cache 'lucide:lightbulb' ttl=3600 %}<i class="size-4" data-lucide="lightbulb"></i>{% endcache %}
                    {{ t('dashboard.emptyState.quickTips.title') }}
                </h3>
                <ul class="space-y-2 text-sm text-slate-600 dark:text-zink-300">
                    <li class="flex items-start gap-2">
                        {% cache 'lucide:check' ttl=3600 %}<i class="size-4 mt-0.5 text-custom-500" data-lucide="check"></i>{% endcache %}
                        <span>{{ t('dashboard.emptyState.quickTips.tip1') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        {% cache 'lucide:check' ttl=3600 %}<i class="size-4 mt-0.5 text-custom-500" data-lucide="check"></i>{% endcache %}
                        <span>{{ t('dashboard.emptyState.quickTips.tip2') }}</span>
                    </li>
                    <li class="flex items-start gap-2">
                        {% cache 'lucide:check' ttl=3600 %}<i class="size-4 mt-0.5 text-custom-500" data-lucide="check"></i>{% endcache %}
                        <span>{{ t('dashboard.emptyState.quickTips.tip3') }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    {% else %}

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold text-slate-900 dark:text-zink-50">
                <?= e($t('common.welcome')) ?>, <?= e(auth()->user()['username'] ?? '') ?>
            </h1>
            <p class="text-sm text-slate-500 dark:text-zink-300 mt-1">
                Here's what's happening in <span class="font-medium text-slate-700 dark:text-zink-100"><?= e($blogIds[$selectedBlogId] ?? '') ?></span>.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/blog/{{ blogSlug }}" target="_blank" rel="noopener"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                {% cache 'lucide:external-link' ttl=3600 %}<i data-lucide="external-link" class="size-4"></i>{% endcache %}
                <span>{{ t('dashboard.actions.viewLive') }}</span>
            </a>
            <?php if (!in_array($blogRole ?? 'none', ['reviewer'], true)) { ?>
            {% set newPostLabel = t('dashboard.actions.newPost') %}
            {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
            <?php } ?>
        </div>
    </div>

    <!-- KPI cards: the numbers a creator wants on landing. The dashboard
         is now strictly the active OWNED-blog workspace, so there's no
         reviewer-context branch here — that case is impossible by construction
         since the default blog must be one the user owns.
         The Pending tile only renders when this blog uses the editorial pipeline. -->
    <?php $kpiCols = !empty($workflowEnabled) ? 'lg:grid-cols-4' : 'lg:grid-cols-3'; ?>
    <div class="grid grid-cols-2 <?= $kpiCols ?> gap-4 mb-6">
        <a href="/dashboard/post?status=published" class="card hover:border-custom-500 transition-colors group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide">{{ t('dashboard.stats.published') }}</span>
                    <span class="inline-flex items-center justify-center size-8 rounded-md bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                        {% cache 'lucide:check-circle-2' ttl=3600 %}<i data-lucide="check-circle-2" class="size-4"></i>{% endcache %}
                    </span>
                </div>
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">{{ stats.published }}</div>
            </div>
        </a>
        <a href="/dashboard/post?status=draft" class="card hover:border-custom-500 transition-colors group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide">{{ t('dashboard.stats.drafts') }}</span>
                    <span class="inline-flex items-center justify-center size-8 rounded-md bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200">
                        {% cache 'lucide:file-text' ttl=3600 %}<i data-lucide="file-text" class="size-4"></i>{% endcache %}
                    </span>
                </div>
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">{{ stats.draft }}</div>
            </div>
        </a>
        <?php if (!empty($workflowEnabled)) { ?>
        <a href="/dashboard/post?status=pending" class="card hover:border-custom-500 transition-colors group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide">{{ t('dashboard.stats.pending') }}</span>
                    <span class="inline-flex items-center justify-center size-8 rounded-md bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                        {% cache 'lucide:clock' ttl=3600 %}<i data-lucide="clock" class="size-4"></i>{% endcache %}
                    </span>
                </div>
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">{{ stats.pending }}</div>
            </div>
        </a>
        <?php } ?>
        <a href="/dashboard/blog/{{ selectedBlogId }}/comments?status=pending" class="card hover:border-custom-500 transition-colors group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide">{{ t('dashboard.stats.comments') }}</span>
                    <span class="inline-flex items-center justify-center size-8 rounded-md bg-sky-50 text-sky-600 dark:bg-sky-900/30 dark:text-sky-400">
                        {% cache 'lucide:message-square' ttl=3600 %}<i data-lucide="message-square" class="size-4"></i>{% endcache %}
                    </span>
                </div>
                <div class="flex items-end gap-2">
                    <span class="text-2xl font-semibold text-slate-900 dark:text-zink-50">{{ stats.comments }}</span>
                    <?php if (!empty($stats['comments_pending'])) { ?>
                    <span class="inline-flex items-center gap-1 mb-1 px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-900/40 dark:border-amber-800">
                        <?= (int) $stats['comments_pending'] ?> pending
                    </span>
                    <?php } ?>
                </div>
            </div>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Needs attention: drafts + pending. This is the "you have work waiting" panel. -->
        <section class="lg:col-span-1 card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        {% cache 'lucide:alert-circle' ttl=3600 %}<i data-lucide="alert-circle" class="size-4 text-amber-500"></i>{% endcache %}
                        {{ t('dashboard.sections.needsAttention') }}
                    </h2>
                    {% if needsAttention|notempty %}
                    <a href="/dashboard/post?status=draft" class="text-xs font-medium text-custom-500 hover:text-custom-600">View all</a>
                    {% endif %}
                </div>

                {% if needsAttention|empty %}
                <div class="text-center py-8">
                    {% cache 'lucide:check-circle' ttl=3600 %}<i data-lucide="check-circle" class="size-10 text-green-500 mx-auto mb-2"></i>{% endcache %}
                    <p class="text-sm text-slate-500 dark:text-zink-300">{{ t('dashboard.empty.noDrafts') }}</p>
                </div>
                {% else %}
                <ul class="divide-y divide-slate-100 dark:divide-zink-600">
                    {% foreach ($needsAttention as $post): %}
                    <li class="py-3 first:pt-0 last:pb-0">
                        <a href="/dashboard/post/<?= e((string) $post['id']) ?>/edit" 
                            class="flex items-start gap-3 p-3 rounded-lg border border-slate-100 hover:border-custom-500 hover:bg-slate-50 dark:border-zink-600 dark:hover:bg-zink-600 transition-colors">
                            <span class="inline-flex items-center justify-center size-8 rounded-md bg-slate-100 dark:bg-zink-600 text-slate-500 dark:text-zink-200 shrink-0 mt-0.5">
                                <?php if ($post['status'] === 'pending') { ?>
                                    {% cache 'lucide:clock' ttl=3600 %}<i data-lucide="clock" class="size-4"></i>{% endcache %}
                                <?php } else { ?>
                                    {% cache 'lucide:pencil' ttl=3600 %}<i data-lucide="pencil" class="size-4"></i>{% endcache %}
                                <?php } ?>
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-slate-900 dark:text-zink-50 truncate transition-colors">
                                    <?= e($post['title']) ?>
                                </p>
                                <p class="text-xs text-slate-500 dark:text-zink-300 mt-0.5">
                                    <?= e(ucfirst((string) $post['status'])) ?> · Updated <?= e(date('M j', strtotime((string) ($post['updated_at'] ?? $post['created_at'] ?? 'now')))) ?>
                                </p>
                            </div>
                            {% cache 'lucide:chevron-right' ttl=3600 %}<i data-lucide="chevron-right" class="size-4 text-slate-400 shrink-0 mt-3 transition-colors"></i>{% endcache %}
                        </a>
                    </li>
                    {% endforeach %}
                </ul>
                {% endif %}
            </div>
        </section>

        <!-- Recently published: the "what just went live" strip. -->
        <section class="lg:col-span-2 card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                        {% cache 'lucide:rss' ttl=3600 %}<i data-lucide="rss" class="size-4 text-green-500"></i>{% endcache %}
                        {{ t('dashboard.sections.recent') }}
                    </h2>
                    {% if recent|notempty %}
                    <a href="/dashboard/post?status=published" class="text-xs font-medium text-custom-500 hover:text-custom-600">{{ t('dashboard.actions.managePosts') }}</a>
                    {% endif %}
                </div>

                {% if recent|empty %}
                <div class="text-center py-12">
                    {% cache 'lucide:feather' ttl=3600 %}<i data-lucide="feather" class="size-10 text-slate-400 mx-auto mb-2"></i>{% endcache %}
                    <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">{{ t('dashboard.empty.noRecent') }}</p>
                    {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="Write your first post" %}
                </div>
                {% else %}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {% foreach ($recent as $post): %}
                    <a href="/dashboard/post/<?= e((string) $post['id']) ?>/edit"
                       class="flex flex-col gap-1 p-3 rounded-lg border border-slate-100 hover:border-custom-500 hover:bg-slate-50 dark:border-zink-600 dark:hover:bg-zink-600 transition-colors">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-medium text-slate-900 dark:text-zink-50 line-clamp-2 transition-colors">
                                <?= e($post['title']) ?>
                            </p>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zink-300">
                            <?= e(date('M j, Y', strtotime((string) ($post['published_at'] ?? $post['created_at'] ?? 'now')))) ?>
                        </p>
                    </a>
                    {% endforeach %}
                </div>
                {% endif %}
            </div>
        </section>
    </div>

    {% if blogsSummary|notempty %}
    <!-- Per-blog summary: only renders if the user owns 2+ blogs. -->
    <section class="card mt-6">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 flex items-center gap-2">
                    {% cache 'lucide:book-open' ttl=3600 %}<i data-lucide="book-open" class="size-4 text-custom-500"></i>{% endcache %}
                    {{ t('dashboard.sections.yourBlogs') }}
                </h2>
                <a href="/dashboard/blog" class="text-xs font-medium text-custom-500 hover:text-custom-600">View all blogs</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                {% foreach ($blogsSummary as $b): %}
                <form method="POST" action="/dashboard/setDefaultBlog" class="m-0">
                    {{ csrf_field() }}
                    <input type="hidden" name="blog" value="<?= e((string) $b['id']) ?>">
                    <button type="submit" class="w-full text-left p-3 rounded-lg border border-slate-100 hover:border-custom-500 hover:bg-slate-50 dark:border-zink-600 dark:hover:bg-zink-700 transition-colors <?= ($b['id'] === $selectedBlogId) ? 'border-custom-500 bg-custom-50/40 dark:bg-custom-500/10' : '' ?>">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <span class="text-sm font-medium text-slate-900 dark:text-zink-50 truncate"><?= e($b['name']) ?></span>
                            <?php if ($b['id'] === $selectedBlogId) { ?>
                            <span class="text-[10px] font-medium text-custom-500 uppercase tracking-wide">Active</span>
                            <?php } ?>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zink-300">
                            <?= (int) $b['post_count'] ?> posts · <?= e(ucfirst((string) $b['status'])) ?>
                        </p>
                    </button>
                </form>
                {% endforeach %}
            </div>
        </div>
    </section>
    {% endif %}

</div>
{% endif %}
{% endblock %}
{% block scripts %}
<script src='/cp-assets/js/tooltip.js'></script>
{% endblock %}
