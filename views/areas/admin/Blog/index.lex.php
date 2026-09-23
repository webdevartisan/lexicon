{% extends "back.lex.php" %}

{% block title %}Blogs{% endblock %}
{% block subtitle %}All blogs on the platform, with owners and content counts.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/blogs';
$hasFilters = $q !== '' || $status !== '' || $featured !== '' || $theme !== '';
$emptyTitle = $hasFilters ? 'No blogs match these filters' : 'No blogs yet';
$emptyMessage = $hasFilters ? 'Try a different name, owner, status, theme, or featured state.' : 'Create the first blog to get the site going.';

$statusChoices = ['' => 'All statuses'];
foreach ($statusOptions as $opt) {
    $statusChoices[$opt] = ucfirst($opt);
}

$featuredChoices = ['' => 'Featured: any', 'yes' => 'Featured only', 'no' => 'Not featured'];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between mb-4">
        <form method="GET" action="<?= e($basePath) ?>" data-table-filter class="flex flex-col sm:flex-row sm:items-center gap-3 grow">
            {% cmp="input" type="search" name="q" value="{$q}" placeholder="Search name, slug, or owner..." %}
            {% cmp="select" name="status" options="{$statusChoices}" selectedKey="{$status}" onchange="this.form.submit()" %}
            {% cmp="select" name="theme" options="{$themeChoices}" selectedKey="{$theme}" onchange="this.form.submit()" %}
            {% cmp="select" name="featured" options="{$featuredChoices}" selectedKey="{$featured}" onchange="this.form.submit()" %}
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
            {% cmp="btn" href="/admin/blogs/new" variant="blue" icon="plus" label="New Blog" %}
        </div>
    </div>

    <div data-table-region>
    {% if blogs|empty %}
        {% cmp="empty-state" icon="book-open" title="{$emptyTitle}" message="{$emptyMessage}" %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="id" label="ID" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="name" label="Name" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="owner" label="Owner" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="posts" label="Posts" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="team" label="Team" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="theme" label="Theme" %}
                        {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="status" label="Status" %}
                        <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($blogs as $blog): %}
                    <?php
                        // The blogs table has a `status` enum and no `is_active` column;
// reading the missing key made every row render as "Inactive".
$blogStatus = (string) ($blog['status'] ?? 'draft');
$statusLabel = ucfirst($blogStatus);
$showUrl = '/admin/blogs/'.$blog['id'].'/show';
$editUrl = '/admin/blogs/'.$blog['id'].'/edit';
$deleteUrl = '/admin/blogs/'.$blog['id'].'/delete';
$featuredOnExplore = (int) ($blog['is_featured'] ?? 0) === 1;
$featureTip = $featuredOnExplore ? 'Remove from explore featured' : 'Feature on explore page';
$blogTheme = (string) ($blog['theme'] ?? '');
// The stored key is what the filter matches on, so show that rather than the
// display name; they differ, and a mismatch here would read as a broken filter.
$themeLabel = $blogTheme !== '' ? $blogTheme : '—';
$blogSlug = (string) ($blog['blog_slug'] ?? '');
$publicUrl = $blogSlug !== '' ? lurl('/blog/'.rawurlencode($blogSlug)) : '';
?>
                    <tr class="transition-colors <?= $featuredOnExplore
                        ? 'bg-amber-50/60 hover:bg-amber-50 dark:bg-amber-500/5 dark:hover:bg-amber-500/10'
                        : 'hover:bg-slate-50/60 dark:hover:bg-zink-700/40' ?>">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) $blog['id']) ?></td>
                        <td class="px-3.5 py-2.5">
                            <?php if ($featuredOnExplore) { ?>
                            {% cache 'lucide:star-fill:row' ttl=31536000 %}<i data-lucide="star" class="size-3.5 fill-current text-amber-500 inline-block align-[-1px] mr-1"></i>{% endcache %}
                            <span class="sr-only">Featured on the explore page. </span>
                            <?php } ?>
                            <?php // A blog with no slug has no public page to open, so it stays plain text.?>
                            <?php if ($publicUrl !== '') { ?>
                                <a href="<?= e($publicUrl) ?>" target="_blank" rel="noopener"
                                   class="font-medium text-slate-900 dark:text-zink-50 hover:text-custom-500 dark:hover:text-custom-500 transition-colors">
                                    <?= e($blog['blog_name']) ?>
                                </a>
                            <?php } else { ?>
                                <span class="font-medium text-slate-900 dark:text-zink-50"><?= e($blog['blog_name']) ?></span>
                            <?php } ?>
                            <span class="block text-xs text-slate-400 dark:text-zink-300">/<?= e($blogSlug) ?></span>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e((string) ($blog['owner_name'] ?? '—')) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= (int) ($blog['post_count'] ?? 0) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= (int) ($blog['author_count'] ?? 0) ?></td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= e($themeLabel) ?></td>
                        <td class="px-3.5 py-2.5">
                            {% cmp="status-badge" status="{$blogStatus}" label="{$statusLabel}" %}
                        </td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <?php
                                $rowTitle = (string) $blog['blog_name'];
                                $isSuspended = $blogStatus === 'suspended';
                                $rowActions = [
                                    ['label' => 'View', 'icon' => 'eye', 'href' => $showUrl],
                                    ['label' => 'Edit', 'icon' => 'pencil', 'href' => $editUrl],
                                    ['label' => 'Transfer ownership', 'icon' => 'key-round', 'href' => $editUrl.'#ownership'],
                                    [
                                        'label' => $featureTip, 'icon' => 'star',
                                        'post' => buildLocalizedUrl('/admin/blogs/'.(int) $blog['id'].'/feature-explore'),
                                        'confirm' => $featuredOnExplore
                                            ? 'Take this blog off the explore page?'
                                            : 'Feature this blog on the explore page?',
                                        // featureExplore() refuses anything unpublished, so offering it
                                        // there would only ever produce an error flash.
                                        'can' => $featuredOnExplore || $blogStatus === 'published',
                                    ],
                                    [
                                        'label' => 'Publish', 'icon' => 'send',
                                        'post' => buildLocalizedUrl('/admin/blogs/'.(int) $blog['id'].'/publish'),
                                        'confirm' => 'Publish this blog?',
                                        'can' => !$isSuspended && $blogStatus !== 'published',
                                    ],
                                    [
                                        'label' => 'Move to draft', 'icon' => 'pencil-ruler',
                                        'post' => buildLocalizedUrl('/admin/blogs/'.(int) $blog['id'].'/unpublish'),
                                        'confirm' => 'Move this blog back to draft? It will no longer be publicly visible.',
                                        'can' => !$isSuspended && $blogStatus === 'published',
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
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="{$basePath}" itemSingular="blog" itemPlural="blogs" %}
    </div>
    {% endif %}
    </div>
</div>
{% endblock %}
