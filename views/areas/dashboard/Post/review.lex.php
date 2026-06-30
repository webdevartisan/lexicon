{% extends "back.lex.php" %}

{% block title %}Review Post · {{ post.title }}{% endblock %}
{% block subtitle %}{% endblock %}
{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
{% endblock %}
{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Main content (read-only) -->
        <div class="lg:col-span-2">
            <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
                <div class="p-4 border-b border-slate-200 dark:border-zink-600">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Content</h2>
                    <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
                        Title, slug, summary, and the main body of the post.
                    </p>
                </div>
                <div class="p-4 space-y-6 md:p-5">
                    <?php $title = $post['title'] ?? ''; ?>
                    {% cmp="input" type="text" label="title" value="{$title}" disabled="true" %}

                    <?php $slug = $post['slug'] ?? ''; ?>
                    {% cmp="input" type="text" label="slug" value="{$slug}" prefix="/" disabled="true"
                    underlabel="Cannot be changed." %}

                    <?php $excerpt = $post['excerpt'] ?? ''; ?>
                    {% cmp="input" type="textarea" label="excerpt" value="{$excerpt}" rows="3" disabled="true"
                    placeholder="Optional short summary used in listings and meta description." %}

                    <div class="card">
                        <div class="card-body">
                            <h6 class="mb-4 text-15">Content</h6>
                            <div data-simplebar="" data-simplebar-auto-hide="false" style="max-height: 220px;"
                                class="pr-2 text-slate-500 dark:text-zink-200">
                                {{ post.content|raw }}
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 justify-between pt-2 pb-2">
                        {% cmp="btn" href="/dashboard" variant="slate" icon="step-back" label="Go Back" %}
                        {% cmp="btn" type="button" variant="blue" icon="fullscreen"
                        label="Preview" dataBtn="data-modal-target='fullScreenModal'" %}
                    </div>
                </div>
            </section>

            <!-- Review history -->
            <?php if (!empty($reviews)) { ?>
            <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
                <div class="p-4 border-b border-slate-200 dark:border-zink-600">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Review history</h2>
                </div>
                <div class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($reviews as $rev) { ?>
                    <?php
                        $decisionClass = match ($rev['decision'] ?? '') {
                            'approved' => 'text-emerald-600 dark:text-emerald-400',
                            'needs_revision' => 'text-amber-600 dark:text-amber-400',
                            'rejected' => 'text-red-600 dark:text-red-400',
                            default => 'text-slate-500 dark:text-zink-300',
                        };
                        $decisionLabel = match ($rev['decision'] ?? '') {
                            'approved' => 'Approved',
                            'needs_revision' => 'Needs changes',
                            'rejected' => 'Rejected',
                            default => ucfirst((string) ($rev['decision'] ?? 'Pending')),
                        };
                        ?>
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-slate-700 dark:text-zink-100">
                                <?= e($rev['reviewer_username'] ?? 'Unknown') ?>
                            </span>
                            <span class="text-[11px] font-semibold <?= $decisionClass ?>">
                                <?= e($decisionLabel) ?>
                            </span>
                        </div>
                        <?php if (!empty($rev['feedback'])) { ?>
                        <p class="text-xs text-slate-500 dark:text-zink-300 mt-1">
                            <?= e($rev['feedback']) ?>
                        </p>
                        <?php } ?>
                        <p class="text-[10px] text-slate-400 dark:text-zink-400 mt-1">
                            <?= e(date('M j, Y g:ia', strtotime((string) ($rev['reviewed_at'] ?? 'now')))) ?>
                        </p>
                    </div>
                    <?php } ?>
                </div>
            </section>
            <?php } ?>
        </div>

        <!-- Sidebar -->
        <aside class="space-y-6">
            <!-- Status panel -->
            <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
                <div class="p-4 border-b border-slate-200 dark:border-zink-600">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Status</h2>
                </div>
                <div class="p-4 space-y-3 text-xs text-slate-600 dark:text-zink-200">
                    <?php
                        $status = $status ?? ($post['status'] ?? 'draft');
                    $wf = $workflowState ?? ($post['workflow_state'] ?? 'draft');
                    $role = $blogRole ?? 'none';

                    $badgeBase = 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset';

                    $statusBadge = match ($status) {
                        'published' => $badgeBase.' bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/30',
                        'archived' => $badgeBase.' bg-slate-200 text-slate-800 ring-slate-300 dark:bg-zink-900/40 dark:text-zink-100 dark:ring-zink-600/40',
                        default => $badgeBase.' bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zink-600/30 dark:text-zink-100 dark:ring-zink-500/30',
                    };

                    $wfBadge = match ($wf) {
                        'draft' => $badgeBase.' bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zink-600/30 dark:text-zink-100 dark:ring-zink-500/30',
                        'in_review' => $badgeBase.' bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-200 dark:ring-sky-500/30',
                        'needs_changes' => $badgeBase.' bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/20 dark:text-amber-200 dark:ring-amber-500/30',
                        'approved' => $badgeBase.' bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/20 dark:text-emerald-200 dark:ring-emerald-500/30',
                        default => $badgeBase.' bg-slate-100 text-slate-700 ring-slate-200 dark:bg-zink-600/30 dark:text-zink-100 dark:ring-zink-500/30',
                    };

                    $roleBadge = $badgeBase.' bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/20 dark:text-sky-200 dark:ring-sky-500/30';
                    ?>

                    <p class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-zink-300">Visibility</span>
                        <span class="<?= $statusBadge ?>"><?= e($status) ?></span>
                    </p>
                    <p class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-zink-300">Workflow state</span>
                        <span class="<?= $wfBadge ?>"><?= e(str_replace('_', ' ', $wf)) ?></span>
                    </p>
                    <div class="pt-3 mt-1 border-t border-dashed border-slate-200 dark:border-zink-600">
                        <p class="flex items-center justify-between gap-3">
                            <span class="text-slate-500 dark:text-zink-300">Your role</span>
                            <span class="<?= $roleBadge ?>"><?= e($role) ?></span>
                        </p>
                    </div>
                </div>
            </section>

            <?php if (!empty($reviewerLocked)) { ?>
            <!-- Lock screen: another reviewer has claimed this post. Read-only for reviewers; editors/owners can still act. -->
            <section class="bg-amber-50 border border-amber-200 rounded-lg dark:bg-amber-500/10 dark:border-amber-500/30">
                <div class="p-4 flex items-start gap-3">
                    <i data-lucide="lock" class="size-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5"></i>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-amber-800 dark:text-amber-200">Being reviewed by <?= e($lockedByReviewer ?? 'another reviewer') ?></h3>
                        <p class="text-xs text-amber-700 dark:text-amber-300">
                            Only one reviewer works on a post at a time.
                            <?php if (!empty($lockedAt)) { ?>
                                Claimed <?= e(date('M j, g:ia', strtotime((string) $lockedAt))) ?>.
                            <?php } ?>
                        </p>
                        <p class="text-[11px] text-amber-600 dark:text-amber-400 italic">
                            You'll be notified when this becomes available again.
                        </p>
                    </div>
                </div>
            </section>
            <?php } ?>

            <!-- Workflow actions -->
            <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
                <div class="p-4 border-b border-slate-200 dark:border-zink-600">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Workflow actions</h2>
                </div>
                <div class="p-4 space-y-2"><?php /* canRequestReview is no longer surfaced — pending status auto-triggers the review pipeline. */ ?>

                    <form method="post" action="/dashboard/posts/<?= (int) $post['id'] ?>/workflow/review-decision">
                        {{ csrf_field() }}

                        {% cmp="input" type="textarea" rows="2" label="feedback" placeholder="Optional feedback for the author…" %}

                        <?php if ($canMarkNeedsChanges) { ?>
                            {% cmp="btn" type="submit" variant="yellow" name="decision" value="needs_changes" icon="pen-line" label="Needs changes" %}
                        <?php } ?>

                        <?php if ($canApprove) { ?>
                            {% cmp="btn" type="submit" variant="green" name="decision" value="approved" icon="check" label="Approve" %}
                        <?php } ?>
                    </form>

                    
                    <?php if ($canPublish) { ?>
                    <form method="post" action="/dashboard/posts/<?= (int) $post['id'] ?>/publish">
                        {{ csrf_field() }}
                        <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                        <input type="hidden" name="blog_id" value="<?= (int) ($blog['id'] ?? 0) ?>">
                        {% cmp="btn" type="submit" variant="slate" icon="megaphone" label="Publish now" %}
                    </form>
                    <?php } ?>

                    <?php if (!empty($post['status']) && $post['status'] === 'published') { ?>
                    <form method="post" action="/dashboard/posts/<?= (int) $post['id'] ?>/unpublish">
                        {{ csrf_field() }}
                        <input type="hidden" name="post_id" value="<?= (int) $post['id'] ?>">
                        <input type="hidden" name="blog_id" value="<?= (int) ($blog['id'] ?? 0) ?>">
                        {% cmp="btn" type="submit" variant="slate" icon="megaphone-off" label="Unpublish" %}
                    </form>
                    <?php } ?>

                    <?php if ($canResetToDraft) { ?>
                    <form method="post" action="/dashboard/posts/<?= (int) $post['id'] ?>/workflow/reset">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="slate" icon="rotate-ccw" label="Reset to draft" %}
                    </form>
                    <?php } ?>

                </div>
            </section>

            <!-- Reviewer assignment -->
            <?php if (!empty($reviewers) || $canAssignReviewer || !empty($canSelfAssign)) { ?>
            <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
                <div class="p-4 border-b border-slate-200 dark:border-zink-600">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Reviewer</h2>
                </div>
                <div class="p-4 space-y-3 text-xs">
                    <?php if (!empty($reviewers)) { ?>
                    <div class="space-y-1">
                        <?php foreach ($reviewers as $r) {
                            $rid = (int) ($r['reviewer_id'] ?? 0);
                            $isSelf = $rid === (int) ($currentUserId ?? 0);
                            $canUnassignThis = $isSelf
                                || in_array($blogRole, ['owner', 'editor'], true)
                                || !empty($isAdmin);
                            $unassignLabel = $isSelf ? 'Release' : 'Unassign';
                        ?>
                        <div class="flex items-center justify-between gap-2 text-slate-600 dark:text-zink-200">
                            <div class="flex items-center gap-2 min-w-0">
                                <i data-lucide="user-check" class="size-3.5 text-emerald-500 shrink-0"></i>
                                <span class="truncate"><?= e($r['reviewer_username'] ?? 'Unknown') ?></span>
                            </div>
                            <?php if ($canUnassignThis) { ?>
                            <form method="post" action="/dashboard/posts/<?= (int) $post['id'] ?>/workflow/unassign-reviewer" class="m-0 shrink-0"
                                  onsubmit="return confirm('<?= e($isSelf ? 'Release this post back to the review queue?' : 'Unassign this reviewer?') ?>');">
                                {{ csrf_field() }}
                                <input type="hidden" name="reviewer_id" value="<?= $rid ?>">
                                <button type="submit" class="text-[11px] font-medium text-slate-500 hover:text-red-500 inline-flex items-center gap-1">
                                    <i data-lucide="<?= $isSelf ? 'log-out' : 'user-minus' ?>" class="size-3"></i>
                                    <?= e($unassignLabel) ?>
                                </button>
                            </form>
                            <?php } ?>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } else { ?>
                    <p class="text-slate-400 dark:text-zink-400 italic">No reviewer assigned yet.</p>
                    <?php } ?>

                    <?php if (!empty($canSelfAssign)) { ?>
                    <!-- Reviewer self-assign: no dropdown, just a single button -->
                    <form method="post" action="/dashboard/posts/<?= (int) $post['id'] ?>/workflow/assign-reviewer"
                          class="pt-2 border-t border-dashed border-slate-200 dark:border-zink-600">
                        {{ csrf_field() }}
                        <input type="hidden" name="reviewer_id" value="<?= (int) ($currentUserId ?? 0) ?>">
                        {% cmp="btn" type="submit" variant="sky" icon="user-plus" label="Assign myself as reviewer" %}
                    </form>
                    <?php } elseif ($canAssignReviewer && !empty($availableReviewers)) { ?>
                    <!-- Editor/owner dropdown -->
                    <form method="post" action="/dashboard/posts/<?= (int) $post['id'] ?>/workflow/assign-reviewer"
                          class="pt-2 border-t border-dashed border-slate-200 dark:border-zink-600">
                        {{ csrf_field() }}
                        <?php
                            $reviewerOptions = [];
                        foreach ($availableReviewers as $reviewer) {
                            $reviewerOptions[(int) $reviewer['user_id']] = e($reviewer['username']).' ('.ucfirst((string) $reviewer['role']).')';
                        }
                        ?>
                        {% cmp="select" label="Assign reviewer" name="reviewer_id" options="{$reviewerOptions}" emptyDefault=true %}
                        {% cmp="btn" type="submit" variant="sky" icon="user-plus" label="Assign" %}
                    </form>
                    <?php } elseif ($canAssignReviewer) { ?>
                    <p class="pt-2 border-t border-dashed border-slate-200 dark:border-zink-600 text-xs text-slate-400 dark:text-zink-400 italic">
                        All available reviewers are already assigned.
                    </p>
                    <?php } ?>
                </div>
            </section>
            <?php } ?>

            <!-- Live preview link -->
            <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
                <div class="p-4">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100 mb-2">Live preview</h2>
                    <a href="/blog/<?= e($blog['blog_slug']) ?>/<?= e($post['slug']) ?>"
                       target="_blank"
                       class="inline-flex items-center gap-1.5 text-xs font-medium text-sky-600 hover:text-sky-700 dark:text-sky-400 dark:hover:text-sky-300 transition-colors">
                        <i data-lucide="external-link" class="size-3.5"></i>
                        Open as visitor would see it
                    </a>
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-zink-400">
                        Shows the post through the blog's theme, bypassing the published requirement.
                    </p>
                </div>
            </section>

            <!-- Editing tips -->
            <section class="p-4 text-[11px] bg-slate-50 border border-dashed border-slate-200 rounded-lg dark:bg-zink-800 dark:border-zink-600 dark:text-zink-200">
                <h3 class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-100">Review tips</h3>
                <p>Check for factual accuracy, grammar, and tone. Use the feedback box to explain what needs changing before marking the post as needing revision.</p>
            </section>
        </aside>
    </div>
</div>

<div id="fullScreenModal"
    modal-center=""
    class="fixed !inset-0 flex flex-col hidden transition-all duration-300 ease-in-out z-drawer show">
    <div class="flex flex-col w-full h-full md:w-4/5 md:h-4/5 md:max-w-4xl md:max-h-[90vh] md:rounded-xl md:shadow-2xl bg-white dark:bg-zink-600 overflow-hidden mx-auto my-auto">
        <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-zink-500">
            <h5 class="text-16 font-semibold">Preview: {{ post.title }}</h5>
            <button
                data-modal-close="fullScreenModal"
                class="transition-all duration-200 ease-linear text-slate-500 hover:text-red-500 dark:text-zink-200 dark:hover:text-red-500">
                <i data-lucide="x" class="size-5"></i>
            </button>
        </div>
        <div class="p-4 flex-1 min-h-0" data-simplebar data-simplebar-auto-hide="false">
            {{ post.content|raw }}
        </div>
        <div class="flex items-center justify-between p-4 mt-auto border-t border-slate-200 dark:border-zink-500">
            <p class="text-sm text-slate-500 dark:text-zink-300">Last updated: {{ post.updated_at }}</p>
            <div class="flex items-center gap-2">
                {% cmp="btn" type="button" variant="blue" icon="x" label="Close" dataBtn="data-modal-close='fullScreenModal'" %}
            </div>
        </div>
    </div>
</div>
{% endblock %}
{% block scripts %}
<script src="/cp-assets/js/modal.js"></script>
{% endblock %}
