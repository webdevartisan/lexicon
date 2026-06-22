{% extends "back.lex.php" %}
{% block title %}{{ t('navigation.allPosts') }}{% endblock %}
{% block body %}
<?php
$tabs = [
    ['key' => '', 'label' => 'All', 'count' => $counts['all']],
    ['key' => 'published', 'label' => 'Published', 'count' => $counts['published']],
    ['key' => 'draft', 'label' => 'Drafts', 'count' => $counts['draft']],
    ['key' => 'pending', 'label' => 'Pending', 'count' => $counts['pending']],
    ['key' => 'archived', 'label' => 'Archived', 'count' => $counts['archived']],
];

$makeQuery = static function (array $overrides = []) use ($q, $status, $blog_id, $sort, $categoryId, $tagId): string {
    $params = [
        'q' => $overrides['q'] ?? $q,
        'status' => array_key_exists('status', $overrides) ? $overrides['status'] : $status,
        'blog_id' => array_key_exists('blog_id', $overrides) ? $overrides['blog_id'] : $blog_id,
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
    'newest' => 'Newest first',
    'oldest' => 'Oldest first',
    'title_asc' => 'Title A → Z',
    'title_desc' => 'Title Z → A',
];
?>

<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex justify-end mb-4">
        {% set newPostLabel = t('dashboard.actions.newPost') %}
        {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
    </div>

    <form method="GET" action="/dashboard/post" class="card mb-4">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <div class="md:col-span-3">
                    <label for="q" class="form-label text-xs font-medium text-slate-600 dark:text-zink-200 mb-1">Search</label>
                    <div class="relative">
                        <i data-lucide="search" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        <input
                            id="q"
                            name="q"
                            type="text"
                            value="{{ q }}"
                            placeholder="Search by title or content..."
                            class="form-input w-full pl-9 border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500"
                        >
                    </div>
                </div>

                <div class="md:col-span-3">
                    <label for="blog" class="form-label text-xs font-medium text-slate-600 dark:text-zink-200 mb-1">Blog</label>
                    <select id="blog" name="blog_id" class="form-input w-full border-slate-200 dark:border-zink-500">
                        <option value="">All blogs</option>
                        {% foreach ($blogs as $b): %}
                            <option value="<?= e((string) $b['id']) ?>" <?= (int) $b['id'] === (int) ($blog_id ?? 0) ? 'selected' : '' ?>>
                                <?= e($b['blog_name']) ?>
                            </option>
                        {% endforeach %}
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="category" class="form-label text-xs font-medium text-slate-600 dark:text-zink-200 mb-1">Category</label>
                    <select id="category" name="category" class="form-input w-full border-slate-200 dark:border-zink-500" <?= empty($blogCategories) ? 'disabled' : '' ?>>
                        <option value="">All</option>
                        <?php foreach (($blogCategories ?? []) as $cat) { ?>
                        <option value="<?= (int) $cat['id'] ?>" <?= (int) ($categoryId ?? 0) === (int) $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="tag" class="form-label text-xs font-medium text-slate-600 dark:text-zink-200 mb-1">Tag</label>
                    <select id="tag" name="tag" class="form-input w-full border-slate-200 dark:border-zink-500" <?= empty($blogTags) ? 'disabled' : '' ?>>
                        <option value="">All</option>
                        <?php foreach (($blogTags ?? []) as $tg) { ?>
                        <option value="<?= (int) $tg['id'] ?>" <?= (int) ($tagId ?? 0) === (int) $tg['id'] ? 'selected' : '' ?>>
                            <?= e($tg['name']) ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>

                <div class="md:col-span-2 flex items-end">
                    <button
                        type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors"
                    >
                        <i data-lucide="filter" class="size-4"></i>
                        Apply
                    </button>
                </div>
            </div>

            <input type="hidden" name="status" value="{{ status }}">
        </div>
    </form>

    <?php if (!empty($activeTag)) { ?>
    <div class="flex items-center gap-2 mb-4 text-sm">
        <span class="text-slate-500 dark:text-zink-300">Filtering by tag:</span>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium rounded-full bg-custom-50 text-custom-700 border border-custom-100 dark:bg-custom-500/20 dark:text-custom-300">
            <i data-lucide="tag" class="size-3"></i>
            <?= e($activeTag['name']) ?>
            <a href="/dashboard/post<?= e($makeQuery(['tag' => null, 'page' => null])) ?>" class="ml-0.5 hover:text-red-600" title="Clear tag filter">&times;</a>
        </span>
    </div>
    <?php } ?>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="flex flex-wrap gap-2">
            <?php foreach ($tabs as $tab) { ?>
                <?php
                $active = (string) $status === (string) $tab['key'];
                $url = '/dashboard/post'.$makeQuery([
                    'status' => $tab['key'],
                    'page' => null,
                ]);
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

        <form method="GET" action="/dashboard/post" class="flex items-center gap-2">
            <?php foreach (['q' => $q, 'status' => $status, 'blog_id' => $blog_id, 'category' => $categoryId, 'tag' => $tagId] as $key => $value) { ?>
                <?php if ($value !== '' && $value !== null) { ?>
                    <input type="hidden" name="<?= e((string) $key) ?>" value="<?= e((string) $value) ?>">
                <?php } ?>
            <?php } ?>

            <label for="sort" class="text-xs font-medium text-slate-500 dark:text-zink-300">Sort:</label>
            <select
                id="sort"
                name="sort"
                onchange="this.form.submit()"
                class="form-input text-xs py-1.5 pl-2 pr-8 border-slate-200 dark:border-zink-500 rounded-md min-w-[140px]"
            >
                <?php foreach ($sortOptions as $key => $label) { ?>
                    <option value="<?= e($key) ?>" <?= $sort === $key ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php } ?>
            </select>
        </form>
    </div>

    {% if posts|empty %}
    <?php
    $emptyByStatus = [
        'draft' => [
            'icon' => 'file-text',
            'title' => 'No drafts yet',
            'text' => 'Start a new draft and come back to it whenever you want.',
            'cta' => 'Start writing',
        ],
        'pending' => [
            'icon' => 'clock',
            'title' => 'Nothing waiting for review',
            'text' => 'Posts submitted for review will show up here.',
            'cta' => 'New post',
        ],
        'published' => [
            'icon' => 'rss',
            'title' => 'No published posts yet',
            'text' => 'Publish a draft when you are ready to make it live.',
            'cta' => 'New post',
        ],
        'archived' => [
            'icon' => 'archive',
            'title' => 'No archived posts',
            'text' => 'Archived posts will appear here.',
            'cta' => null,
        ],
    ];

$emptyTitle = 'No posts yet';
$emptyText = 'Create your first post to get started.';
$emptyIcon = 'feather';
$clearUrl = null;

if ($q !== '') {
    $emptyTitle = 'No matches for "'.truncate($q, 40).'"';
    $emptyText = $status !== ''
        ? 'Try changing the status filter or using a different search term.'
        : 'Try a different search term.';
    $emptyIcon = 'search-x';
    $clearUrl = '/dashboard/post';
} elseif ($status !== '' && isset($emptyByStatus[$status])) {
    $emptyTitle = $emptyByStatus[$status]['title'];
    $emptyText = $emptyByStatus[$status]['text'];
    $emptyIcon = $emptyByStatus[$status]['icon'];
}
?>
    <div class="card">
        <div class="card-body text-center py-12">
            <i data-lucide="<?= e($emptyIcon) ?>" class="size-12 text-slate-400 mx-auto mb-3"></i>
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1"><?= e($emptyTitle) ?></h3>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-5"><?= e($emptyText) ?></p>

            <div class="flex flex-wrap items-center justify-center gap-2">
                <?php if ($status !== 'archived' || $q !== '') { ?>
                    {% set newPostLabel = t('dashboard.actions.newPost') %}
                    {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
                <?php } ?>

                <?php if ($clearUrl !== null) { ?>
                    <a
                        href="<?= e($clearUrl) ?>"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors"
                    >
                        <i data-lucide="x" class="size-4"></i>
                        Clear filters
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
    {% else %}
    <form id="bulk-form" method="POST" action="/dashboard/post/bulk">
        {{ csrf_field() }}
        <input type="hidden" name="bulk_action" id="bulk-action" value="">

        <div class="flex items-center justify-between mb-3 text-xs">
            <label class="inline-flex items-center gap-2 text-slate-600 dark:text-zink-200 cursor-pointer">
                <input
                    type="checkbox"
                    id="select-all"
                    class="form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500"
                >
                <span>Select all on this page</span>
            </label>
            <span id="selected-count" class="text-slate-500 dark:text-zink-300"></span>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            {% foreach ($posts as $post): %}
            <div class="relative bulk-item">
                <label class="absolute top-3 left-3 z-10 inline-flex items-center justify-center w-6 h-6 rounded-md bg-white/90 border border-slate-200 cursor-pointer hover:border-custom-500 dark:bg-zink-700/90 dark:border-zink-500">
                    <input
                        type="checkbox"
                        name="post_ids[]"
                        value="<?= e((string) $post['id']) ?>"
                        aria-label="Select post: <?= e((string) ($post['title'] ?? '')) ?>"
                        class="bulk-checkbox form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-2 focus:ring-custom-500 focus:ring-offset-1"
                    >
                </label>
                {% cmp="post-card" post="{$post}" blogSlug="{$activeBlogSlug}" %}
            </div>
            {% endforeach %}
        </div>

        <div class="mt-6">
            {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="/dashboard/post" %}
        </div>

        <div id="bulk-bar" class="hidden" style="position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); top: auto; z-index: 9999;">
            <div class="flex items-center gap-2 px-4 py-3 bg-slate-900 dark:bg-zink-700 text-white rounded-full shadow-lg border border-slate-700">
                <span id="bulk-count" class="text-sm font-medium pr-2 border-r border-slate-700"></span>

                <button type="button" data-bulk="publish" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md hover:bg-green-600/30 transition-colors">
                    <i data-lucide="send" class="size-3.5"></i> Publish
                </button>

                <button type="button" data-bulk="draft" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md hover:bg-slate-600/50 transition-colors">
                    <i data-lucide="pencil-ruler" class="size-3.5"></i> Move to draft
                </button>

                <button type="button" data-bulk="archive" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md hover:bg-orange-600/30 transition-colors">
                    <i data-lucide="archive" class="size-3.5"></i> Archive
                </button>

                <button type="button" data-bulk="delete" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md hover:bg-red-600/30 transition-colors text-red-300">
                    <i data-lucide="trash-2" class="size-3.5"></i> Delete
                </button>
            </div>
        </div>
    </form>
    {% endif %}

</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
<script>
(function () {
    const form = document.getElementById('bulk-form');
    if (!form) return;

    const checkboxes = form.querySelectorAll('.bulk-checkbox');
    const selectAll = document.getElementById('select-all');
    const bulkBar = document.getElementById('bulk-bar');
    const bulkCount = document.getElementById('bulk-count');
    const selectedCount = document.getElementById('selected-count');
    const actionInput = document.getElementById('bulk-action');

    document.body.appendChild(bulkBar);

    function getSelectedCount() {
        return Array.from(checkboxes).filter((checkbox) => checkbox.checked).length;
    }

    function updateBulkUi() {
        const selected = getSelectedCount();

        if (selected > 0) {
            bulkBar.classList.remove('hidden');
            bulkCount.textContent = selected + ' selected';
            selectedCount.textContent = selected + ' selected';
        } else {
            bulkBar.classList.add('hidden');
            selectedCount.textContent = '';
        }

        selectAll.checked = selected > 0 && selected === checkboxes.length;
        selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
    }

    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateBulkUi);
    });

    selectAll.addEventListener('change', function () {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });

        updateBulkUi();
    });

    bulkBar.querySelectorAll('[data-bulk]').forEach((button) => {
        button.addEventListener('click', function () {
            const action = button.dataset.bulk;
            const selected = getSelectedCount();

            const messages = {
                delete: 'Permanently delete ' + selected + ' post(s)? This cannot be undone.',
                archive: 'Archive ' + selected + ' post(s)?',
                publish: 'Publish ' + selected + ' post(s)?',
                draft: 'Move ' + selected + ' post(s) to draft?',
            };

            if (!confirm(messages[action])) {
                return;
            }

            actionInput.value = action;
            form.submit();
        });
    });
})();
</script>
{% endblock %}