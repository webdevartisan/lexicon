{% extends "back.lex.php" %}
{% block title %}{{ t('dashboard.blogs.title') }}{% endblock %}
{% block subtitle %}<span class="hidden sm:inline">{{ t('dashboard.blogs.subtitle') }}</span>{% endblock %}
{% block actions %}
<?php if (!empty($can_create_blog)) {
    $createLabel = $t('dashboard.blogs.create');
    $createAttrs = ['data-panel' => 'open'];
    ?>
{% cmp="btn" href="/start" variant="blue" icon="plus" label="{$createLabel}" attrs="{$createAttrs}" %}
<?php } ?>
{% endblock %}
{% block body %}
<?php
$makeQuery = static function (array $overrides = []) use (
    $q, $status, $role, $sortExplicit, $tableSort, $listView
): string {
    $params = [
        'q' => $q,
        'status' => array_key_exists('status', $overrides) ? $overrides['status'] : $status,
        'role' => array_key_exists('role', $overrides) ? $overrides['role'] : $role,
        'sort' => $sortExplicit ? $tableSort->key() : '',
        'dir' => $sortExplicit ? $tableSort->direction() : '',
        'view' => array_key_exists('view', $overrides) ? $overrides['view'] : $listView,
    ];
    $params = array_filter($params, static function ($value): bool {
        return $value !== '' && $value !== null;
    });

    return $params ? '?'.http_build_query($params) : '';
};

$listBase = lurl('/dashboard/blog');
$sortOptions = [
    'updated' => $t('dashboard.blogs.sort.updated'),
    'created' => $t('dashboard.blogs.sort.created'),
    'posts' => $t('dashboard.blogs.sort.posts'),
    'name' => $t('dashboard.blogs.sort.name'),
];
if ($sortChoice === '') {
    // Sorted by a column header: the menu has no matching name, so say so instead of showing a wrong one.
    $sortOptions = ['' => $t('dashboard.blogs.sort.label')] + $sortOptions;
}

$statusOptions = [
    '' => $t('dashboard.blogs.filters.all').' ('.(int) $counts['all'].')',
    'published' => $t('dashboard.blogStatus.published').' ('.(int) $counts['published'].')',
    'draft' => $t('dashboard.blogStatus.draft').' ('.(int) $counts['draft'].')',
    'archived' => $t('dashboard.blogStatus.archived').' ('.(int) $counts['archived'].')',
];
$roleOptions = [
    '' => $t('dashboard.blogs.filters.all'),
    'owned' => $t('dashboard.blogs.groups.owned'),
    'shared' => $t('dashboard.blogs.groups.shared'),
];

$filters = [
    [
        'label' => $t('dashboard.blogs.filters.status'),
        'name' => 'status',
        'options' => $statusOptions,
        'selected' => $status,
    ],
];
if ($hasShared) {
    $filters[] = [
        'label' => $t('dashboard.blogs.filters.role'),
        'name' => 'role',
        'options' => $roleOptions,
        'selected' => $role,
    ];
}
$viewChoices = [
    'cards' => ['icon' => 'layout-grid', 'label' => $t('dashboard.blogs.view.cards')],
    'list' => ['icon' => 'list', 'label' => $t('dashboard.blogs.view.compact')],
];
foreach ($viewChoices as $viewKey => $choice) {
    $viewChoices[$viewKey]['href'] = lurl('/dashboard/blog'.$makeQuery(['view' => $viewKey]));
}
$keepSort = null;
if ($sortChoice === '' && $sortExplicit) {
    $keepSort = ['sort' => $tableSort->key(), 'dir' => $tableSort->direction()];
}
$toolbar = [
    'action' => $listBase,
    'q' => $q,
    'searchPlaceholder' => $t('dashboard.blogs.search'),
    'filters' => $filters,
    'filtersLabel' => $t('dashboard.blogs.filters.title'),
    'applyLabel' => $t('dashboard.blogs.filters.apply'),
    'clearLabel' => $t('dashboard.blogs.clearFilters'),
    'clearHref' => lurl('/dashboard/blog'.$makeQuery(['status' => '', 'role' => ''])),
    'view' => ['current' => $listView, 'label' => $t('dashboard.blogs.view.label'), 'choices' => $viewChoices],
    'sort' => [
        'options' => $sortOptions,
        'selected' => $sortChoice,
        'keep' => $keepSort,
    ],
];
$colName = $t('dashboard.blogs.col.name');
$colRole = $t('dashboard.blogs.col.role');
$colStatus = $t('dashboard.blogs.col.status');
$colPosts = $t('dashboard.blogs.col.posts');
$colTeam = $t('dashboard.blogs.col.team');
$colUpdated = $t('dashboard.blogs.col.updated');
$colActions = $t('dashboard.blogs.col.actions');
$itemSingular = $t('dashboard.blogs.item.one');
$itemPlural = $t('dashboard.blogs.item.other');
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    {% cmp="list-toolbar" toolbar="{$toolbar}" %}

    {% if blogs|empty %}
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
    {% else %}

        <?php if ($listView === 'list') { ?>
        <div class="card">
            <div class="card-body relative p-0 overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="text-left bg-slate-100 dark:bg-zink-600">
                        <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="name" label="{$colName}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="role" label="{$colRole}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="status" label="{$colStatus}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="posts" label="{$colPosts}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="team" label="{$colTeam}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="updated" label="{$colUpdated}" %}
                            <th class="px-3.5 py-2.5 font-semibold text-right"><?= e($colActions) ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                        {% foreach ($blogs as $blog): %}
                        <?php
                        $blogName = (string) $blog['blog_name'];
                        $blogStatus = (string) $blog['status'];
                        $blogStatusLabel = $t('dashboard.blogStatus.'.$blogStatus);
                        ?>
                        <tr class="transition-colors hover:bg-slate-50/60 dark:hover:bg-zink-700/40">
                            <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50">
                                <div class="flex items-center gap-2 max-w-[18rem]">
                                    <span class="truncate" dir="auto"><?= e($blogName) ?></span>
                                    <?php if ($blog['is_active']) { ?>
                                    <span class="shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-semibold rounded-full bg-custom-500 text-white"><?= e($t('dashboard.blogs.active')) ?></span>
                                    <?php } ?>
                                </div>
                                <p class="mt-0.5 text-xs font-normal text-slate-500 dark:text-zink-300 truncate" dir="ltr">/blog/<?= e($blog['blog_slug']) ?></p>
                            </td>
                            <td class="px-3.5 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-violet-100 text-violet-700 border-violet-200 dark:bg-violet-900/40 dark:text-violet-300 dark:border-violet-800"><?= e($blog['role_label']) ?></span>
                            </td>
                            <td class="px-3.5 py-2.5">
                                {% cmp="status-badge" status="{$blogStatus}" label="{$blogStatusLabel}" %}
                            </td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= (int) $blog['post_count'] ?></td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= (int) $blog['author_count'] ?></td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e(local_datetime($blog['updated_at'] ?? $blog['created_at'] ?? null, 'M j, Y')) ?></td>
                            <td class="px-3.5 py-2.5">
                                <div class="flex items-center justify-end gap-3">
                                    {% cmp="blog-actions" blog="{$blog}" %}
                                </div>
                            </td>
                        </tr>
                        {% endforeach; %}
                    </tbody>
                </table>
            </div>
        </div>
        <?php } else { ?>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            {% foreach ($blogs as $blog): %}
            <?php
            $blogName = (string) $blog['blog_name'];
            $blogStatus = (string) $blog['status'];
            $blogStatusLabel = $t('dashboard.blogStatus.'.$blogStatus);
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
                        {% cmp="status-badge" status="{$blogStatus}" label="{$blogStatusLabel}" %}
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
                        {% cmp="blog-actions" blog="{$blog}" %}
                    </div>
                </div>
            </article>
            {% endforeach %}
        </div>
        <?php } ?>

        <div class="mt-6">
            {% cmp="paginator" pagination="{$pagination}" pageParam="page" basePath="/dashboard/blog" itemSingular="{$itemSingular}" itemPlural="{$itemPlural}" %}
        </div>
    {% endif %}

</div>
{% endblock %}
