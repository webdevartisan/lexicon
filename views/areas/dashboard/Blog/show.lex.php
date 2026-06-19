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
            <a href="/dashboard/blog/{{ blog.id }}/edit"
                class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                <i data-lucide="settings" class="size-4"></i>
                <span>Settings</span>
            </a>
            {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="New post" %}
        </div>
    </div>

    <!-- Stats row: at-a-glance numbers for THIS blog. -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <a href="/dashboard/post?status=published&blog_id={{ blog.id }}" class="card hover:border-custom-500 transition-colors">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide">Published</span>
                    <i data-lucide="check-circle-2" class="size-4 text-green-500"></i>
                </div>
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">{{ stats.published }}</div>
            </div>
        </a>
        <a href="/dashboard/post?status=draft&blog_id={{ blog.id }}" class="card hover:border-custom-500 transition-colors">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide">Drafts</span>
                    <i data-lucide="file-text" class="size-4 text-slate-400"></i>
                </div>
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">{{ stats.draft }}</div>
            </div>
        </a>
        <a href="/dashboard/post?status=pending&blog_id={{ blog.id }}" class="card hover:border-custom-500 transition-colors">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide">Pending</span>
                    <i data-lucide="clock" class="size-4 text-amber-500"></i>
                </div>
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">{{ stats.pending }}</div>
            </div>
        </a>
        <a href="/dashboard/post?status=archived&blog_id={{ blog.id }}" class="card hover:border-custom-500 transition-colors">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide">Archived</span>
                    <i data-lucide="archive" class="size-4 text-slate-400"></i>
                </div>
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">{{ stats.archived }}</div>
            </div>
        </a>
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium text-slate-500 dark:text-zink-300 uppercase tracking-wide">Comments</span>
                    <i data-lucide="message-square" class="size-4 text-sky-500"></i>
                </div>
                <div class="text-2xl font-semibold text-slate-900 dark:text-zink-50">{{ stats.comments }}</div>
            </div>
        </div>
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
            </div>
        </section>

        <!-- Quick actions for this blog -->
        <aside class="card">
            <div class="card-body">
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
                    <a href="/dashboard/blog/{{ blog.id }}/users" class="flex items-center gap-3 p-2.5 rounded-md hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors text-sm text-slate-700 dark:text-zink-100">
                        <i data-lucide="users" class="size-4 text-slate-400"></i>
                        <span>Collaborators</span>
                    </a>
                    <a href="/dashboard/post?blog_id={{ blog.id }}" class="flex items-center gap-3 p-2.5 rounded-md hover:bg-slate-50 dark:hover:bg-zink-600 transition-colors text-sm text-slate-700 dark:text-zink-100">
                        <i data-lucide="files" class="size-4 text-slate-400"></i>
                        <span>All posts in this blog</span>
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
                <a href="/dashboard/post?blog_id={{ blog.id }}" class="text-xs font-medium text-custom-500 hover:text-custom-600">View all</a>
            </div>

            {% if recent|empty %}
            <div class="text-center py-10">
                <i data-lucide="feather" class="size-10 text-slate-400 mx-auto mb-2"></i>
                <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">No posts published yet in this blog.</p>
                {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="Write the first post" %}
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
