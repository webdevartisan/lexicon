{% extends "back.lex.php" %}
{% block title %}{{ t('navigation.allPosts') }}{% endblock %}
{% block body %}
<?php
$statusFilters = [
    ['key' => '',         'label' => 'All',       'count' => $counts['all']],
    ['key' => 'published', 'label' => 'Published', 'count' => $counts['published']],
    ['key' => 'draft',    'label' => 'Drafts',    'count' => $counts['draft']],
    ['key' => 'pending',  'label' => 'Pending',   'count' => $counts['pending']],
    ['key' => 'archived', 'label' => 'Archived',  'count' => $counts['archived']],
];

$buildQuery = static function (array $overrides) use ($q, $status, $blog_id): string {
    $params = array_filter([
        'q' => $overrides['q'] ?? $q,
        'status' => $overrides['status'] ?? $status,
        'blog_id' => $overrides['blog_id'] ?? $blog_id,
        'page' => $overrides['page'] ?? null,
    ], static fn ($v) => $v !== '' && $v !== null);

    return $params ? '?'.http_build_query($params) : '';
};
?>

<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex justify-end mb-4">
        {% set newPostLabel = t('dashboard.actions.newPost') %}
        {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="{$newPostLabel}" %}
    </div>

    <!-- Filters: search + blog scope + status. Submits via GET so links/back work. -->
    <form method="GET" action="/dashboard/post" class="card mb-4">
        <div class="card-body">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                <div class="md:col-span-7">
                    <label for="q" class="form-label text-xs font-medium text-slate-600 dark:text-zink-200 mb-1">Search</label>
                    <div class="relative">
                        <i data-lucide="search" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                        <input id="q" name="q" type="text" value="{{ q }}"
                            placeholder="Search by title or content..."
                            class="form-input w-full pl-9 border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                    </div>
                </div>
                <div class="md:col-span-3">
                    <label for="blog" class="form-label text-xs font-medium text-slate-600 dark:text-zink-200 mb-1">Blog</label>
                    <select id="blog" name="blog_id" class="form-input w-full border-slate-200 dark:border-zink-500">
                        <option value="">All blogs</option>
                        {% foreach ($blogs as $b): %}
                            <option value="<?= e((string) $b['id']) ?>" <?= ((int) $b['id'] === (int) ($blog_id ?? 0)) ? 'selected' : '' ?>>
                                <?= e($b['blog_name']) ?>
                            </option>
                        {% endforeach %}
                    </select>
                </div>
                <div class="md:col-span-2 flex items-end">
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                        <i data-lucide="filter" class="size-4"></i>
                        Apply
                    </button>
                </div>
            </div>
            <input type="hidden" name="status" value="{{ status }}">
        </div>
    </form>

    <!-- Status chips: filter without losing search/blog scope. -->
    <div class="flex flex-wrap gap-2 mb-5">
        <?php foreach ($statusFilters as $f) {
            $isActive = ((string) $status === (string) $f['key']);
            $href = '/dashboard/post'.$buildQuery(['status' => $f['key'], 'page' => null]);
            ?>
        <a href="<?= e($href) ?>"
            class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full border transition-colors <?= $isActive
                    ? 'bg-custom-500 text-white border-custom-500'
                    : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:border-zink-400' ?>">
            <?= e($f['label']) ?>
            <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-semibold rounded-full <?= $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200' ?>">
                <?= (int) $f['count'] ?>
            </span>
        </a>
        <?php } ?>
    </div>

    {% if posts|empty %}
    <div class="card">
        <div class="card-body text-center py-12">
            <i data-lucide="files" class="size-12 text-slate-400 mx-auto mb-3"></i>
            <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">No posts found</h3>
            <p class="text-sm text-slate-500 dark:text-zink-300 mb-5">
                <?php if ($q !== '' || $status !== '') { ?>
                    Try adjusting your search or filters.
                <?php } else { ?>
                    You haven't published anything yet. Start with your first post.
                <?php } ?>
            </p>
            {% cmp="btn" href="/dashboard/post/new" variant="blue" icon="plus" label="New post" %}
        </div>
    </div>
    {% else %}
    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        {% foreach ($posts as $post): %}
            {% cmp="post-card" post="{$post}" blogSlug="{$activeBlogSlug}" %}
        {% endforeach %}
    </div>

    <div class="mt-6">
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="/dashboard/post" %}
    </div>
    {% endif %}

</div>
{% endblock %}
{% block scripts %}
<script src='/cp-assets/js/tooltip.js'></script>
{% endblock %}
