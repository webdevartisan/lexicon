{% extends "back.lex.php" %}

{% block title %}Posts{% endblock %}
{% block subtitle %}Every post on the site, across all blogs.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/posts';
$hasFilters = $q !== '' || $status !== '' || $featured !== '' || $visibility !== '' || $blogId > 0;
$emptyTitle = $hasFilters ? 'No posts match your filters' : 'No posts yet';
$emptyMessage = $hasFilters ? 'Try a different search, or clear the filters.' : 'Posts from every blog will appear here.';

// Built from PostModel::STATUSES so a new status can never go missing here.
$statusChoices = ['' => 'All statuses'];
foreach ($statusOptions as $opt) {
    $statusChoices[$opt] = ucfirst($opt);
}

$visibilityChoices = ['' => 'Any visibility'];
foreach ($visibilityOptions as $opt) {
    $visibilityChoices[$opt] = ucfirst($opt);
}

$featuredChoices = [
    '' => 'Featured: any',
    'home' => 'On the front page',
    'blog' => 'Featured in its blog',
    'none' => 'Not featured anywhere',
];

$blogChoices = ['' => 'All blogs'] + $blogOptions;
$selectedBlogKey = $blogId > 0 ? (string) $blogId : '';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between mb-4">
        <form method="GET" action="<?= e($basePath) ?>" data-table-filter class="flex flex-wrap items-center gap-3 grow">
            {% cmp="input" type="search" name="q" value="{$q}" placeholder="Search title or content..." %}
            {% cmp="select" name="status" options="{$statusChoices}" selectedKey="{$status}" onchange="this.form.submit()" %}
            {% cmp="select" name="blog_id" options="{$blogChoices}" selectedKey="{$selectedBlogKey}" onchange="this.form.submit()" %}
            {% cmp="select" name="featured" options="{$featuredChoices}" selectedKey="{$featured}" onchange="this.form.submit()" %}
            {% cmp="select" name="visibility" options="{$visibilityChoices}" selectedKey="{$visibility}" onchange="this.form.submit()" %}
            {% cmp="btn" type="submit" variant="blue" icon="search" label="Search" %}
            <?php /* Marked for table-sort.js: the region swap does not reach
                     into the filter form, so this is refreshed separately. */ ?>
            <span data-table-sync="filter-clear" class="contents">
            <?php if ($hasFilters) { ?>
            {% cmp="btn" href="{$basePath}" variant="slate" icon="x" label="Clear" %}
            <?php } ?>
            </span>
        </form>
        <div class="shrink-0">
            {% cmp="btn" href="/admin/posts/new" variant="blue" icon="plus" label="New Post" %}
        </div>
    </div>

    <div data-table-region>
    {% if posts|empty %}
        {% cmp="empty-state" icon="files" title="{$emptyTitle}" message="{$emptyMessage}" %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="id" label="ID" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="title" label="Title" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="blog" label="Blog" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="author" label="Author" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="status" label="Status" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="comments" label="Comments" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="updated" label="Updated" %}
                        <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($posts as $post): %}
                    <?php
                        $postStatus = (string) $post['status'];
$showUrl = '/admin/posts/'.$post['id'].'/show';
$editUrl = '/admin/posts/'.$post['id'].'/edit';
$deleteUrl = '/admin/posts/'.$post['id'].'/delete';
$featuredOnHome = (int) ($post['featured_on_home'] ?? 0) === 1;
$featureTip = $featuredOnHome ? 'Remove from front page' : 'Feature on front page';
$commentCount = (int) ($post['comment_count'] ?? 0);

// Both slugs are needed to reach the post on the front; a draft that never got
// one has no public page to open.
$postSlug = (string) ($post['slug'] ?? '');
$blogSlug = (string) ($post['blog_slug'] ?? '');
$publicUrl = $postSlug !== '' && $blogSlug !== ''
    ? lurl('/blog/'.rawurlencode($blogSlug).'/'.rawurlencode($postSlug))
    : '';
?>
                    <tr class="transition-colors <?= $featuredOnHome
                        ? 'bg-amber-50/60 hover:bg-amber-50 dark:bg-amber-500/5 dark:hover:bg-amber-500/10'
                        : 'hover:bg-slate-50/60 dark:hover:bg-zink-700/40' ?>">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) $post['id']) ?></td>
                        <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50 max-w-xs truncate">
                            <?php if ($featuredOnHome) { ?>
                            {% cache 'lucide:star-fill:row' ttl=31536000 %}<i data-lucide="star" class="size-3.5 fill-current text-amber-500 inline-block align-[-1px] mr-1"></i>{% endcache %}
                            <span class="sr-only">Featured on the front page. </span>
                            <?php } ?>
                            <?php if ($publicUrl !== '') { ?>
                                <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"
                                   class="hover:text-custom-500 dark:hover:text-custom-500 transition-colors">
                                    <?= e(truncate((string) $post['title'], 60)) ?>
                                </a>
                            <?php } else { ?>
                                <?= e(truncate((string) $post['title'], 60)) ?>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) ($post['blog_name'] ?? '—')) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) $post['author_handle']) ?></td>
                        <td class="px-3.5 py-2.5">
                            {% cmp="status-badge" status="{$postStatus}" %}
                            <?php if (!empty($post['open_case_id'])) {
                                $caseReports = (int) $post['open_case_reports'];
                                $caseLabel = $caseReports.' report'.($caseReports === 1 ? '' : 's');
                                $caseHref = $canHandleReports ? '/admin/reports/'.(int) $post['open_case_id'] : '';
                                ?>
                            <div>{% cmp="report-pill" label="{$caseLabel}" href="{$caseHref}" %}</div>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= $commentCount ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e(local_datetime($post['updated_at'] ?? null, 'M j, Y')) ?></td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <?php
                                $rowTitle = (string) $post['title'];
                                $isModerated = $postStatus === 'moderated';
                                $postVisibility = (string) ($post['visibility'] ?? 'public');
                                $rowActions = [
                                    ['label' => 'View', 'icon' => 'eye', 'href' => $showUrl],
                                    ['label' => 'Edit', 'icon' => 'pencil', 'href' => $editUrl],
                                    [
                                        'label' => $featureTip, 'icon' => 'star',
                                        'post' => buildLocalizedUrl('/admin/posts/'.(int) $post['id'].'/feature-home'),
                                        'confirm' => $featuredOnHome
                                            ? 'Take this post off the front page?'
                                            : 'Feature this post on the front page?',
                                        // featureHome() refuses anything not published and public, so
                                        // offering it there would only ever produce an error flash.
                                        'can' => $featuredOnHome || ($postStatus === 'published' && $postVisibility === 'public'),
                                    ],
                                    [
                                        'label' => 'Publish', 'icon' => 'send',
                                        'post' => buildLocalizedUrl('/admin/posts/'.(int) $post['id'].'/publish'),
                                        'confirm' => 'Publish this post?',
                                        'can' => !$isModerated && $postStatus !== 'published',
                                    ],
                                    [
                                        'label' => 'Move to draft', 'icon' => 'pencil-ruler',
                                        'post' => buildLocalizedUrl('/admin/posts/'.(int) $post['id'].'/draft'),
                                        'confirm' => 'Move this post back to draft?',
                                        'can' => !$isModerated && $postStatus !== 'draft',
                                    ],
                                    [
                                        'label' => 'Archive', 'icon' => 'archive',
                                        'post' => buildLocalizedUrl('/admin/posts/'.(int) $post['id'].'/archive'),
                                        'confirm' => 'Archive this post?',
                                        'can' => !$isModerated && $postStatus !== 'archived',
                                    ],
                                    [
                                        'label' => 'Make public', 'icon' => 'globe',
                                        'post' => buildLocalizedUrl('/admin/posts/'.(int) $post['id'].'/make-public'),
                                        'confirm' => 'Make this post public?',
                                        'can' => $postVisibility !== 'public',
                                    ],
                                    [
                                        'label' => 'Make private', 'icon' => 'lock',
                                        'post' => buildLocalizedUrl('/admin/posts/'.(int) $post['id'].'/make-private'),
                                        'confirm' => 'Make this post private? Only its blog\'s team will be able to see it.',
                                        'can' => $postVisibility !== 'private',
                                    ],
                                    [
                                        'label' => 'Unlist', 'icon' => 'eye-off',
                                        'post' => buildLocalizedUrl('/admin/posts/'.(int) $post['id'].'/unlist'),
                                        'confirm' => 'Unlist this post? It will stay reachable by direct link, but drop out of listings.',
                                        'can' => $postVisibility !== 'unlisted',
                                    ],
                                    ['label' => 'Delete', 'icon' => 'trash-2', 'href' => $deleteUrl, 'danger' => true],
                                ];
                                ?>
                                {% cmp="row-actions" title="{$rowTitle}" items="{$rowActions}" %}
                            </div>
                        </td>
                    </tr>
                    {% endforeach; %}
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="{$basePath}" itemSingular="post" itemPlural="posts" %}
    </div>
    {% endif %}
    </div>
</div>
{% endblock %}
