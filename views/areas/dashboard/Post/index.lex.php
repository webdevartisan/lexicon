{% extends "back.lex.php" %}
{% block title %}{{ t('navigation.posts') }}{% endblock %}
{% block subtitle %}<span class="hidden sm:inline"><?= e($t($managesAll ? 'dashboard.posts.subtitleAll' : 'dashboard.posts.subtitleOwn')) ?></span>{% endblock %}
{% block actions %}
<?php if ($canCreate) { ?>
{% set newPostLabel = t('dashboard.actions.newPost') %}
{% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
<?php } ?>
{% endblock %}
{% block body %}
<?php
// Review-stage pills only make sense when the active blog has editorial workflow on.
$showReviewPills = !empty($workflowEnabled);

$tabs = [
    ['key' => '', 'label' => $t('dashboard.posts.tabs.all'), 'count' => $counts['all']],
    ['key' => 'published', 'label' => $t('dashboard.posts.tabs.published'), 'count' => $counts['published']],
    ['key' => 'draft', 'label' => $t('dashboard.posts.tabs.drafts'), 'count' => $counts['draft']],
];

// Only worth a tab once something is actually waiting to go out.
if (!empty($counts['scheduled'])) {
    $tabs[] = ['key' => 'scheduled', 'label' => $t('dashboard.posts.tabs.scheduled'), 'count' => $counts['scheduled']];
}

if ($showReviewPills) {
    $tabs[] = ['key' => 'pending', 'label' => $t('dashboard.posts.tabs.inReview'), 'count' => $counts['pending']];
    $tabs[] = ['key' => 'needs_changes', 'label' => $t('dashboard.posts.tabs.needsChanges'), 'count' => $counts['needs_changes'] ?? 0];
}

$tabs[] = ['key' => 'archived', 'label' => $t('dashboard.posts.tabs.archived'), 'count' => $counts['archived']];

$makeQuery = static function (array $overrides = []) use ($q, $status, $sortExplicit, $tableSort, $categoryId, $tagId, $listView): string {
    $params = [
        'q' => $overrides['q'] ?? $q,
        'status' => array_key_exists('status', $overrides) ? $overrides['status'] : $status,
        'sort' => $sortExplicit ? $tableSort->key() : '',
        'dir' => $sortExplicit ? $tableSort->direction() : '',
        'category' => array_key_exists('category', $overrides) ? $overrides['category'] : $categoryId,
        'tag' => array_key_exists('tag', $overrides) ? $overrides['tag'] : $tagId,
        'page' => $overrides['page'] ?? null,
        'view' => array_key_exists('view', $overrides) ? $overrides['view'] : $listView,
    ];


    $params = array_filter($params, static fn ($value) => $value !== '' && $value !== null);

    return $params ? '?'.http_build_query($params) : '';
};

$sortOptions = [
    'newest' => $t('dashboard.posts.sort.newest'),
    'oldest' => $t('dashboard.posts.sort.oldest'),
    'title_asc' => $t('dashboard.posts.sort.titleAsc'),
    'title_desc' => $t('dashboard.posts.sort.titleDesc'),
];

$categoryOptions = ['' => $t('dashboard.posts.filters.all')];
foreach (($blogCategories ?? []) as $cat) {
    $categoryOptions[(string) $cat['id']] = (string) $cat['name'];
}

$tagOptions = ['' => $t('dashboard.posts.filters.all')];
foreach (($blogTags ?? []) as $tg) {
    $tagOptions[(string) $tg['id']] = (string) $tg['name'];
}

$selectedCategoryKey = (string) ($categoryId ?? '');
$selectedTagKey = (string) ($tagId ?? '');
$selectedSortKey = (string) $sortChoice;
if ($sortChoice === '') {
    // Sorted by a column header: the menu has no matching name, so say so instead of showing a wrong one.
    $sortOptions = ['' => $t('dashboard.posts.sort.label')] + $sortOptions;
}
$listBase = lurl('/dashboard/post');
$colTitle = $t('dashboard.posts.view.col.title');
$colAuthor = $t('dashboard.posts.view.col.author');
$colStatus = $t('dashboard.posts.view.col.status');
$colVisibility = $t('dashboard.posts.view.col.visibility');
$colComments = $t('dashboard.posts.view.col.comments');
$colUpdated = $t('dashboard.posts.view.col.updated');
$colActions = $t('dashboard.posts.view.col.actions');
$statusOptions = [];
foreach ($tabs as $tab) {
    $statusOptions[(string) $tab['key']] = $tab['label'].' ('.(int) $tab['count'].')';
}
$selectedStatusKey = (string) $status;
$viewChoices = [
    'cards' => ['icon' => 'layout-grid', 'label' => $t('dashboard.posts.view.cards')],
    'list' => ['icon' => 'list', 'label' => $t('dashboard.posts.view.compact')],
];
foreach ($viewChoices as $viewKey => $choice) {
    $viewChoices[$viewKey]['href'] = lurl('/dashboard/post'.$makeQuery(['view' => $viewKey, 'page' => null]));
}
$keepSort = null;
if ($sortChoice === '' && $sortExplicit) {
    $keepSort = ['sort' => $tableSort->key(), 'dir' => $tableSort->direction()];
}
$clearQuery = $makeQuery(['status' => '', 'category' => null, 'tag' => null, 'page' => null]);
$toolbar = [
    'action' => $listBase,
    'q' => $q,
    'searchPlaceholder' => $t('dashboard.posts.filters.searchPlaceholder'),
    'filters' => [
        [
            'label' => $t('dashboard.posts.filters.status'),
            'name' => 'status',
            'options' => $statusOptions,
            'selected' => $selectedStatusKey,
        ],
        [
            'label' => $t('dashboard.posts.filters.category'),
            'name' => 'category',
            'options' => $categoryOptions,
            'selected' => $selectedCategoryKey,
        ],
        [
            'label' => $t('dashboard.posts.filters.tag'),
            'name' => 'tag',
            'options' => $tagOptions,
            'selected' => $selectedTagKey,
        ],
    ],
    'filtersLabel' => $t('dashboard.posts.filters.title'),
    'applyLabel' => $t('dashboard.posts.filters.apply'),
    'clearLabel' => $t('dashboard.posts.filters.clear'),
    'clearHref' => lurl('/dashboard/post'.$clearQuery),
    'view' => ['current' => $listView, 'label' => $t('dashboard.posts.view.label'), 'choices' => $viewChoices],
    'sort' => [
        'options' => $sortOptions,
        'selected' => $selectedSortKey,
        'keep' => $keepSort,
    ],
];
?>

<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <?php if (!empty($blogVisibility)) { ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800/60 dark:bg-amber-900/20">
        {% cmp="visibility-line" line="{$blogVisibility}" returnTo="/dashboard/post" %}
    </div>
    <?php } ?>

    {% cmp="list-toolbar" toolbar="{$toolbar}" %}

    <?php if (!empty($activeTag)) { ?>
    <div class="flex items-center gap-2 mb-4 text-sm">
        <span class="text-slate-500 dark:text-zink-300"><?= e($t('dashboard.posts.filters.byTag')) ?></span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-custom-50 text-custom-700 border border-custom-100 dark:bg-custom-500/20 dark:text-custom-300">
            {% cache 'lucide:tag:sm' ttl=31536000 %}<i data-lucide="tag" class="size-3"></i>{% endcache %}
            <?= e($activeTag['name']) ?>
            <a href="<?= e(lurl('/dashboard/post'.($makeQuery(['tag' => null, 'page' => null])))) ?>" class="ml-0.5 hover:text-red-600" title="<?= e($t('dashboard.posts.filters.clearTag')) ?>">&times;</a>
        </span>
    </div>
    <?php } ?>

    {% if posts|empty %}
    <?php
    $emptyIcons = [
        'draft' => 'file-text',
        'pending' => 'clock',
        'published' => 'rss',
        'scheduled' => 'calendar-clock',
        'archived' => 'archive',
    ];

$emptyTitle = $t('dashboard.posts.empty.noneTitle');
$emptyText = $t('dashboard.posts.empty.noneText');
$emptyIcon = 'feather';
$clearUrl = null;

if ($q !== '') {
    $emptyTitle = $t('dashboard.posts.empty.searchTitle', ['query' => truncate($q, 40)]);
    $emptyText = $t($status !== '' ? 'dashboard.posts.empty.searchTextStatus' : 'dashboard.posts.empty.searchText');
    $emptyIcon = 'search-x';
    $clearUrl = lurl('/dashboard/post');
} elseif ($status !== '' && isset($emptyIcons[$status])) {
    $emptyTitle = $t('dashboard.posts.empty.'.$status.'Title');
    $emptyText = $t('dashboard.posts.empty.'.$status.'Text');
    $emptyIcon = $emptyIcons[$status];
}
?>
    <div class="card">
        <div class="card-body text-center py-12">
            {% cache 'lucide:empty:' . $emptyIcon ttl=31536000 %}<i data-lucide="<?= e($emptyIcon) ?>" class="size-12 text-slate-400 mx-auto mb-3"></i>{% endcache %}
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1"><?= e($emptyTitle) ?></h3>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-5"><?= e($emptyText) ?></p>

            <div class="flex flex-wrap items-center justify-center gap-2">
                <?php if ($canCreate && ($status !== 'archived' || $q !== '')) { ?>
                    {% set newPostLabel = t('dashboard.actions.newPost') %}
                    {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
                <?php } ?>

                <?php if ($clearUrl !== null) { ?>
                    <a
                        href="<?= e($clearUrl) ?>"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors"
                    >
                        {% cache 'lucide:x' ttl=31536000 %}<i data-lucide="x" class="size-4"></i>{% endcache %}
                        <?= e($t('dashboard.posts.filters.clear')) ?>
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
    {% else %}
    <?php
    $bulkActions = [
        ['action' => 'publish', 'label' => $t('dashboard.posts.bulk.publish'), 'icon' => 'send', 'hover' => 'hover:bg-green-600/30', 'confirm' => $t('dashboard.posts.bulk.publishConfirm')],
        ['action' => 'draft', 'label' => $t('dashboard.posts.bulk.draft'), 'icon' => 'pencil-ruler', 'hover' => 'hover:bg-slate-600/50', 'confirm' => $t('dashboard.posts.bulk.draftConfirm')],
    ];
    if ($showReviewPills) {
        $bulkActions[] = ['action' => 'review', 'label' => $t('dashboard.posts.bulk.review'), 'icon' => 'pencil-ruler', 'hover' => 'hover:bg-slate-600/50', 'confirm' => $t('dashboard.posts.bulk.reviewConfirm')];
    }
    $bulkActions[] = ['action' => 'archive', 'label' => $t('dashboard.posts.bulk.archive'), 'icon' => 'archive', 'hover' => 'hover:bg-orange-600/30', 'confirm' => $t('dashboard.posts.bulk.archiveConfirm')];
    $bulkActions[] = ['action' => 'delete', 'label' => $t('dashboard.posts.bulk.delete'), 'icon' => 'trash-2', 'hover' => 'hover:bg-red-600/30', 'danger' => true, 'confirm' => $t('dashboard.posts.bulk.deleteConfirm')];
    ?>
    <!-- Cards must stay outside the bulk form: each card carries its own
         action forms, and nesting forms makes the browser drop the inner
         ones. Checkboxes attach via form="bulk-form" instead. -->
    <?php if ($managesAll) { ?>
    {% cmp="bulk-actions-bar" actionUrl="/dashboard/post/bulk" itemLabel="post" actions="{$bulkActions}" %}
    <?php } ?>

        <?php if ($listView === 'list') { ?>
        <div class="card">
            <div class="card-body p-0 overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="text-left bg-slate-100 dark:bg-zink-600">
                        <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                            <?php if ($managesAll) { ?><th class="px-3.5 py-2.5 w-10"></th><?php } ?>
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="title" label="{$colTitle}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="author" label="{$colAuthor}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="status" label="{$colStatus}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="visibility" label="{$colVisibility}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="comments" label="{$colComments}" %}
                            {% cmp="sortable-th" sort="{$tableSort}" base="{$listBase}" sortKey="updated" label="{$colUpdated}" %}
                            <th class="px-3.5 py-2.5 font-semibold text-right"><?= e($colActions) ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                        {% foreach ($posts as $post): %}
                        <?php
                        $postId = (int) $post['id'];
                        $postStatus = (string) ($post['status'] ?? 'draft');
                        $postVisibility = (string) ($post['visibility'] ?? 'public');
                        $can = ($post['can'] ?? []) + ['update' => true, 'open' => true];
                        $editHref = lurl('/dashboard/post/'.$postId.'/edit?r='.urlencode($returnToken));
                        $rowTitle = (string) $post['title'];
                        [$visibilityStatus, $visibilityLabel] = match ($postVisibility) {
                            'private' => ['draft', $t('components.postCard.visibility.private')],
                            'unlisted' => ['pending', $t('components.postCard.visibility.unlisted')],
                            default => ['published', $t('dashboard.posts.view.col.public')],
                        };
                        ?>
                        <tr class="transition-colors hover:bg-slate-50/60 dark:hover:bg-zink-700/40">
                            <?php if ($managesAll) { ?>
                            <td class="px-3.5 py-2.5">
                                <input type="checkbox" name="post_ids[]" form="bulk-form" value="<?= $postId ?>" class="bulk-checkbox"
                                    aria-label="<?= e($t('dashboard.posts.selectPost', ['title' => $rowTitle])) ?>">
                            </td>
                            <?php } ?>
                            <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50">
                                <div class="flex flex-wrap items-center gap-1 max-w-[16rem]" data-post-title data-title="<?= e($rowTitle) ?>" data-rename-url="<?= e(lurl('/dashboard/post/'.$postId.'/rename')) ?>">
                                    <?php if (!empty($post['is_featured'])) { ?>
                                    {% cmp="featured-star" %}
                                    <?php } ?>
                                    <span class="flex-1 min-w-0 truncate" data-post-title-text>
                                        <?php if ($can['open']) { ?>
                                        <a href="<?= e($editHref) ?>" class="hover:text-custom-500 transition-colors"><?= e($rowTitle) ?></a>
                                        <?php } else { ?>
                                        <?= e($rowTitle) ?>
                                        <?php } ?>
                                    </span>
                                    <?php if ($can['update']) { ?>
                                    <button type="button" data-post-rename title="<?= e($t('dashboard.posts.rename.button')) ?>" aria-label="<?= e($t('dashboard.posts.rename.button')) ?>"
                                        class="shrink-0 p-1 rounded-md text-slate-400 hover:text-custom-500 hover:bg-slate-100 dark:text-zink-300 dark:hover:bg-zink-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
                                        {% cache 'lucide:pencil:rename' ttl=31536000 %}<i data-lucide="pencil" class="size-3.5" aria-hidden="true"></i>{% endcache %}
                                    </button>
                                    <?php } ?>
                                </div>
                            </td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300">
                                <?= !empty($post['author_handle']) ? '@'.e((string) $post['author_handle']) : '—' ?>
                            </td>
                            <td class="px-3.5 py-2.5">
                                {% cmp="status-badge" status="{$postStatus}" %}
                            </td>
                            <td class="px-3.5 py-2.5">
                                {% cmp="status-badge" status="{$visibilityStatus}" label="{$visibilityLabel}" %}
                            </td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= (int) ($post['comment_count'] ?? 0) ?></td>
                            <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e(local_datetime($post['updated_at'] ?? $post['created_at'] ?? null, 'M j, Y')) ?></td>
                            <td class="px-3.5 py-2.5">
                                <div class="flex items-center justify-end gap-1">
                                    {% cmp="post-actions" post="{$post}" blogSlug="{$blogSlug}" returnToken="{$returnToken}" %}
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
            {% foreach ($posts as $post): %}
            <?php if ($managesAll) { ?>
            <label class="relative block cursor-pointer bulk-item">
                <input
                    type="checkbox"
                    name="post_ids[]"
                    form="bulk-form"
                    value="<?= e((string) $post['id']) ?>"
                    aria-label="<?= e($t('dashboard.posts.selectPost', ['title' => (string) ($post['title'] ?? '')])) ?>"
                    class="peer sr-only bulk-checkbox"
                >
                {% cmp="post-card" post="{$post}" blogSlug="{$blogSlug}" returnToken="{$returnToken}" %}
            </label>
            <?php } else { ?>
            <div class="relative">
                {% cmp="post-card" post="{$post}" blogSlug="{$blogSlug}" returnToken="{$returnToken}" %}
            </div>
            <?php } ?>
            {% endforeach %}
        </div>
        <?php } ?>

        <div class="mt-6">
            {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="/dashboard/post" %}
        </div>
    {% endif %}

</div>

<div hidden data-post-tools
     data-rename-label="<?= e($t('dashboard.posts.rename.label')) ?>"
     data-rename-length="<?= e($t('dashboard.posts.rename.length')) ?>"
     data-rename-failed="<?= e($t('dashboard.posts.rename.failed')) ?>"><?= csrf_field() ?></div>

<?php if ($canCreate) { ?>
<?php
$duplicateTitle = $t('dashboard.posts.duplicate.title');
$duplicateConfirm = $t('dashboard.posts.duplicate.confirm');
$cancelLabel = $t('dashboard.posts.duplicate.cancel');
$duplicateBody = '<div data-duplicate-slot></div>';
$titleLabel = $t('dashboard.posts.duplicate.titleLabel');
$linkLabel = $t('dashboard.posts.duplicate.linkLabel');
$linkPrefix = e(base_url().'/blog/'.$blogSlug.'/');
?>
{% cmp="modal" id="postDuplicateModal" title="{$duplicateTitle}" icon="copy" body="{$duplicateBody}" form="postDuplicateForm" confirmText="{$duplicateConfirm}" cancelText="{$cancelLabel}" %}
<template id="postDuplicateTemplate"
          data-loading="<?= e($t('dashboard.posts.duplicate.loading')) ?>"
          data-failed="<?= e($t('dashboard.posts.duplicate.failed')) ?>">
    <form id="postDuplicateForm" method="POST" class="space-y-4 text-left"
          data-check-url="<?= e(lurl('/dashboard/slug-check').'?type=post&blog_id='.(int) $blogId) ?>">
        <?= csrf_field() ?>
        {% cmp="input" type="text" name="title" label="{$titleLabel}" required="true" %}
        {% cmp="input" type="text" name="slug" label="{$linkLabel}" prefix="{$linkPrefix}" required="true" %}
    </form>
</template>
<?php } ?>
{% endblock %}

{% block scripts %}
<script src="<?= e(versioned_asset('/cp-assets/js/tooltip.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/post-rename.js')) ?>"></script>
<?php if ($canCreate) { ?>
<script src="<?= e(versioned_asset('/cp-assets/js/slug-field.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/post-duplicate.js')) ?>"></script>
<?php } ?>
{% endblock %}