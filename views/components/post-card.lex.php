<?php
$post = $post ?? [];
$blogSlug = $blogSlug ?? '';

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

<div class="card flex flex-col h-full gap-2">
    <div class="card-body flex flex-col flex-1">
        <div class="flex items-start justify-between gap-2 mb-3">
            <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border <?= $badgeClass ?>">
                <?= e($badgeLabel) ?>
            </span>
            <?php if ($dateDisplay) { ?>
            <span class="text-[11px] text-slate-500 dark:text-zink-300 whitespace-nowrap">
                <?= e($dateDisplay) ?>
            </span>
            <?php } ?>
        </div>

        <h6 class="mb-2 text-15 line-clamp-2">
            <a href="/dashboard/post/{{ post.id }}/edit" class="hover:text-custom-500 transition-colors">
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

        <div class="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-zink-600">
            <form method="GET" action="/dashboard/post/{{ post.id }}/edit" class="m-0">
                <button type="submit"
                    data-tooltip="default" data-tooltip-content="{{ editTooltip }}" data-tooltip-follow-cursor="true"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-custom-500 hover:text-custom-600 transition-colors"
                    title="{{ editTooltip }}">
                    {% cache 'lucide:pencil' ttl=3600 %}<i data-lucide="pencil" class="size-4"></i>{% endcache %}
                    <span>{{ editTooltip }}</span>
                </button>
            </form>

            <div class="flex items-center gap-0.5">
                {% if ($post['status'] === 'published'): %}
                <a href="/blog/{{ blogSlug }}/{{ post.slug }}" target="_blank" rel="noopener"
                    data-tooltip="default" data-tooltip-content="{{ previewText }}" data-tooltip-follow-cursor="true"
                    class="p-2 text-slate-500 hover:text-custom-500 transition-colors rounded-md hover:bg-slate-100 dark:hover:bg-zink-600"
                    title="{{ previewText }}">
                    {% cache 'lucide:external-link' ttl=3600 %}<i data-lucide="external-link" class="size-4"></i>{% endcache %}
                </a>
                {% endif %}

                {% if (($post['status'] === 'draft') || ($post['status'] === 'archived')): %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/publish" class="m-0">
                    {{ csrf_field() }}
                    <button data-tooltip="default" data-tooltip-content="{{ publishTooltip }}" data-tooltip-follow-cursor="true" type="submit"
                        class="p-2 text-slate-500 hover:text-purple-600 transition-colors rounded-md hover:bg-slate-100 dark:hover:bg-zink-600"
                        title="{{ publishTooltip }}">
                        {% cache 'lucide:send' ttl=3600 %}<i data-lucide="send" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}

                {% if (($post['status'] === 'archived') || ($post['status'] === 'published')): %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/draft" class="m-0">
                    {{ csrf_field() }}
                    <button data-tooltip="default" data-tooltip-content="{{ draftTooltip }}" data-tooltip-follow-cursor="true" type="submit"
                        class="p-2 text-slate-500 hover:text-purple-600 transition-colors rounded-md hover:bg-slate-100 dark:hover:bg-zink-600"
                        title="{{ draftTooltip }}">
                        {% cache 'lucide:pencil-ruler' ttl=3600 %}<i data-lucide="pencil-ruler" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}

                {% if ($post['status'] !== 'archived'): %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/archive" class="m-0">
                    {{ csrf_field() }}
                    <button data-tooltip="default" data-tooltip-content="{{ archiveTooltip }}" data-tooltip-follow-cursor="true" type="submit"
                        class="p-2 text-slate-500 hover:text-orange-600 transition-colors rounded-md hover:bg-slate-100 dark:hover:bg-zink-600"
                        title="{{ archiveTooltip }}">
                        {% cache 'lucide:archive' ttl=3600 %}<i data-lucide="archive" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}

                <span class="mx-1 h-4 w-px bg-slate-200 dark:bg-zink-500" aria-hidden="true"></span>

                <form method="POST" action="/dashboard/post/{{ post.id }}/delete" class="m-0"
                    onsubmit="return confirm('Permanently delete this post? This cannot be undone.');">
                    {{ csrf_field() }}
                    <button data-tooltip="default" data-tooltip-content="{{ deleteTooltip }}" data-tooltip-follow-cursor="true" type="submit"
                        class="p-2 text-slate-500 hover:text-red-600 transition-colors rounded-md hover:bg-red-50 dark:hover:bg-red-900/30"
                        title="{{ deleteTooltip }}">
                        {% cache 'lucide:trash-2' ttl=3600 %}<i data-lucide="trash-2" class="size-4"></i>{% endcache %}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
