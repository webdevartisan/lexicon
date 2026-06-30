{% extends "back.lex.php" %}
{% block title %}All Blogs{% endblock %}
{% block subtitle %}Create, manage, and switch between blogs.{% endblock %}
{% block body %}
<?php
$statusBadge = [
    'published' => ['bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800', 'Published'],
    'draft' => ['bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600', 'Draft'],
    'archived' => ['bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100', 'Archived'],
];
$sortOptions = [
    'updated' => 'Last updated',
    'created' => 'Date created',
    'posts' => 'Most posts',
    'name' => 'Name (A–Z)',
];
$statusOptions = [
    'published' => 'Published',
    'draft' => 'Draft',
    'archived' => 'Archived'
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <!-- Toolbar: filters + create -->
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between mb-5">
        <form method="GET" action="/dashboard/blog" class="grid grid-cols-1 sm:grid-cols-3 gap-3 grow max-w-2xl">
            {% cmp="input" type="text" icon="search" name="q" value="{$q}" placeholder="Search blogs..." %}
            {% cmp="select" name="status" options="{$statusOptions}" selectedKey="{$status}" onchange="this.form.submit()" %}
            {% cmp="select" name="sort" options="{$sortOptions}" selectedKey="{$sort}" onchange="this.form.submit()" %}
        </form>

        <div class="shrink-0">
            {% cmp="btn" href="/dashboard/blog/new" variant="blue" icon="plus" label="Create New Blog" %}
        </div>
    </div>

    {% if blogs|empty %}
    <div class="card">
        <div class="card-body text-center py-16">
            <i data-lucide="book-open" class="size-12 text-slate-400 mx-auto mb-3"></i>
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">
                <?php if ($q !== '' || $status !== '') { ?>
                    No blogs match your filters
                <?php } else { ?>
                    No blogs yet
                <?php } ?>
            </h3>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-5">
                <?php if ($q !== '' || $status !== '') { ?>
                    Try a different search or clear the status filter.
                <?php } else { ?>
                    Create your first blog to start publishing.
                <?php } ?>
            </p>
            <div class="flex items-center justify-center gap-2">
                {% cmp="btn" href="/dashboard/blog/new" variant="blue" icon="plus" label="Create New Blog" %}
                <?php if ($q !== '' || $status !== '') { ?>
                <a href="/dashboard/blog" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                    <i data-lucide="x" class="size-4"></i> Clear filters
                </a>
                <?php } ?>
            </div>
        </div>
    </div>
    {% else %}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        {% foreach ($blogs as $blog): %}
        <?php
            $bid = (int) $blog['id'];
$isActive = ($bid === (int) $selectedBlogId);
$bStatus = $blog['status'] ?? 'draft';
[$badgeClass, $badgeLabel] = $statusBadge[$bStatus] ?? $statusBadge['draft'];
$isOwner = ($blog['user_role'] ?? 'owner') === 'owner';
$collaboratorRole = $isOwner ? null : ($blog['user_role'] ?? null);

$blogCardHref = "/dashboard/blog/<?= $bid ?>/show";


?>
        <div class="card flex flex-col h-full <?= $isActive ? 'ring-1 ring-custom-500 border-custom-500' : '' ?>">
            <!-- Banner / placeholder -->
            <a href="<?= $blogCardHref ?>" class="relative block overflow-hidden rounded-t-md aspect-[21/8] bg-slate-100 dark:bg-zink-600">
                <?php if (!empty($blog['banner_path'])) { ?>
                <img src="<?= e($blog['banner_path']) ?>" alt="<?= e($blog['blog_name']) ?> banner" class="object-cover w-full h-full">
                <?php } else { ?>
                <span class="flex items-center justify-center w-full h-full text-slate-300 dark:text-zink-500">
                    <i data-lucide="image" class="size-8"></i>
                </span>
                <?php } ?>
                <?php if ($isActive) { ?>
                <span class="absolute top-2 left-2 inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-custom-500 text-white shadow">
                    <i data-lucide="check" class="size-3"></i> Active
                </span>
                <?php } ?>
                <span class="absolute top-2 right-2 inline-flex items-center gap-1.5">
                    <?php if ($collaboratorRole !== null) { ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-900/40 dark:text-violet-300 dark:border-violet-800">
                        <?= e(ucfirst($collaboratorRole)) ?>
                    </span>
                    <?php } ?>
                    <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border <?= $badgeClass ?>">
                        <?= e($badgeLabel) ?>
                    </span>
                </span>
            </a>

            <div class="card-body flex flex-col flex-1">
                <h2 class="text-15 font-semibold mb-1">
                    <a href="<?= $blogCardHref ?>" class="hover:text-custom-500 transition-colors">
                        <?= e($blog['blog_name']) ?>
                    </a>
                </h2>
                <p class="text-xs text-slate-500 dark:text-zink-300 mb-2 truncate">
                    /blog/<?= e($blog['blog_slug'] ?? '') ?>
                </p>

                <?php if (!empty($blog['description'])) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-200 line-clamp-2 flex-1">
                    <?= e(truncate(strip_tags((string) $blog['description']), 140)) ?>
                </p>
                <?php } else { ?>
                <p class="text-sm italic text-slate-400 dark:text-zink-300 flex-1">No description yet.</p>
                <?php } ?>

                <!-- Meta -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-3 text-[11px] text-slate-500 dark:text-zink-300">
                    <span class="inline-flex items-center gap-1"><i data-lucide="files" class="size-3"></i> <?= (int) ($blog['post_count'] ?? 0) ?> posts</span>
                    <span class="inline-flex items-center gap-1"><i data-lucide="users" class="size-3"></i> <?= (int) ($blog['author_count'] ?? 0) ?> collaborators</span>
                    <?php if (!empty($blog['updated_at'])) { ?>
                    <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="size-3"></i> <?= e(date('M j, Y', strtotime((string) $blog['updated_at']))) ?></span>
                    <?php } ?>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-zink-600">
                    <div class="flex items-center gap-1">
                        <a href="<?= $blogCardHref ?>" title="Overview"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-custom-500 hover:text-custom-600 transition-colors">
                            <i data-lucide="layout-grid" class="size-4"></i> Open
                        </a>
                    </div>
                    <div class="flex items-center gap-0.5">
                        <?php if (!$isActive) { ?>
                        <form method="POST" action="/dashboard/setDefaultBlog" class="m-0">
                            {{ csrf_field() }}
                            <input type="hidden" name="blog" value="<?= $bid ?>">
                            <button type="submit" title="Set as active blog"
                                class="p-2 text-slate-500 hover:text-custom-500 rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
                                <i data-lucide="check-circle-2" class="size-4"></i>
                            </button>
                        </form>
                        <?php } ?>
                        <?php if ($isOwner) { ?>
                        <a href="/dashboard/blog/<?= $bid ?>/edit" title="Blog settings"
                            class="p-2 text-slate-500 hover:text-custom-500 rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors">
                            <i data-lucide="sliders" class="size-4"></i>
                        </a>
                        <a href="/dashboard/blog/<?= $bid ?>/team" title="Collaborators"
                            class="p-2 text-slate-500 hover:text-custom-500 rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors">
                            <i data-lucide="users" class="size-4"></i>
                        </a>
                        <?php } ?>
                        <a href="/blog/<?= e($blog['blog_slug'] ?? '') ?>" target="_blank" rel="noopener" title="View live"
                            class="p-2 text-slate-500 hover:text-custom-500 rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors">
                            <i data-lucide="external-link" class="size-4"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        {% endforeach %}
    </div>
    {% endif %}

</div>
{% endblock %}
{% block scripts %}
<script src='/cp-assets/js/tooltip.js'></script>
{% endblock %}
