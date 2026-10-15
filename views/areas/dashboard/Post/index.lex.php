{% extends "back.lex.php" %}
{% block title %}{{ t('navigation.posts') }}{% endblock %}
{% block subtitle %}<?= e($t($managesAll ? 'dashboard.posts.subtitleAll' : 'dashboard.posts.subtitleOwn')) ?>{% endblock %}
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

$makeQuery = static function (array $overrides = []) use ($q, $status, $sort, $categoryId, $tagId): string {
    $params = [
        'q' => $overrides['q'] ?? $q,
        'status' => array_key_exists('status', $overrides) ? $overrides['status'] : $status,
        'sort' => array_key_exists('sort', $overrides) ? $overrides['sort'] : $sort,
        'category' => array_key_exists('category', $overrides) ? $overrides['category'] : $categoryId,
        'tag' => array_key_exists('tag', $overrides) ? $overrides['tag'] : $tagId,
        'page' => $overrides['page'] ?? null,
    ];

    if (($params['sort'] ?? 'newest') === 'newest') {
        unset($params['sort']);
    }

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
$selectedSortKey = (string) ($sort ?? '');
$searchLabel = $t('dashboard.posts.filters.search');
$searchPlaceholder = $t('dashboard.posts.filters.searchPlaceholder');
$categoryLabel = $t('dashboard.posts.filters.category');
$tagLabel = $t('dashboard.posts.filters.tag');
$applyLabel = $t('dashboard.posts.filters.apply');
$sortLabel = $t('dashboard.posts.sort.label');
?>

<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <?php if ($canCreate) { ?>
    <div class="flex justify-end mb-4">
        {% set newPostLabel = t('dashboard.actions.newPost') %}
        {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
    </div>
    <?php } ?>

    <form method="GET" action="<?= e(lurl('/dashboard/post')) ?>" class="card mb-4">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-5">
                    {% cmp="input" type="text" label="{$searchLabel}" name="q" value="{$q}" placeholder="{$searchPlaceholder}" %}
                </div>

                <div class="md:col-span-3">
                    {% cmp="select" label="{$categoryLabel}" name="category" options="{$categoryOptions}" selectedKey="{$selectedCategoryKey}" %}
                </div>

                <div class="md:col-span-2">
                    {% cmp="select" label="{$tagLabel}" name="tag" options="{$tagOptions}" selectedKey="{$selectedTagKey}" %}
                </div>

                <div class="md:col-span-2">
                    {% cmp="btn" type="submit" variant="blue" icon="filter" label="{$applyLabel}" addClass="w-full" %}
                </div>
            </div>

            <input type="hidden" name="status" value="{{ status }}">
        </div>
    </form>

    <?php if (!empty($activeTag)) { ?>
    <div class="flex items-center gap-2 mb-4 text-sm">
        <span class="text-slate-500 dark:text-zink-300"><?= e($t('dashboard.posts.filters.byTag')) ?></span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-custom-50 text-custom-700 border border-custom-100 dark:bg-custom-500/20 dark:text-custom-300">
            <i data-lucide="tag" class="size-3"></i>
            <?= e($activeTag['name']) ?>
            <a href="<?= e(lurl('/dashboard/post'.($makeQuery(['tag' => null, 'page' => null])))) ?>" class="ml-0.5 hover:text-red-600" title="<?= e($t('dashboard.posts.filters.clearTag')) ?>">&times;</a>
        </span>
    </div>
    <?php } ?>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex flex-wrap gap-2">
            <?php foreach ($tabs as $tab) { ?>
                <?php
                $active = (string) $status === (string) $tab['key'];
                $url = lurl('/dashboard/post'.$makeQuery([
                    'status' => $tab['key'],
                    'page' => null,
                ]));
                ?>
                <a
                    href="<?= e($url) ?>"
                    aria-pressed="<?= $active ? 'true' : 'false' ?>"
                    <?= $active ? 'style="background-color: rgb(29, 78, 216); border-color: rgb(29, 78, 216);"' : '' ?>
                    class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 focus-visible:ring-offset-1 <?= $active
                        ? 'text-white'
                        : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:border-zink-400' ?>"
                >
                    <?= e($tab['label']) ?>
                    <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-semibold rounded-full <?= $active ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200' ?>">
                        <?= (int) $tab['count'] ?>
                    </span>
                </a>
            <?php } ?>
        </div>

        <form method="GET" action="<?= e(lurl('/dashboard/post')) ?>" class="flex items-center gap-2">
            <?php foreach (['q' => $q, 'status' => $status, 'category' => $categoryId, 'tag' => $tagId] as $key => $value) { ?>
                <?php if ($value !== '' && $value !== null) { ?>
                    <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                <?php } ?>
            <?php } ?>

            {% cmp="select" label="{$sortLabel}" name="sort" options="{$sortOptions}" selectedKey="{$selectedSortKey}" onchange="this.form.submit()" %}
        </form>
    </div>

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
            <i data-lucide="<?= e($emptyIcon) ?>" class="size-12 text-slate-400 mx-auto mb-3"></i>
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
                        <i data-lucide="x" class="size-4"></i>
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

        <div class="mt-6">
            {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="/dashboard/post" %}
        </div>
    {% endif %}

</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}