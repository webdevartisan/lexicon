{% extends "back.lex.php" %}
{% block title %}{{ blog.blog_name }} · Overview{% endblock %}
{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <!-- Blog header: identity + status + primary actions -->
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between mb-6">
        <div class="flex items-start gap-3 min-w-0">
            {% if blog.logo_path|notempty %}
            <div class="flex items-center justify-center w-12 h-12 rounded-md bg-slate-100 dark:bg-zink-700 shrink-0 overflow-hidden">
                <img src="{{ blog.logo_path }}" alt="{{ blog.blog_name }} logo" class="object-contain w-10 h-10">
            </div>
            {% else %}
            <div class="flex items-center justify-center w-12 h-12 rounded-md bg-custom-50 text-custom-500 dark:bg-custom-500/20 shrink-0">
                <i data-lucide="book-open" class="size-5"></i>
            </div>
            {% endif %}

            <div class="min-w-0">
                <h1 class="text-xl font-semibold text-slate-900 dark:text-zink-50 truncate">
                    {{ blog.blog_name }}
                </h1>
                <div class="flex flex-wrap items-center gap-2 mt-1.5">
                    <?php
                    $blogStatus = $blog['status'] ?? 'draft';
                    $blogBadge = [
                        'published' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
                        'draft' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600',
                        'archived' => 'bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900',
                    ][$blogStatus] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                    ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border <?= $blogBadge ?>">
                        <?= e(ucfirst($blogStatus)) ?>
                    </span>
                    <span class="text-xs text-slate-500 dark:text-zink-300">
                        /blog/{{ blog.blog_slug }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="/blog/{{ blog.blog_slug }}" target="_blank" rel="noopener"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                <i data-lucide="external-link" class="size-4"></i>
                <span>View live</span>
            </a>
            <?php if (in_array($blogRole ?? 'none', ['owner', 'editor'], true)) { ?>
            <a href="/dashboard/blog/{{ blog.id }}/edit"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                <i data-lucide="settings" class="size-4"></i>
                <span>Settings</span>
            </a>
            <?php $blogId = (int) $blog['id']; $newPostHref = "/dashboard/post/new?blog_id={$blogId}"; ?>
            {% cmp="btn" href="{$newPostHref}" variant="blue" icon="plus" label="New post" %}
            <?php } ?>
        </div>
    </div>

    <!-- Compact inline stats: a one-line summary for quick orientation. The big
         KPI grid belongs on the Dashboard (which is the activity workspace);
         duplicating it here just made the two pages look like the same page. -->
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 mb-6 px-4 py-3 rounded-md border border-slate-200 dark:border-zink-600 bg-white dark:bg-zink-700 text-sm">
        <a href="/dashboard/blog/{{ blog.id }}/posts?status=published" class="inline-flex items-center gap-1.5 text-slate-700 dark:text-zink-100 hover:text-custom-500 transition-colors">
            <i data-lucide="check-circle-2" class="size-3.5 text-green-500"></i>
            <span class="font-semibold tabular-nums">{{ stats.published }}</span>
            <span class="text-slate-500 dark:text-zink-300">published</span>
        </a>
        <a href="/dashboard/blog/{{ blog.id }}/posts?status=draft" class="inline-flex items-center gap-1.5 text-slate-700 dark:text-zink-100 hover:text-custom-500 transition-colors">
            <i data-lucide="file-text" class="size-3.5 text-slate-400"></i>
            <span class="font-semibold tabular-nums">{{ stats.draft }}</span>
            <span class="text-slate-500 dark:text-zink-300">drafts</span>
        </a>
        <?php if (!empty($settings['workflow_enabled'])) { ?>
        <a href="/dashboard/blog/{{ blog.id }}/posts?status=pending" class="inline-flex items-center gap-1.5 text-slate-700 dark:text-zink-100 hover:text-custom-500 transition-colors">
            <i data-lucide="clock" class="size-3.5 text-amber-500"></i>
            <span class="font-semibold tabular-nums">{{ stats.pending }}</span>
            <span class="text-slate-500 dark:text-zink-300">in review</span>
        </a>
        <?php } ?>
        <a href="/dashboard/blog/{{ blog.id }}/posts?status=archived" class="inline-flex items-center gap-1.5 text-slate-700 dark:text-zink-100 hover:text-custom-500 transition-colors">
            <i data-lucide="archive" class="size-3.5 text-slate-400"></i>
            <span class="font-semibold tabular-nums">{{ stats.archived }}</span>
            <span class="text-slate-500 dark:text-zink-300">archived</span>
        </a>
        <a href="/dashboard/blog/{{ blog.id }}/comments?status=pending" class="inline-flex items-center gap-1.5 text-slate-700 dark:text-zink-100 hover:text-custom-500 transition-colors">
            <i data-lucide="message-square" class="size-3.5 text-sky-500"></i>
            <span class="font-semibold tabular-nums">{{ stats.comments }}</span>
            <span class="text-slate-500 dark:text-zink-300">comments</span>
            <?php if (!empty($stats['comments_pending'])) { ?>
            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium rounded-full bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-900/40 dark:border-amber-800">
                <?= (int) $stats['comments_pending'] ?> pending
            </span>
            <?php } ?>
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Description + meta -->
        <section class="lg:col-span-2 card">
            {% if blog.banner_path|notempty %}
            <div class="relative overflow-hidden aspect-[21/6] bg-slate-100 dark:bg-zink-800">
                <img src="{{ blog.banner_path }}" alt="{{ blog.blog_name }} banner" class="object-cover w-full h-full">
            </div>
            {% endif %}
            <div class="card-body space-y-3">
                <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50">About this blog</h2>
                {% if blog.description|notempty %}
                <p class="text-sm leading-6 text-slate-700 dark:text-zink-100">{{ blog.description }}</p>
                {% else %}
                <p class="text-sm italic text-slate-400 dark:text-zink-300">No description has been added yet.</p>
                {% endif %}

                <div class="flex flex-wrap gap-2 pt-3 border-t border-slate-100 dark:border-zink-600">
                    <?php if (!empty($settings['default_locale'])) { ?>
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] rounded-md bg-slate-50 border border-slate-200 text-slate-600 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200">
                        <i data-lucide="globe" class="size-3"></i>
                        <?= e(strtoupper((string) $settings['default_locale'])) ?>
                    </span>
                    <?php } ?>
                    <?php if (!empty($settings['timezone'])) { ?>
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] rounded-md bg-slate-50 border border-slate-200 text-slate-600 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200">
                        <i data-lucide="clock" class="size-3"></i>
                        <?= e((string) $settings['timezone']) ?>
                    </span>
                    <?php } ?>
                    <?php if (!empty($settings['theme'])) { ?>
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 text-[11px] rounded-md bg-slate-50 border border-slate-200 text-slate-600 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200">
                        <i data-lucide="palette" class="size-3"></i>
                        <?= e((string) $settings['theme']) ?>
                    </span>
                    <?php } ?>
                </div>

                <!-- Configuration snapshot: on/off facts unique to this page (Dashboard has activity, this has identity + config). -->
                <?php
                $configOn  = 'inline-flex items-center gap-1.5 px-2 py-1 text-[11px] rounded-md bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300';
                $configOff = 'inline-flex items-center gap-1.5 px-2 py-1 text-[11px] rounded-md bg-slate-50 text-slate-500 border border-slate-200 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-300';
                $workflowOn  = !empty($settings['workflow_enabled']);
                $commentsOn  = !empty($settings['comments_enabled']);
                $indexableOn = !empty($settings['indexable']);
                ?>
                <div class="pt-3 mt-3 border-t border-slate-100 dark:border-zink-600">
                    <p class="mb-2 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">Configuration</p>
                    <div class="flex flex-wrap gap-2">
                        <span class="<?= $workflowOn ? $configOn : $configOff ?>">
                            <i data-lucide="<?= $workflowOn ? 'check' : 'x' ?>" class="size-3"></i>
                            Editorial workflow
                        </span>
                        <span class="<?= $commentsOn ? $configOn : $configOff ?>">
                            <i data-lucide="<?= $commentsOn ? 'check' : 'x' ?>" class="size-3"></i>
                            Comments
                        </span>
                        <span class="<?= $indexableOn ? $configOn : $configOff ?>">
                            <i data-lucide="<?= $indexableOn ? 'check' : 'x' ?>" class="size-3"></i>
                            Search-engine indexable
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick actions — content varies by role -->
        <aside class="card">
            <div class="card-body">
                <?php $isOwner = ($blogRole ?? '') === 'owner'; ?>
                <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-3">Manage this blog</h2>
                <div class="flex flex-col gap-2">
                    <a href="/dashboard/blog/{{ blog.id }}/edit" class="flex items-center gap-3 p-2.5 rounded-md hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors text-sm text-slate-700 dark:text-zink-100">
                        <i data-lucide="sliders" class="size-4 text-slate-400"></i>
                        <span>Blog settings</span>
                    </a>
                    <a href="/dashboard/blog/{{ blog.id }}/theme" class="flex items-center gap-3 p-2.5 rounded-md hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors text-sm text-slate-700 dark:text-zink-100">
                        <i data-lucide="palette" class="size-4 text-slate-400"></i>
                        <span>Appearance / Theme</span>
                    </a>
                    <?php if ($isOwner) { ?>
                    <a href="/dashboard/blog/{{ blog.id }}/team" class="flex items-center gap-3 p-2.5 rounded-md hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors text-sm text-slate-700 dark:text-zink-100">
                        <i data-lucide="users" class="size-4 text-slate-400"></i>
                        <span>Collaborators</span>
                    </a>
                    <?php } ?>
                    <a href="/dashboard/blog/{{ blog.id }}/posts" class="flex items-center gap-3 p-2.5 rounded-md hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors text-sm text-slate-700 dark:text-zink-100">
                        <i data-lucide="files" class="size-4 text-slate-400"></i>
                        <span>All posts in this blog</span>
                    </a>
                    <a href="/dashboard/blog/{{ blog.id }}/review-queue" class="flex items-center gap-3 p-2.5 rounded-md hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors text-sm text-slate-700 dark:text-zink-100">
                        <i data-lucide="clipboard-check" class="size-4 text-amber-500"></i>
                        <span>Review queue</span>
                    </a>
                </div>
            </div>
        </aside>
    </div>

    <!-- Recent published posts in this blog -->
    <section class="card mt-6">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50">Recent posts in this blog</h2>
                <a href="/dashboard/blog/{{ blog.id }}/posts" class="text-xs font-medium text-custom-500 hover:text-custom-600">View all</a>
            </div>

            {% if recent|empty %}
            <div class="text-center py-10">
                <i data-lucide="feather" class="size-10 text-slate-400 mx-auto mb-2"></i>
                <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">No posts published yet in this blog.</p>
                <?php $firstPostHref = '/dashboard/post/new?blog_id='.(int) $blog['id']; ?>
                {% cmp="btn" href="{$firstPostHref}" variant="blue" icon="plus" label="Write the first post" %}
            </div>
            {% else %}
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                {% foreach ($recent as $post): %}
                    {% cmp="post-card" post="{$post}" blogSlug="{$blog['blog_slug']}" %}
                {% endforeach %}
            </div>
            {% endif %}
        </div>
    </section>

</div>
{% endblock %}
{% block scripts %}
<script src='/cp-assets/js/tooltip.js'></script>
{% endblock %}
