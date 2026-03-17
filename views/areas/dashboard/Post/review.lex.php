{% extends "back.lex.php" %}

{% block title %}Review Post · {{ post.title }}{% endblock %}
{% block subtitle %}{% endblock %}
{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
{% endblock %}
{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Main form -->
        <div class="lg:col-span-2">
            <!-- Content -->
            <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
                <div class="p-4 border-b border-slate-200 dark:border-zink-600">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Content</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
                        Title, slug, summary, and the main body of the post.
                    </p>
                </div>
                <div class="p-4 space-y-6 md:p-5">
                    <!-- Title -->
                    <?php $title = $post['title'] ?? ''; ?>
                    {% cmp="input" type="text" label="title" value="{$title}" disabled="true" %}

                    <!-- Slug -->
                    <?php $slug = $post['slug'] ?? ''; ?>
                    {% cmp="input" type="text" label="slug" value="{$slug}" prefix="/" disabled="true"
                    underlabel="Cannot be changed."%}

                    <!-- Excerpt / summary -->
                    <?php $excerpt = $post['excerpt'] ?? ''; ?>
                    {% cmp="input" type="textarea" label="excerpt" value="{$excerpt}" rows="3" disabled="true"
                    placeholder="Optional short summary used in listings and meta description when not set
                    explicitly." %}

                    <!-- Body -->
                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-4 text-15">Content</h6>
                            <div data-simplebar="" data-simplebar-auto-hide="false" style="max-height: 220px;"
                                class="pr-2 text-slate-500 dark:text-zink-200">
                                {{ post.content|raw }}
                            </div>
                        </div>
                    </div><!--end card-->

                    <!-- actions -->
                    <div class="flex gap-2 justify-between pt-2 pb-2">
                        {% cmp="btn" href="/dashboard" variant="slate" icon="step-back" label="Go Back" %}
                        {% cmp="btn" type="button" variant="blue" icon="fullscreen" 
                        label="Preview" dataBtn="data-modal-target='fullScreenModal'" %}
                        
                    </div>
            </section>
        </div>

        <!-- side panel -->
        <aside class="space-y-6">
            <!-- status -->
            <section
                class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
                <div class="p-4 border-b border-slate-200 dark:border-zink-600">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Status</h2>
                </div>

                <div class="p-4 space-y-3 text-xs text-slate-600 dark:text-zink-200">
                    <?php
                    $status = $status ?? ($post['status'] ?? 'draft');
                    $wf = $workflowState ?? ($post['workflow_state'] ?? 'draft');
                    $role = $blogRole ?? 'none';

                    // Tailwind “badge” base (pill style).
                    // Based on common Tailwind badge patterns: rounded-full + small padding + small text.
                    $badgeBase = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';

                    // Status badge color mapping
                    $statusBadge = match ($status) {
                        'published' => $badgeBase.' bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/30',
                        'archived' => $badgeBase.' bg-slate-200 text-slate-800 ring-slate-300 dark:bg-zink-900/40 dark:text-zink-100 dark:ring-zink-600/40',
                        default => $badgeBase.' bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zink-600/30 dark:text-zink-100 dark:ring-zink-500/30',
                    };

                    // Workflow badge color mapping
                    $wfBadge = match ($wf) {
                        'idea' => $badgeBase.' bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zink-600/30 dark:text-zink-100 dark:ring-zink-500/30',
                        'draft' => $badgeBase.' bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zink-600/30 dark:text-zink-100 dark:ring-zink-500/30',
                        'in_review' => $badgeBase.' bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-200 dark:ring-sky-500/30',
                        'needs_changes' => $badgeBase.' bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-500/30',
                        'approved' => $badgeBase.' bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/30',
                        'ready_to_publish' => $badgeBase.' bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/20 dark:text-indigo-200 dark:ring-indigo-500/30',
                        default => $badgeBase.' bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zink-600/30 dark:text-zink-100 dark:ring-zink-500/30',
                    };

                    // Role badge (simple “info” style)
                    $roleBadge = $badgeBase.' bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-200 dark:ring-sky-500/30';
                    ?>

                    <p class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-zink-300">Visibility</span>
                        <span class="<?= $statusBadge ?>">
                            <?= e($status) ?>
                        </span>
                    </p>

                    <p class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-zink-300">Workflow state</span>
                        <span class="<?= $wfBadge ?>">
                            <?= e(str_replace('_', ' ', $wf)) ?>
                        </span>
                    </p>

                    <div class="pt-3 mt-1 border-t border-dashed border-slate-200 dark:border-zink-600 space-y-2">
                        <p class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-zink-300">Your role on this blog</span>
                            <span class="<?= $roleBadge ?>">
                                <?= e($role) ?>
                            </span>
                        </p>
                    </div>
                </div>
            </section>
            <!-- Workflow -->
            <section
                class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
                <div class="p-4 border-b border-slate-200 dark:border-zink-600">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Workflow actions</h2>
                </div>
                <div class="p-4 space-y-3 text-xs text-slate-600 dark:text-zink-200">
                    <p>
                        Use these actions to move the post through the editorial workflow.
                        All actions are still enforced server-side by your authorization policies.
                    </p>

                    <div class="pt-3 mt-1 border-t border-dashed border-slate-200 dark:border-zink-600 space-y-2">

                        {# if canRequestReview #}
                        <form method="post" action="/dashboard/posts/{{ post.id }}/workflow/request-review">
                            {{ csrf_field() }}
                            {% cmp="btn" type="submit" variant="sky" icon="send" label="Request review" %}
                        </form>
                        {# endif #}

                        {# if canMarkNeedsChanges #}
                        <form method="post" action="/dashboard/posts/{{ post.id }}/workflow/needs-changes" class="mt-1">
                            {{ csrf_field() }}
                            {% cmp="btn" type="submit" variant="yellow" icon="pen-line" label="Mark as needs changes" %}
                        </form>
                        {# endif #}

                        {# if canApprove #}
                        <form method="post" action="/dashboard/posts/{{ post.id }}/workflow/approve" class="mt-1">
                            {{ csrf_field() }}
                            {% cmp="btn" type="submit" variant="green" icon="check" label="Approve" %}
                        </form>
                        {# endif #}

                        {# if canPublish #}
                        <form method="post" action="/dashboard/posts/{{ post.id }}/publish" class="mt-1">
                            {{ csrf_field() }}
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <input type="hidden" name="blog_id" value="<?= $blog['id'] ?>">
                            {% cmp="btn" type="submit" variant="slate" icon="megaphone" label="Publish now" %}
                        </form>
                        {# endif #}

                        {# if canPublish|empty #}
                        <form method="post" action="/dashboard/posts/{{ post.id }}/unpublish" class="mt-1">
                            {{ csrf_field() }}
                            <input type="hidden" name="post_id" value="<?= $post['id'] ?>">
                            <input type="hidden" name="blog_id" value="<?= $blog['id'] ?>">
                            {% cmp="btn" type="submit" variant="slate" icon="megaphone-off" label="Unpublish" %}
                        </form>
                        {# endif #}

                        {# if canDelete|notempty #}
                        <form method="post" action="/dashboard/posts/{{ post.id }}/delete"
                            onsubmit="return confirm('Move this post to trash?');" class="mt-1">
                            <input type="hidden" name="_method" value="DELETE">
                            {{ csrf_field() }}
                            {% cmp="btn" type="submit" variant="red" icon="trash-2" label="Move to trash" %}
                        </form>
                        {# endif #}
                    </div>
                </div>
            </section>
            <!-- Editing tips -->
            <section
                class="p-4 text-[11px] bg-slate-50 border border-dashed border-slate-200 rounded-lg dark:bg-zink-800 dark:border-zink-600 dark:text-zink-200">
                {# Dev note: should reuse a generic “editor tips” partial here to keep guidance text consistent across content forms. #}
                <h3 class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-100">Editing tips</h3>
                <p>
                    Keep paragraphs short and use headings for structure. Add a clear excerpt and meta description so
                    posts look good in listings and search results.
                </p>
            </section>
        </aside>
    </div>
</div>

<div id="fullScreenModal" 
    modal-center=""
    class="fixed !inset-0 flex flex-col hidden transition-all duration-300 ease-in-out z-drawer show"
>
    <div class="flex flex-col w-full h-full md:w-4/5 md:h-4/5 md:max-w-4xl md:max-h-[90vh] md:rounded-xl md:shadow-2xl bg-white dark:bg-zink-600 overflow-hidden mx-auto my-auto">
        <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-zink-500">
            <h5 class="text-16 font-semibold">Preview: {{ post.title }}</h5>
            <button 
                data-modal-close="fullScreenModal"
                class="transition-all duration-200 ease-linear text-slate-500 hover:text-red-500 dark:text-zink-200 dark:hover:text-red-500"
                ><i data-lucide="x" class="size-5"></i>
            </button>
        </div>

        <div class="p-4 flex-1 min-h-0" data-simplebar data-simplebar-auto-hide="false">
            {{ post.content|raw }}
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between p-4 mt-auto border-t border-slate-200 dark:border-zink-500">

            <p class="text-sm text-slate-500 dark:text-zink-300"> 
                Last updated: {{ post.updated_at }} 
            </p> 
            <!-- Right side: actions -->
            <div class="flex items-center gap-2 relative"> 
                {% cmp="btn" type="button" variant="blue" icon="x" label="Close" dataBtn="data-modal-close='fullScreenModal'" %}
            </div>

        </div>
    </div>
</div>
{% endblock %}
{% block scripts %}
<script src="/cp-assets/js/modal.js"></script>
{% endblock %}