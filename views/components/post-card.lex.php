<?php
$post = $post ?? [];
$blogSlug = $blogSlug ?? '';
$returnToken = $returnToken ?: '';

$status = $post['status'] ?? 'draft';
$statusStyles = [
    'published' => ['bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800', 'Published'],
    'draft' => ['bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-800 dark:text-zink-100 dark:border-zink-600', 'Draft'],
    'pending' => ['bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800', 'Pending'],
    'archived' => ['bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100', 'Archived'],
];
[$badgeClass, $badgeLabel] = $statusStyles[$status] ?? $statusStyles['draft'];

$dateRaw = $post['published_at'] ?? $post['updated_at'] ?? $post['created_at'] ?? '';
$dateDisplay = $dateRaw ? date('M j, Y', strtotime((string) $dateRaw)) : '';
?>
{% set previewText = t('components.postCard.actions.preview') %}
{% set editTooltip = t('components.postCard.tooltips.edit') %}
{% set publishTooltip = t('components.postCard.tooltips.publish') %}
{% set draftTooltip = t('components.postCard.tooltips.draft') %}
{% set archiveTooltip = t('components.postCard.tooltips.archive') %}
{% set deleteTooltip = t('components.postCard.tooltips.delete') %}

<div class="card flex flex-col h-full gap-2 transition border border-slate-200 dark:border-zink-600 rounded-lg peer-checked:border-custom-500 peer-checked:bg-slate-100 dark:peer-checked:bg-zink-800/60">
    <div class="card-body flex flex-col flex-1">
        <div class="flex items-start justify-between gap-2 mb-3">
            <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border <?= $badgeClass ?>">
                <?= e($badgeLabel) ?>
            </span>

        <?php
        // Reviewer chip — only meaningful while a post is in the review pipeline.
        if ($status === 'pending') {
            if (!empty($post['reviewer_username'])) {
                $claimedAgo = !empty($post['reviewer_assigned_at'])
                    ? date('M j', strtotime((string) $post['reviewer_assigned_at']))
                    : '';
                ?>
                <div class="mb-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full bg-sky-50 text-sky-700 border border-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:border-sky-500/30"
                          title="Currently under review">
                        {% cache 'lucide:lock:sm' ttl=31536000 %}<i data-lucide="lock" class="size-3"></i>{% endcache %}
                        Reviewed by <?= e($post['reviewer_username']) ?><?= $claimedAgo ? ' · '.e($claimedAgo) : '' ?>
                    </span>
                </div>
                <?php
            } else {
                ?>
                <div class="mb-2">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium rounded-full bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30"
                          title="No reviewer has claimed this post yet">
                        {% cache 'lucide:user-plus:sm' ttl=31536000 %}<i data-lucide="user-plus" class="size-3"></i>{% endcache %}
                        Awaiting reviewer
                    </span>
                </div>
                <?php
            }
        }
?>
            <?php if ($dateDisplay) { ?>
            <span class="text-[11px] text-slate-500 dark:text-zink-300 whitespace-nowrap">
                <?= e($dateDisplay) ?>
            </span>
            <?php } ?>
        </div>

        <h6 class="mb-2 text-15 line-clamp-2">
            <a href="/dashboard/post/{{ post.id }}/edit?r=<?= urlencode($returnToken) ?>" class="hover:text-custom-500 transition-colors">
                {{ post.title }}
            </a>
        </h6>
        <p class="text-slate-500 dark:text-zink-200 line-clamp-3 text-sm flex-1">
            {% if post.excerpt|notempty %}
              <?= e(truncate($post['excerpt'], 120)) ?>
            {% elseif post.content|isset %}
              <?= e(truncate(strip_tags($post['content']), 120)) ?>
            {% endif %}
        </p>

        <div class="flex items-center flex-wrap gap-x-3 gap-y-1 mt-3 text-[11px] text-slate-500 dark:text-zink-300">
            <?php if (!empty($post['category_name'])) { ?>
            <span class="inline-flex items-center gap-1">
                {% cache 'lucide:folder:sm' ttl=31536000 %}<i data-lucide="folder" class="size-3"></i>{% endcache %}
                <?= e((string) $post['category_name']) ?>
            </span>
            <?php } ?>
            <?php if (isset($post['comment_count'])) { ?>
            <span class="inline-flex items-center gap-1" title="<?= (int) $post['comment_count'] ?> comments">
                {% cache 'lucide:message-square:sm' ttl=31536000 %}<i data-lucide="message-square" class="size-3"></i>{% endcache %}
                <?= (int) $post['comment_count'] ?>
            </span>
            <?php } ?>
            <?php if (!empty($post['blog_name'])) { ?>
            <span class="inline-flex items-center gap-1">
                {% cache 'lucide:book-open:sm' ttl=31536000 %}<i data-lucide="book-open" class="size-3"></i>{% endcache %}
                <?= e((string) $post['blog_name']) ?>
            </span>
            <?php } ?>
            <?php if (!empty($post['tags'])) { ?>
                <?php foreach (array_slice($post['tags'], 0, 3) as $tg) { ?>
                <a href="/dashboard/post?tag=<?= (int) $tg['id'] ?>&blog_id=<?= (int) ($post['blog_id'] ?? 0) ?>"
                   class="inline-flex items-center gap-1 hover:text-custom-500 transition-colors">
                    {% cache 'lucide:tag:sm' ttl=31536000 %}<i data-lucide="tag" class="size-3"></i>{% endcache %}
                    <?= e((string) $tg['name']) ?>
                </a>
                <?php } ?>
            <?php } ?>
        </div>

        <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-zink-600">
            <form method="GET" action="/dashboard/post/{{ post.id }}/edit?r=<?= urlencode($returnToken) ?>" class="m-0">
                <button type="submit"
                    data-tooltip="default" data-tooltip-content="{{ editTooltip }}" data-tooltip-follow-cursor="true"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-custom-500 hover:text-custom-600 transition-colors"
                    title="{{ editTooltip }}">
                    {% cache 'lucide:pencil' ttl=31536000 %}<i data-lucide="pencil" class="size-4"></i>{% endcache %}
                    <span>{{ editTooltip }}</span>
                </button>
            </form>

            <div class="flex items-center gap-0.5">
                {% if ($post['status'] === 'published'): %}
                <a href="/blog/{{ blogSlug }}/{{ post.slug }}" target="_blank" rel="noopener"
                    data-tooltip="default" data-tooltip-content="{{ previewText }}" data-tooltip-follow-cursor="true"
                    class="p-2 text-slate-500 hover:text-custom-500 transition-colors rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500"
                    title="{{ previewText }}">
                    {% cache 'lucide:external-link' ttl=31536000 %}<i data-lucide="external-link" class="size-4"></i>{% endcache %}
                </a>
                {% endif %}

                {% if (($post['status'] === 'draft') || ($post['status'] === 'archived')): %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/publish" class="m-0">
                    {{ csrf_field() }}
                    <button data-tooltip="default" data-tooltip-content="{{ publishTooltip }}" data-tooltip-follow-cursor="true" type="submit"
                        class="p-2 text-slate-500 hover:text-purple-600 transition-colors rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500"
                        title="{{ publishTooltip }}">
                        {% cache 'lucide:send' ttl=31536000 %}<i data-lucide="send" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}

                {% if (($post['status'] === 'archived') || ($post['status'] === 'published')): %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/draft" class="m-0">
                    {{ csrf_field() }}
                    <button data-tooltip="default" data-tooltip-content="{{ draftTooltip }}" data-tooltip-follow-cursor="true" type="submit"
                        class="p-2 text-slate-500 hover:text-purple-600 transition-colors rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-purple-500"
                        title="{{ draftTooltip }}">
                        {% cache 'lucide:pencil-ruler' ttl=31536000 %}<i data-lucide="pencil-ruler" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}

                {% if ($post['status'] !== 'archived'): %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/archive" class="m-0">
                    {{ csrf_field() }}
                    <button data-tooltip="default" data-tooltip-content="{{ archiveTooltip }}" data-tooltip-follow-cursor="true" type="submit"
                        class="p-2 text-slate-500 hover:text-orange-600 transition-colors rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500"
                        title="{{ archiveTooltip }}">
                        {% cache 'lucide:archive' ttl=31536000 %}<i data-lucide="archive" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}

                <form method="POST" action="/dashboard/post/{{ post.id }}/feature" class="m-0">
                    {{ csrf_field() }}
                    <button type="submit"
                        data-tooltip="default" data-tooltip-content="<?= !empty($post['is_featured']) ? 'Unfeature' : 'Feature on homepage' ?>" data-tooltip-follow-cursor="true"
                        class="p-2 transition-colors rounded-md hover:bg-slate-100 dark:hover:bg-zink-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 <?= !empty($post['is_featured']) ? 'text-amber-500' : 'text-slate-500 hover:text-amber-500' ?>"
                        title="<?= !empty($post['is_featured']) ? 'Unfeature' : 'Feature on homepage' ?>">
                        <?php $starKey = !empty($post['is_featured']) ? 'lucide:star:filled' : 'lucide:star:outline'; ?>
                        {% cache $starKey ttl=31536000 %}<i data-lucide="star" class="size-4 <?= !empty($post['is_featured']) ? 'fill-amber-400' : '' ?>"></i>{% endcache %}
                    </button>
                </form>

                <span class="mx-1 h-4 w-px bg-slate-200 dark:bg-zink-500" aria-hidden="true"></span>

                <form method="POST" action="/dashboard/post/{{ post.id }}/delete" class="m-0"
                    onsubmit="return confirm('Permanently delete this post? This cannot be undone.');">
                    {{ csrf_field() }}
                    <button data-tooltip="default" data-tooltip-content="{{ deleteTooltip }}" data-tooltip-follow-cursor="true" type="submit"
                        class="p-2 text-slate-500 hover:text-red-600 transition-colors rounded-md hover:bg-red-50 dark:hover:bg-red-900/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                        title="{{ deleteTooltip }}">
                        {% cache 'lucide:trash-2' ttl=31536000 %}<i data-lucide="trash-2" class="size-4"></i>{% endcache %}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
