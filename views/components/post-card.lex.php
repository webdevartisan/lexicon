<?php
$post = $post ?? [];
$blogSlug = $blogSlug ?? '';
?>
{% set previewText = t('components.postCard.actions.preview') %}
{% set editTooltip = t('components.postCard.tooltips.edit') %}
{% set publishTooltip = t('components.postCard.tooltips.publish') %}
{% set draftTooltip = t('components.postCard.tooltips.draft') %}
{% set archiveTooltip = t('components.postCard.tooltips.archive') %}
{% set deleteTooltip = t('components.postCard.tooltips.delete') %}

<div class="card flex flex-col h-full gap-2">
    <div class="card-body flex flex-col flex-1">
        <h6 class="mb-4 text-15">
            {{ post.title }}
        </h6>
        <p class="text-slate-500 dark:text-zink-200">
            {% if post.content|isset %}
              <?= truncate(strip_tags($post['content']), 100) ?>
            {% endif %}
        </p>
        <div class="flex items-center justify-between mt-auto">
            <a class="inline-flex items-center gap-2 text-sm font-medium transition-all duration-200 ease-linear text-custom-500 hover:text-custom-600"
                href="/blog/{{ blogSlug }}/{{ post.slug }}"
                target="_blank">
                {{ previewText }} {% cache 'lucide:external-link' ttl=3600 %}<i data-lucide="external-link" class="inline-block size-4"></i>{% endcache %}
            </a>
            <div class="flex items-center">
                {% if ($post['status'] !== 'archived'): %}
                <form method="GET" action="/dashboard/post/{{ post.id }}/edit">
                    <button data-tooltip="default" data-tooltip-content="{{ editTooltip }}" data-tooltip-follow-cursor="true" type="submit" class="p-1.5 text-slate-500 hover:text-green-600 transition-colors" title="{{ editTooltip }}">
                        {% cache 'lucide:pencil' ttl=3600 %}<i data-lucide="pencil" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}
                {% if (($post['status'] === 'draft') || ($post['status'] === 'archived')): %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/publish">
                    {{ csrf_field() }}
                    <input type="hidden" name="post_id" value="{{ post.id }}">
                    <button data-tooltip="default" data-tooltip-content="{{ publishTooltip }}" data-tooltip-follow-cursor="true" type="submit" class="p-1.5 text-slate-500 hover:text-purple-600 transition-colors" title="{{ publishTooltip }}">
                        {% cache 'lucide:send' ttl=3600 %}<i data-lucide="send" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}
                {% if (($post['status'] === 'archived') || ($post['status'] === 'published')): %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/draft">
                    {{ csrf_field() }}
                    <input type="hidden" name="post_id" value="{{ post.id }}">
                    <button data-tooltip="default" data-tooltip-content="{{ draftTooltip }}" data-tooltip-follow-cursor="true" type="submit" class="p-1.5 text-slate-500 hover:text-purple-600 transition-colors" title="{{ draftTooltip }}">
                        {% cache 'lucide:pencil-ruler' ttl=3600 %}<i data-lucide="pencil-ruler" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}
                {% if ($post['status'] !== 'archived'): %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/archive">
                    {{ csrf_field() }}
                    <input type="hidden" name="post_id" value="{{ post.id }}">
                    <button data-tooltip="default" data-tooltip-content="{{ archiveTooltip }}" data-tooltip-follow-cursor="true" type="submit" class="p-1.5 text-slate-500 hover:text-orange-600 transition-colors" title="{{ archiveTooltip }}">
                        {% cache 'lucide:archive' ttl=3600 %}<i data-lucide="archive" class="size-4"></i>{% endcache %}
                    </button>
                </form>
                {% endif %}
                <form method="POST" action="/dashboard/post/{{ post.id }}/delete">
                    {{ csrf_field() }}
                    <input type="hidden" name="post_id" value="{{ post.id }}">
                    <button data-tooltip="default" data-tooltip-content="{{ deleteTooltip }}" data-tooltip-follow-cursor="true" type="submit" class="p-1.5 text-slate-500 hover:text-red-600 transition-colors" title="{{ deleteTooltip }}">
                        {% cache 'lucide:trash-2' ttl=3600 %}<i data-lucide="trash-2" class="size-4"></i>{% endcache %}
                    </button>
                </form>
            </div>
            
        </div>
    </div>
</div>