{% extends "back.lex.php" %}

{% block title %}{{ blog.blog_name }} · Comments{% endblock %}

{% block body %}
<?php
// Comment status filters with counts
$statusFilters = [
    ['key' => 'all',      'label' => 'All',      'count' => $counts['all']],
    ['key' => 'pending',  'label' => 'Pending',  'count' => $counts['pending']],
    ['key' => 'approved', 'label' => 'Approved', 'count' => $counts['approved']],
    ['key' => 'spam',     'label' => 'Spam',     'count' => $counts['spam']],
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

$basePath = '/dashboard/blog/'.$blog['id'].'/comments';

// Badge colors for status
$statusBadge = [
    'pending' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800',
    'approved' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'spam' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
];
?>

<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    <div class="flex flex-col gap-2 mb-6">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-zink-50">Comments</h1>
        <p class="text-sm text-slate-500 dark:text-zink-300">
            Moderate comments on <span class="font-medium text-slate-700 dark:text-zink-100"><?= e($blog['blog_name']) ?></span>.
            New ones show up as <span class="font-medium">pending</span> until you approve them.
        </p>
    </div>

    <!-- Search form -->
    <form method="GET" action="<?= e($basePath) ?>" class="card mb-4">
        <div class="card-body">
            <input type="hidden" name="status" value="<?= e($status) ?>">
            <div class="flex flex-col md:flex-row gap-3">
                <div class="relative grow">
                    <i data-lucide="search" class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    <input id="q" name="q" type="text" value="<?= e($q) ?>"
                        placeholder="Search comment text or post title..."
                        class="form-input w-full pl-9 border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500">
                </div>
                <button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-sm font-medium text-white bg-custom-500 border border-custom-500 rounded-md hover:bg-custom-600 transition-colors">
                    <i data-lucide="search" class="size-4"></i> Search
                </button>
            </div>
        </div>
    </form>

    <!-- Status tabs -->
    <div class="flex flex-wrap gap-2 mb-5">
        <?php foreach ($statusFilters as $f) {
            $isActive = (string) $status === (string) $f['key'];
            $href = $basePath.$buildQuery(['status' => $f['key'], 'page' => null]);

            $classes = 'inline-flex items-center gap-2 px-3 py-1.5 text-xs font-medium rounded-full border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 focus-visible:ring-offset-1 '.
                ($isActive
                    ? 'text-white bg-custom-500 border-custom-500'
                    : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:border-zink-400');

            $badgeClass = $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600 dark:bg-zink-600 dark:text-zink-200';
            ?>
        <a href="<?= e($href) ?>"
           aria-pressed="<?= $isActive ? 'true' : 'false' ?>"
           class="<?= $classes ?>">
            <?= e($f['label']) ?>
            <span class="inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[10px] font-semibold rounded-full <?= $badgeClass ?>">
                <?= (int) $f['count'] ?>
            </span>
        </a>
        <?php } ?>
    </div>

    {% if comments|empty %}
        <div class="card">
            <div class="card-body text-center py-12">
                <i data-lucide="message-square-dashed" class="size-12 text-slate-400 mx-auto mb-3"></i>
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
                        You're all caught up — nothing to moderate right now.
                    <?php } else { ?>
                        Nothing to show here yet.
                    <?php } ?>
                </p>
            </div>
        </div>
    {% else %}
        <!-- Bulk action form -->
        <form id="bulk-form" method="POST" action="/dashboard/comment/bulk">
            {{ csrf_field() }}
            <input type="hidden" name="bulk_action" id="bulk-action" value="">
            <input type="hidden" name="blog_id" value="<?= e((string) $blog['id']) ?>">
            <input type="hidden" name="return_status" value="<?= e($status) ?>">
        </form>

        <div>
            <div class="flex items-center justify-between mb-3 text-xs">
                <label class="inline-flex items-center gap-2 text-slate-600 dark:text-zink-200 cursor-pointer">
                    <input type="checkbox" id="select-all" class="form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500">
                    <span>Select all on this page</span>
                </label>
                <span id="selected-count" class="text-slate-500 dark:text-zink-300"></span>
            </div>

            <div class="card">
                <div class="divide-y divide-slate-100 dark:divide-zink-600">
                    {% foreach ($comments as $c): %}
                        <div class="p-4 flex gap-3 hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors">
                            <label class="shrink-0 pt-1">
                                <input type="checkbox" name="comment_ids[]" value="<?= e((string) $c['id']) ?>" form="bulk-form"
                                    aria-label="Select comment by <?= e((string) ($c['user_name'] ?? 'Anonymous')) ?>"
                                    class="bulk-checkbox form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-2 focus:ring-custom-500">
                            </label>

                            <div class="grow min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <span class="text-sm font-medium text-slate-900 dark:text-zink-50">
                                        <?= e((string) ($c['user_name'] ?? 'Anonymous')) ?>
                                    </span>
                                    <?php if (!empty($c['user_email'])) { ?>
                                        <span class="text-xs text-slate-400 dark:text-zink-300"><?= e((string) $c['user_email']) ?></span>
                                    <?php } ?>
                                    <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border <?= $statusBadge[$c['status']] ?? $statusBadge['pending'] ?>">
                                        <?= e(ucfirst((string) $c['status'])) ?>
                                    </span>
                                </div>

                                <p class="text-sm text-slate-700 dark:text-zink-100 mb-2 break-words whitespace-pre-line max-h-32 overflow-y-auto">
                                    <?= e((string) $c['content']) ?>
                                </p>

                                <?php if (mb_strlen((string) $c['content']) > 600) { ?>
                                    <p class="text-[11px] text-amber-600 dark:text-amber-400 mb-2">
                                        <i data-lucide="alert-triangle" class="inline-block size-3"></i>
                                        Long comment (<?= number_format(mb_strlen((string) $c['content'])) ?> chars) — scroll to read in full.
                                    </p>
                                <?php } ?>

                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500 dark:text-zink-300">
                                    <span class="inline-flex items-center gap-1">
                                        <i data-lucide="clock" class="size-3"></i>
                                        <?= e(date('M j, Y · g:i a', strtotime((string) $c['created_at']))) ?>
                                    </span>
                                    <a href="/blog/<?= e((string) ($c['blog_slug'] ?? '')) ?>/<?= e((string) ($c['post_slug'] ?? '')) ?>"
                                        target="_blank" rel="noopener"
                                        class="inline-flex items-center gap-1 hover:text-custom-500 transition-colors">
                                        <i data-lucide="file-text" class="size-3"></i>
                                        <?= e(truncate((string) ($c['post_title'] ?? 'post'), 50)) ?>
                                        <i data-lucide="external-link" class="size-3"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- Per-comment actions -->
                            <div class="shrink-0 flex items-start gap-1">
                                <?php if ($c['status'] !== 'approved') { ?>
                                    <form method="POST" action="/dashboard/comment/<?= e((string) $c['id']) ?>/approve" class="m-0">
                                        {{ csrf_field() }}
                                        <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                        <button type="submit" title="Approve"
                                            class="p-2 text-slate-500 hover:text-green-600 rounded-md hover:bg-green-50 dark:hover:bg-green-900/30 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-500">
                                            <i data-lucide="check" class="size-4"></i>
                                        </button>
                                    </form>
                                <?php } ?>

                                <?php if ($c['status'] !== 'pending') { ?>
                                    <form method="POST" action="/dashboard/comment/<?= e((string) $c['id']) ?>/unapprove" class="m-0">
                                        {{ csrf_field() }}
                                        <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                        <button type="submit" title="Move to pending"
                                            class="p-2 text-slate-500 hover:text-amber-600 rounded-md hover:bg-amber-50 dark:hover:bg-amber-900/30 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                                            <i data-lucide="undo-2" class="size-4"></i>
                                        </button>
                                    </form>
                                <?php } ?>

                                <?php if ($c['status'] !== 'spam') { ?>
                                    <form method="POST" action="/dashboard/comment/<?= e((string) $c['id']) ?>/spam" class="m-0">
                                        {{ csrf_field() }}
                                        <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                        <button type="submit" title="Mark as spam"
                                            class="p-2 text-slate-500 hover:text-orange-600 rounded-md hover:bg-orange-50 dark:hover:bg-orange-900/30 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500">
                                            <i data-lucide="shield-alert" class="size-4"></i>
                                        </button>
                                    </form>
                                <?php } ?>

                                <span class="mx-0.5 mt-1 h-5 w-px bg-slate-200 dark:bg-zink-500" aria-hidden="true"></span>

                                <form method="POST" action="/dashboard/comment/<?= e((string) $c['id']) ?>/destroy" class="m-0"
                                    onsubmit="return confirm('Permanently delete this comment? This cannot be undone.');">
                                    {{ csrf_field() }}
                                    <input type="hidden" name="return_status" value="<?= e($status) ?>">
                                    <button type="submit" title="Delete"
                                        class="p-2 text-slate-500 hover:text-red-600 rounded-md hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                                        <i data-lucide="trash-2" class="size-4"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    {% endforeach %}
                </div>
            </div>

            <div class="mt-6">
                {% cmp="paginator" pagination="{$pagination}" pageParam="page" query="{$q}" basePath="{$basePath}" %}
            </div>

            <!-- Floating bulk action bar -->
            <div id="bulk-bar" class="hidden" style="position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%); z-index: 9999;">
                <div class="flex items-center gap-2 px-4 py-3 bg-slate-900 dark:bg-zink-700 text-white rounded-full shadow-lg border border-slate-700">
                    <span id="bulk-count" class="text-sm font-medium pr-2 border-r border-slate-700"></span>
                    <button type="button" data-bulk="approve" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md hover:bg-green-600/30 transition-colors">
                        <i data-lucide="check" class="size-3.5"></i> Approve
                    </button>
                    <button type="button" data-bulk="unapprove" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md hover:bg-amber-600/30 transition-colors">
                        <i data-lucide="undo-2" class="size-3.5"></i> Pending
                    </button>
                    <button type="button" data-bulk="spam" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md hover:bg-orange-600/30 transition-colors">
                        <i data-lucide="shield-alert" class="size-3.5"></i> Spam
                    </button>
                    <button type="button" data-bulk="delete" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md hover:bg-red-600/30 transition-colors text-red-300">
                        <i data-lucide="trash-2" class="size-3.5"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    {% endif %}
</div>
{% endblock %}

{% block scripts %}
<script>
// Bulk comment selection + floating action bar
(function () {
    const form = document.getElementById('bulk-form');
    if (!form) return;

    const checkboxes = Array.from(document.querySelectorAll('.bulk-checkbox'));
    const selectAll  = document.getElementById('select-all');
    const bar   = document.getElementById('bulk-bar');
    const count = document.getElementById('bulk-count');
    const counter = document.getElementById('selected-count');
    const actionInput= document.getElementById('bulk-action');

    document.body.appendChild(bar);

    function refresh() {
        const n = checkboxes.filter(c => c.checked).length;
        if (n > 0) {
            bar.classList.remove('hidden');
            count.textContent = n + ' selected';
            counter.textContent = n + ' selected';
        } else {
            bar.classList.add('hidden');
            counter.textContent = '';
        }

        selectAll.checked = n === checkboxes.length && n > 0;
        selectAll.indeterminate = n > 0 && n < checkboxes.length;
    }

    checkboxes.forEach(c => c.addEventListener('change', refresh));
    selectAll.addEventListener('change', () => {
        checkboxes.forEach(c => c.checked = selectAll.checked);
        refresh();
    });

    bar.querySelectorAll('[data-bulk]').forEach(btn => {
        btn.addEventListener('click', () => {
            const action = btn.dataset.bulk;
            const n = checkboxes.filter(c => c.checked).length;

            if (action === 'delete' && !confirm('Permanently delete ' + n + ' comment(s)? This cannot be undone.')) return;
            actionInput.value = action;
            form.submit();
        });
    });
})();
</script>
{% endblock %}