{% extends "back.lex.php" %}
{% block title %}{{ t('dashboard.blogs.title') }}{% endblock %}
{% block subtitle %}{{ t('dashboard.blogs.subtitle') }}{% endblock %}
{% block body %}
<?php
$sortOptions = [
    'updated' => $t('dashboard.blogs.sort.updated'),
    'created' => $t('dashboard.blogs.sort.created'),
    'posts' => $t('dashboard.blogs.sort.posts'),
    'name' => $t('dashboard.blogs.sort.name'),
];
$statusOptions = [
    '' => $t('dashboard.blogs.anyStatus'),
    'published' => $t('dashboard.blogStatus.published'),
    'draft' => $t('dashboard.blogStatus.draft'),
    'archived' => $t('dashboard.blogStatus.archived'),
];
$searchPlaceholder = $t('dashboard.blogs.search');
$createLabel = $t('dashboard.blogs.create');
$iconButton = 'p-2 text-slate-500 hover:text-custom-500 rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between mb-5">
        <form method="GET" action="<?= e(lurl('/dashboard/blog')) ?>" class="grid grid-cols-1 sm:grid-cols-3 gap-3 grow max-w-2xl">
            {% cmp="input" type="text" icon="search" name="q" value="{$q}" placeholder="{$searchPlaceholder}" %}
            {% cmp="select" name="status" options="{$statusOptions}" selectedKey="{$status}" onchange="this.form.submit()" %}
            {% cmp="select" name="sort" options="{$sortOptions}" selectedKey="{$sort}" onchange="this.form.submit()" %}
        </form>

        <?php if (!empty($can_create_blog)) { ?>
        <div class="shrink-0">
            {% cmp="btn" href="/dashboard/blog/new" variant="blue" icon="plus" label="{$createLabel}" %}
        </div>
        <?php } ?>
    </div>

    <?php if ($groups['owned'] === [] && $groups['shared'] === []) { ?>
    <div class="card">
        <div class="card-body text-center py-16">
            {% cache 'lucide:blogs:search-x' ttl=31536000 %}<i data-lucide="search-x" class="size-12 text-slate-400 mx-auto mb-3"></i>{% endcache %}
            <h2 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">{{ t('dashboard.blogs.empty.filteredTitle') }}</h2>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-5">{{ t('dashboard.blogs.empty.filteredText') }}</p>
            <a href="<?= e(lurl('/dashboard/blog')) ?>" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
                {% cache 'lucide:blogs:x' ttl=31536000 %}<i data-lucide="x" class="size-4" aria-hidden="true"></i>{% endcache %}
                {{ t('dashboard.blogs.clearFilters') }}
            </a>
        </div>
    </div>
    <?php } else { ?>

    <?php foreach (['owned', 'shared'] as $group) {
        $cards = $groups[$group];
        if ($group === 'shared' && !$hasShared) {
            continue;
        } ?>
    <section class="mb-8" aria-labelledby="blogs-<?= $group ?>">
        <h2 id="blogs-<?= $group ?>" class="flex items-center gap-2 mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300">
            <?= e($t('dashboard.blogs.groups.'.$group)) ?>
            <span class="text-xs font-medium normal-case tracking-normal text-slate-400 dark:text-zink-400">(<?= count($cards) ?>)</span>
        </h2>

        <?php if ($cards === []) { ?>
        <div class="card">
            <div class="card-body text-center py-10">
                <?php if ($group === 'owned' && !$hasOwned) { ?>
                {% cache 'lucide:blogs:book-open' ttl=31536000 %}<i data-lucide="book-open" class="size-10 text-slate-400 mx-auto mb-2"></i>{% endcache %}
                <p class="text-sm text-slate-500 dark:text-zink-300 mb-4">{{ t('dashboard.blogs.empty.noOwned') }}</p>
                <?php if (!empty($can_create_blog)) { ?>
                {% cmp="btn" href="/dashboard/blog/new" variant="blue" icon="plus" label="{$createLabel}" %}
                <?php } ?>
                <?php } else { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300"><?= e($t('dashboard.blogs.empty.groupFiltered')) ?></p>
                <?php } ?>
            </div>
        </div>
        <?php } else { ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <?php foreach ($cards as $blog) {
                $bid = (int) $blog['id'];
                $blogName = (string) $blog['blog_name'];
                $bStatus = (string) $blog['status'];
                $statusLabel = $t('dashboard.blogStatus.'.$bStatus);
                $live = lurl('/blog/'.$blog['blog_slug']);
                ?>
            <article class="card flex flex-col h-full <?= $blog['is_active'] ? 'ring-1 ring-custom-500 border-custom-500' : '' ?>">
                <div class="relative overflow-hidden rounded-t-md aspect-[21/8] bg-slate-100 dark:bg-zink-600">
                    <?php if ($blog['banner_path'] !== '') { ?>
                    <img src="<?= e($blog['banner_path']) ?>" alt="" loading="lazy" class="object-cover w-full h-full">
                    <?php } else { ?>
                    <span class="flex items-center justify-center w-full h-full text-slate-300 dark:text-zink-500">
                        {% cache 'lucide:blogs:image' ttl=31536000 %}<i data-lucide="image" class="size-8" aria-hidden="true"></i>{% endcache %}
                    </span>
                    <?php } ?>
                    <?php if ($blog['is_active']) { ?>
                    <span class="absolute top-2 ltr:left-2 rtl:right-2 inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-custom-500 text-white shadow">
                        {% cache 'lucide:blogs:check' ttl=31536000 %}<i data-lucide="check" class="size-3" aria-hidden="true"></i>{% endcache %}
                        <?= e($t('dashboard.blogs.active')) ?>
                    </span>
                    <?php } ?>
                    <span class="absolute top-2 ltr:right-2 rtl:left-2 inline-flex items-center gap-1.5">
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-900/40 dark:text-violet-300 dark:border-violet-800"><?= e($blog['role_label']) ?></span>
                        {% cmp="status-badge" status="{$bStatus}" label="{$statusLabel}" %}
                    </span>
                </div>

                <div class="card-body flex flex-col flex-1">
                    <h3 class="text-15 font-semibold mb-1 truncate" dir="auto"><?= e($blogName) ?></h3>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mb-2 truncate" dir="ltr">/blog/<?= e($blog['blog_slug']) ?></p>
                    <?php if (!$blog['is_owner']) { ?>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mb-2 truncate"><?= e($t('dashboard.blogs.ownedBy', ['name' => '@'.$blog['owner_name']])) ?></p>
                    <?php } ?>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-auto text-[11px] text-slate-500 dark:text-zink-300">
                        <span><?= e($t('dashboard.blogs.postCount', ['count' => (int) $blog['post_count']])) ?></span>
                        <span><?= e($t('dashboard.blogs.collaboratorCount', ['count' => (int) $blog['author_count']])) ?></span>
                    </div>

                    <div class="flex items-center justify-between gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-zink-600">
                        <form method="POST" action="/dashboard/active-blog" class="m-0">
                            {{ csrf_field() }}
                            <input type="hidden" name="blog" value="<?= $bid ?>">
                            <input type="hidden" name="return_to" value="/dashboard">
                            <button type="submit" class="inline-flex items-center gap-1.5 text-sm font-medium text-custom-500 hover:text-custom-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 rounded">
                                {% cache 'lucide:blogs:arrow-right' ttl=31536000 %}<i data-lucide="arrow-right" class="size-4 rtl:rotate-180" aria-hidden="true"></i>{% endcache %}
                                <?= e($t('dashboard.blogs.open')) ?><span class="sr-only"> <?= e($blogName) ?></span>
                            </button>
                        </form>
                        <div class="flex items-center gap-0.5">
                            <?php if ($blog['can_settings']) { ?>
                            <a href="<?= e(lurl('/dashboard/blog/'.($bid).'/settings')) ?>" class="<?= $iconButton ?>" title="<?= e($t('dashboard.blogs.settings')) ?>" aria-label="<?= e($t('dashboard.blogs.settings').': '.$blogName) ?>">
                                {% cache 'lucide:blogs:sliders' ttl=31536000 %}<i data-lucide="sliders" class="size-4" aria-hidden="true"></i>{% endcache %}
                            </a>
                            <?php } ?>
                            <?php if ($blog['can_team']) { ?>
                            <a href="<?= e(lurl('/dashboard/blog/'.($bid).'/team')) ?>" class="<?= $iconButton ?>" title="<?= e($t('dashboard.blogs.team')) ?>" aria-label="<?= e($t('dashboard.blogs.team').': '.$blogName) ?>">
                                {% cache 'lucide:blogs:users' ttl=31536000 %}<i data-lucide="users" class="size-4" aria-hidden="true"></i>{% endcache %}
                            </a>
                            <?php } ?>
                            <a href="<?= e($live) ?>" target="_blank" rel="noopener" class="<?= $iconButton ?>" title="<?= e($t('dashboard.actions.viewLive')) ?>" aria-label="<?= e($t('dashboard.actions.viewLive').': '.$blogName) ?>">
                                {% cache 'lucide:blogs:external-link' ttl=31536000 %}<i data-lucide="external-link" class="size-4" aria-hidden="true"></i>{% endcache %}
                            </a>
                            <?php if (!$blog['is_owner']) { ?>
                            <form method="POST" action="/dashboard/blog/<?= $bid ?>/team/leave" class="m-0"
                                  data-confirm="<?= e($t('dashboard.blogs.leaveConfirm', ['name' => $blogName])) ?>">
                                {{ csrf_field() }}
                                <button type="submit" class="p-2 text-slate-500 hover:text-red-500 rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500" title="<?= e($t('dashboard.blogs.leave')) ?>" aria-label="<?= e($t('dashboard.blogs.leave').': '.$blogName) ?>">
                                    {% cache 'lucide:blogs:log-out' ttl=31536000 %}<i data-lucide="log-out" class="size-4 rtl:rotate-180" aria-hidden="true"></i>{% endcache %}
                                </button>
                            </form>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </article>
            <?php } ?>
        </div>
        <?php } ?>
    </section>
    <?php } ?>

    <?php } ?>
</div>
{% endblock %}
