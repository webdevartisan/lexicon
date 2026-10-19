{% extends "back.lex.php" %}

{% block title %}Comments{% endblock %}
{% block subtitle %}<span class="hidden sm:inline"><?php if (!empty($autoPublish)) { ?>Moderate comments on this blog. New ones are published straight away, and you can hold or remove them here.<?php } else { ?>Moderate comments on this blog. New ones show up as <span class="font-medium">pending</span> until you approve them.<?php } ?></span>{% endblock %}

{% block body %}
<?php
// Comment status filters with counts
$statusFilters = [
    ['key' => 'all',      'label' => 'All',      'count' => $counts['all']],
    ['key' => 'pending',  'label' => 'Pending',  'count' => $counts['pending']],
    ['key' => 'approved', 'label' => 'Approved', 'count' => $counts['approved']],
    ['key' => 'spam',     'label' => 'Spam',     'count' => $counts['spam']],
    ['key' => 'reported', 'label' => 'Reported', 'count' => $counts['reported'] ?? 0],
];

// Build query string with overrides (keeps filters intact)
$buildQuery = static function (array $overrides) use ($q, $status): string {
    $params = array_filter([
        'q' => $overrides['q'] ?? $q,
        'status' => $overrides['status'] ?? $status,
        'page' => $overrides['page'] ?? null,
    ], static fn ($v) => $v !== '' && $v !== null);

    return $params ? '?'.http_build_query($params) : '';
};

$basePath = lurl('/dashboard/blog/'.$blog['id'].'/comments');

// Badge colors for status
$statusBadge = [
    'pending' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
    'approved' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'spam' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
];
?>

<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <nav class="flex flex-wrap gap-2" aria-label="Comment status">
            <?php foreach ($statusFilters as $f) {
                $isActive = (string) $status === (string) $f['key'];
                $href = $basePath.$buildQuery(['status' => $f['key'], 'page' => null]);

                $classes = 'inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 '.
                    ($isActive
                        ? 'text-white bg-custom-500 border-custom-500'
                        : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500');

                $badgeClass = $isActive ? 'bg-white/20' : 'bg-slate-100 dark:bg-zink-600';
                ?>
            <a href="<?= e($href) ?>" aria-pressed="<?= $isActive ? 'true' : 'false' ?>" class="<?= $classes ?>">
                <?= e($f['label']) ?>
                <span class="px-1.5 rounded-full <?= $badgeClass ?>"><?= (int) $f['count'] ?></span>
            </a>
            <?php } ?>
        </nav>

        <form method="GET" action="<?= e($basePath) ?>" class="flex items-center gap-2 w-full sm:w-auto" role="search">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search comments or posts" aria-label="Search comments"
                class="form-input w-full sm:w-64 px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-700 placeholder:text-slate-400 focus:outline-none focus:border-custom-500 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100">
            {% cmp="btn" type="submit" variant="slate" icon="search" label="Search" %}
            <?php if ($q !== '') { ?>
            <a href="<?= e($basePath.$buildQuery(['q' => '', 'page' => null])) ?>" class="text-xs text-slate-500 hover:text-custom-500 dark:text-zink-300 whitespace-nowrap">Clear</a>
            <?php } ?>
        </form>
    </div>

    {% if comments|empty %}
        <div class="card">
            <div class="card-body text-center py-12">
                {% cache 'lucide:message-square-dashed' ttl=31536000 %}<i data-lucide="message-square-dashed" class="size-12 text-slate-400 mx-auto mb-3"></i>{% endcache %}
                <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">
                    <?php if ($q !== '') { ?>
                        No comments match "<?= e(truncate($q, 40)) ?>"
                    <?php } elseif ($status === 'pending') { ?>
                        No comments waiting
                    <?php } elseif ($status === 'spam') { ?>
                        No spam
                    <?php } elseif ($status === 'all') { ?>
                        No comments yet
                    <?php } else { ?>
                        No <?= e($status) ?> comments
                    <?php } ?>
                </h3>
                <p class="text-sm text-slate-500 dark:text-zink-300">
                    <?php if ($status === 'pending') { ?>
                        You're all caught up. Nothing to moderate right now.
                    <?php } else { ?>
                        Nothing to show here yet.
                    <?php } ?>
                </p>
            </div>
        </div>
    {% else %}
        <?php
        $bulkActions = [
            ['action' => 'approve', 'label' => 'Approve', 'icon' => 'check', 'hover' => 'hover:bg-green-600/30'],
            ['action' => 'unapprove', 'label' => 'Pending', 'icon' => 'undo-2', 'hover' => 'hover:bg-amber-600/30'],
            ['action' => 'spam', 'label' => 'Spam', 'icon' => 'shield-alert', 'hover' => 'hover:bg-orange-600/30'],
            ['action' => 'delete', 'label' => 'Delete', 'icon' => 'trash-2', 'hover' => 'hover:bg-red-600/30', 'danger' => true, 'confirm' => 'Permanently delete {n}? This cannot be undone.'],
        ];
        $bulkHiddenFields = ['blog_id' => (string) $blog['id'], 'return_status' => $status];
        ?>
        {% cmp="bulk-actions-bar" actionUrl="/dashboard/comment/bulk" itemLabel="comment" actions="{$bulkActions}" hiddenFields="{$bulkHiddenFields}" %}

        <div class="queue">
            <div class="queue-list">
                <div>
                    <div class="queue-head">
                        <span><?= (int) $pagination['total_records'] ?> comment<?= (int) $pagination['total_records'] === 1 ? '' : 's' ?></span>
                    </div>
                    <ul role="listbox" aria-label="Comments">
                        {% foreach ($comments as $c): %}
                        <?php
                        $removed = !empty($c['deleted_at']);
                        $preview = $removed ? 'Removed' : mb_strimwidth((string) $c['content'], 0, 140, '...');
                        ?>
                        <li class="flex items-start border-b border-slate-100 dark:border-zink-600" data-bulk-row>
                            <label class="shrink-0 pt-4 ps-4">
                                <input type="checkbox" name="comment_ids[]" value="<?= e((string) $c['id']) ?>" form="bulk-form"
                                    aria-label="Select comment by <?= e((string) ($c['user_name'] ?? 'Anonymous')) ?>"
                                    class="bulk-checkbox form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-2 focus:ring-custom-500">
                            </label>
                            <button type="button" role="option" data-queue-item="<?= e((string) $c['id']) ?>" aria-selected="false" class="queue-item grow min-w-0">
                                <span class="queue-avatar" aria-hidden="true"><?= e(mb_substr((string) ($c['user_name'] ?? 'A'), 0, 1)) ?></span>
                                <span class="grow min-w-0">
                                    <span class="flex items-center justify-between gap-2 mb-0.5">
                                        <span class="text-sm font-medium text-slate-900 dark:text-zink-50 truncate"><?= e((string) ($c['user_name'] ?? 'Anonymous')) ?></span>
                                        <span class="text-xs shrink-0 text-slate-400 dark:text-zink-400"><?= e(local_datetime($c['created_at'] ?? null, 'M j')) ?></span>
                                    </span>
                                    <span class="block text-sm text-slate-600 dark:text-zink-200 line-clamp-2 break-words"><?= e($preview) ?></span>
                                    <span class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                        <span class="pill <?= $statusBadge[$c['status']] ?? $statusBadge['pending'] ?>"><?= e(ucfirst((string) $c['status'])) ?></span>
                                        <?php if ((int) ($c['reports_count'] ?? 0) > 0) { ?>
                                        <span class="pill bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300"><?= (int) $c['reports_count'] ?> reported</span>
                                        <?php } ?>
                                        <span class="text-xs text-slate-400 dark:text-zink-400 truncate"><?= e(truncate((string) ($c['post_title'] ?? 'post'), 30)) ?></span>
                                    </span>
                                </span>
                            </button>
                        </li>
                        {% endforeach %}
                    </ul>
                </div>

                <div class="p-4">
                    {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="{$basePath}" %}
                </div>
            </div>

            <div class="min-w-0 h-full overflow-hidden">
                {% foreach ($comments as $c): %}
                <section class="queue-detail h-full" data-queue-panel="<?= e((string) $c['id']) ?>" aria-label="Comment by <?= e((string) ($c['user_name'] ?? 'Anonymous')) ?>">
                    <div class="queue-bar">
                        <button type="button" data-queue-back class="queue-icon queue-back" aria-label="Back to comments"><i data-lucide="arrow-left" class="size-4"></i></button>
                        <button type="button" data-queue-step="-1" class="queue-icon" aria-label="Previous"><i data-lucide="chevron-up" class="size-4"></i></button>
                        <button type="button" data-queue-step="1" class="queue-icon" aria-label="Next"><i data-lucide="chevron-down" class="size-4"></i></button>
                    </div>
                    <div class="queue-body">

                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="text-base font-semibold text-slate-900 dark:text-zink-50"><?= e((string) ($c['user_name'] ?? 'Anonymous')) ?></span>
                            <span class="pill <?= $statusBadge[$c['status']] ?? $statusBadge['pending'] ?>"><?= e(ucfirst((string) $c['status'])) ?></span>
                        </div>
                        <?php if (!empty($c['user_email'])) { ?>
                        <p class="text-xs text-slate-400 dark:text-zink-300"><?= e((string) $c['user_email']) ?></p>
                        <?php } ?>
                        <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300">
                            <?= e(local_datetime($c['created_at'] ?? null, 'M j, Y · g:i a')) ?> on
                            <a href="<?= e(lurl('/blog/'.((string) ($c['blog_slug'] ?? '')).'/'.((string) ($c['post_slug'] ?? '')))) ?>" target="_blank" rel="noopener" class="hover:text-custom-500 hover:underline"><?= e(truncate((string) ($c['post_title'] ?? 'post'), 60)) ?></a>
                        </p>

                        <?php if (!empty($c['parent_comment_id'])) { ?>
                        <p class="mb-2 text-xs italic text-slate-400 dark:text-zink-300">In reply to: "<?= e(truncate((string) ($c['parent_content'] ?? ''), 80)) ?>"</p>
                        <?php } ?>

                        <?php if ((int) ($c['reports_count'] ?? 0) > 0) { ?>
                        <p class="mb-2 text-xs font-medium text-rose-600 dark:text-rose-400">Reported by <?= (int) $c['reports_count'] ?> reader<?= (int) $c['reports_count'] === 1 ? '' : 's' ?></p>
                        <?php } ?>

                        <?php if (!empty($c['deleted_at'])) { ?>
                        <p class="p-3 mb-4 rounded-lg text-sm italic bg-slate-50 text-slate-500 dark:bg-zink-700 dark:text-zink-300">
                            <?= ($c['deleted_by'] ?? 'author') === 'moderator'
                                ? 'Removed by a moderator. The text is gone; the row is kept so its replies still read in order.'
                                : 'Deleted by its author. The text is gone; the row is kept so its replies still read in order.' ?>
                        </p>
                        <?php } else { ?>
                        <div class="p-3 mb-4 rounded-lg text-sm break-words whitespace-pre-line bg-slate-50 text-slate-700 dark:bg-zink-700 dark:text-zink-100"><?= e((string) $c['content']) ?></div>
                        <?php } ?>

                        <div class="flex flex-wrap gap-2">
                            <?php if ($c['status'] !== 'approved') { ?>
                            <form method="POST" action="/dashboard/comment/<?= e((string) $c['id']) ?>/approve" class="m-0">
                                {{ csrf_field() }}
                                <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                {% cmp="btn" type="submit" variant="blue" icon="check" label="Approve" %}
                            </form>
                            <?php } ?>
                            <?php if ($c['status'] !== 'pending') { ?>
                            <form method="POST" action="/dashboard/comment/<?= e((string) $c['id']) ?>/unapprove" class="m-0">
                                {{ csrf_field() }}
                                <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                {% cmp="btn" type="submit" variant="slate" icon="undo-2" label="Move to pending" %}
                            </form>
                            <?php } ?>
                            <?php if ($c['status'] !== 'spam') { ?>
                            <form method="POST" action="/dashboard/comment/<?= e((string) $c['id']) ?>/spam" class="m-0">
                                {{ csrf_field() }}
                                <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                {% cmp="btn" type="submit" variant="slate" icon="shield-alert" label="Spam" %}
                            </form>
                            <?php } ?>
                            <form method="POST" action="/dashboard/comment/<?= e((string) $c['id']) ?>/destroy" class="m-0">
                                {{ csrf_field() }}
                                <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                <button type="submit" data-confirm="Permanently delete this comment? This cannot be undone."
                                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/30 transition-colors">
                                    <i data-lucide="trash-2" class="size-4"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </section>
                {% endforeach %}
            </div>
        </div>
    {% endif %}
</div>
{% endblock %}

