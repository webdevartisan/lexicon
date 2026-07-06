{% extends "back.lex.php" %}

{% block title %}Overview{% endblock %}
{% block subtitle %}Site-wide stats and quick access to every admin tool.{% endblock %}

{% block body %}
<?php
$statusBadge = [
    'published' => 'bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800',
    'draft' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500',
    'archived' => 'bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100 dark:border-zink-600',
];
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-5">
        <a href="/admin/posts" class="card hover:ring-1 hover:ring-custom-500 transition-shadow">
            <div class="card-body flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-zink-200">Posts</p>
                    <p class="text-3xl font-semibold text-slate-900 dark:text-zink-50 mt-2"><?= number_format($stats['posts']) ?></p>
                </div>
                <div class="flex items-center justify-center size-12 bg-purple-100 rounded-md dark:bg-purple-500/20">
                    <i class="text-purple-500 dark:text-purple-200" data-lucide="file-text"></i>
                </div>
            </div>
        </a>

        <a href="/admin/comments?status=pending" class="card hover:ring-1 hover:ring-custom-500 transition-shadow">
            <div class="card-body flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-zink-200">Comments</p>
                    <p class="text-3xl font-semibold text-slate-900 dark:text-zink-50 mt-2"><?= number_format($stats['comments']) ?></p>
                    <?php if ($stats['pending_comments'] > 0) { ?>
                    <span class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 text-[10px] font-semibold rounded-full bg-amber-100 text-amber-700 border border-amber-200 dark:bg-amber-900/40 dark:border-amber-800">
                        <?= number_format($stats['pending_comments']) ?> pending
                    </span>
                    <?php } ?>
                </div>
                <div class="flex items-center justify-center size-12 bg-green-100 rounded-md dark:bg-green-500/20">
                    <i class="text-green-500 dark:text-green-200" data-lucide="message-square"></i>
                </div>
            </div>
        </a>

        <a href="/admin/users" class="card hover:ring-1 hover:ring-custom-500 transition-shadow">
            <div class="card-body flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-zink-200">Users</p>
                    <p class="text-3xl font-semibold text-slate-900 dark:text-zink-50 mt-2"><?= number_format($stats['users']) ?></p>
                </div>
                <div class="flex items-center justify-center size-12 bg-sky-100 rounded-md dark:bg-sky-500/20">
                    <i class="text-sky-500 dark:text-sky-200" data-lucide="users"></i>
                </div>
            </div>
        </a>

        <a href="/admin/blogs" class="card hover:ring-1 hover:ring-custom-500 transition-shadow">
            <div class="card-body flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500 dark:text-zink-200">Blogs</p>
                    <p class="text-3xl font-semibold text-slate-900 dark:text-zink-50 mt-2"><?= number_format($stats['blogs']) ?></p>
                </div>
                <div class="flex items-center justify-center size-12 bg-orange-100 rounded-md dark:bg-orange-500/20">
                    <i class="text-orange-500 dark:text-orange-200" data-lucide="book-open"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Quick actions -->
    <div class="flex flex-wrap gap-2 mb-5">
        {% cmp="btn" href="/admin/posts/new" variant="blue" icon="pen" label="New Post" %}
        {% cmp="btn" href="/admin/users/new" variant="blue" icon="user-plus" label="New User" %}
        {% cmp="btn" href="/admin/blogs/new" variant="blue" icon="file-plus" label="New Blog" %}
        <a href="/admin/comments?status=pending" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            <i data-lucide="message-square" class="size-4"></i> Moderate Comments
        </a>
        <a href="/admin/cache" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            <i data-lucide="database-zap" class="size-4"></i> Cache Tools
        </a>
        <a href="/admin/settings" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium border rounded-md text-slate-700 bg-white border-slate-200 hover:bg-slate-50 dark:bg-zink-700 dark:text-zink-100 dark:border-zink-500 dark:hover:bg-zink-600 transition-colors">
            <i data-lucide="settings" class="size-4"></i> Settings
        </a>
    </div>

    <!-- Insights -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5">
        <!-- Signups trend -->
        <div class="card">
            <div class="card-body">
                <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-1">Signups — last 30 days</h3>
                <?php $signupTotal = array_sum($signups); ?>
                <p class="text-xs text-slate-500 dark:text-zink-300 mb-4"><?= number_format($signupTotal) ?> new account<?= $signupTotal === 1 ? '' : 's' ?></p>
                <?php $maxSignups = max(1, max($signups)); ?>
                <div class="flex items-end gap-[2px] h-24" role="img" aria-label="Daily signups over the last 30 days">
                    <?php foreach ($signups as $day => $count) { ?>
                    <div class="grow rounded-t-sm <?= $count > 0 ? 'bg-custom-500' : 'bg-slate-200 dark:bg-zink-600' ?>"
                         style="height: <?= $count > 0 ? max(8, (int) round($count / $maxSignups * 100)) : 3 ?>%"
                         data-tooltip data-tooltip-content="<?= e(date('M j', strtotime($day)).': '.$count) ?>"></div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Content breakdown -->
        <div class="card">
            <div class="card-body">
                <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-4">Content breakdown</h3>
                <?php
                $postTotal = max(1, array_sum($postCounts));
                $rows = [
                    ['label' => 'Published', 'count' => $postCounts['published'] ?? 0, 'bar' => 'bg-green-500'],
                    ['label' => 'Drafts', 'count' => $postCounts['draft'] ?? 0, 'bar' => 'bg-slate-400'],
                    ['label' => 'Archived', 'count' => $postCounts['archived'] ?? 0, 'bar' => 'bg-slate-700'],
                    ['label' => 'Comments pending', 'count' => $commentCounts['pending'], 'bar' => 'bg-amber-500', 'total' => max(1, $commentCounts['all'])],
                    ['label' => 'Comments spam', 'count' => $commentCounts['spam'], 'bar' => 'bg-red-500', 'total' => max(1, $commentCounts['all'])],
                ];
                ?>
                <div class="space-y-3">
                    <?php foreach ($rows as $row) { $den = $row['total'] ?? $postTotal; ?>
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="text-slate-600 dark:text-zink-200"><?= e($row['label']) ?></span>
                            <span class="font-semibold text-slate-900 dark:text-zink-50"><?= number_format($row['count']) ?></span>
                        </div>
                        <div class="h-1.5 rounded-full bg-slate-100 dark:bg-zink-600 overflow-hidden">
                            <div class="h-full rounded-full <?= $row['bar'] ?>" style="width: <?= (int) round($row['count'] / $den * 100) ?>%"></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Top blogs -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50">Top blogs by posts</h3>
                    <a href="/admin/blogs" class="text-xs font-medium text-custom-500 hover:text-custom-600">All blogs</a>
                </div>
                <?php if (empty($topBlogs)) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300 py-4 text-center">No blogs yet.</p>
                <?php } else { ?>
                <div class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($topBlogs as $tb) { ?>
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="/admin/blogs/<?= e((string) $tb['id']) ?>/show" class="text-sm font-medium text-slate-900 dark:text-zink-50 hover:text-custom-500 block truncate">
                                <?= e($tb['blog_name']) ?>
                            </a>
                            <span class="text-[11px] text-slate-400 dark:text-zink-300"><?= e((string) ($tb['owner_name'] ?? '')) ?></span>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-slate-600 dark:text-zink-200"><?= (int) ($tb['post_count'] ?? 0) ?> posts</span>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
        <!-- Pending comments -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50">Awaiting moderation</h3>
                    <a href="/admin/comments?status=pending" class="text-xs font-medium text-custom-500 hover:text-custom-600">View all</a>
                </div>
                <?php if (empty($pendingComments)) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300 py-4 text-center">All caught up — no pending comments.</p>
                <?php } else { ?>
                <div class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($pendingComments as $c) { ?>
                    <div class="py-2.5">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-sm font-medium text-slate-900 dark:text-zink-50"><?= e((string) ($c['user_name'] ?? 'Anonymous')) ?></span>
                            <span class="text-[11px] text-slate-400 dark:text-zink-300">on <?= e(truncate((string) ($c['post_title'] ?? ''), 40)) ?></span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zink-300"><?= e(truncate((string) $c['content'], 110)) ?></p>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>

        <!-- Recent posts -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50">Recently updated posts</h3>
                    <a href="/admin/posts" class="text-xs font-medium text-custom-500 hover:text-custom-600">View all</a>
                </div>
                <?php if (empty($recentPosts)) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300 py-4 text-center">No posts yet.</p>
                <?php } else { ?>
                <div class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($recentPosts as $p) { ?>
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="/admin/posts/<?= e((string) $p['id']) ?>/show" class="text-sm font-medium text-slate-900 dark:text-zink-50 hover:text-custom-500 block truncate">
                                <?= e(truncate((string) $p['title'], 60)) ?>
                            </a>
                            <span class="text-[11px] text-slate-400 dark:text-zink-300">
                                <?= e((string) ($p['blog_name'] ?? '')) ?> · <?= e((string) ($p['author_username'] ?? '')) ?> · <?= e(date('M j', strtotime((string) $p['updated_at']))) ?>
                            </span>
                        </div>
                        <span class="shrink-0 inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border <?= $statusBadge[$p['status']] ?? $statusBadge['draft'] ?>">
                            <?= e(ucfirst((string) $p['status'])) ?>
                        </span>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>

        <!-- Recent users -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50">Newest users</h3>
                    <a href="/admin/users" class="text-xs font-medium text-custom-500 hover:text-custom-600">View all</a>
                </div>
                <?php if (empty($recentUsers)) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300 py-4 text-center">No users yet.</p>
                <?php } else { ?>
                <div class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($recentUsers as $u) { ?>
                    <div class="py-2.5 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <a href="/admin/users/<?= e((string) $u['id']) ?>/edit" class="text-sm font-medium text-slate-900 dark:text-zink-50 hover:text-custom-500 block truncate">
                                <?= e((string) $u['username']) ?>
                            </a>
                            <span class="text-[11px] text-slate-400 dark:text-zink-300"><?= e((string) $u['email']) ?></span>
                        </div>
                        <span class="shrink-0 text-[11px] text-slate-400 dark:text-zink-300">
                            <?= e(date('M j, Y', strtotime((string) $u['created_at']))) ?>
                        </span>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="card">
            <div class="card-body">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50">Recent activity</h3>
                    <a href="/admin/audit-log" class="text-xs font-medium text-custom-500 hover:text-custom-600">Full audit log</a>
                </div>
                <?php if (empty($recentActivity)) { ?>
                <p class="text-sm text-slate-500 dark:text-zink-300 py-4 text-center">No recorded activity yet.</p>
                <?php } else { ?>
                <div class="divide-y divide-slate-100 dark:divide-zink-600">
                    <?php foreach ($recentActivity as $a) { ?>
                    <div class="py-2 flex items-center justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <span class="font-medium text-slate-900 dark:text-zink-50"><?= e((string) ($a['username'] ?? 'system')) ?></span>
                            <span class="text-slate-500 dark:text-zink-300"><?= e((string) $a['action']) ?></span>
                            <span class="text-slate-400 dark:text-zink-300 text-xs"><?= e((string) $a['resource_type']) ?><?= $a['resource_id'] !== null ? ' #'.e((string) $a['resource_id']) : '' ?></span>
                        </div>
                        <span class="shrink-0 text-[11px] text-slate-400 dark:text-zink-300"><?= e(date('M j · g:i a', strtotime((string) $a['created_at']))) ?></span>
                    </div>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- System health -->
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mb-5">
        <div class="card">
            <div class="card-body">
                <h3 class="text-base font-semibold text-slate-900 dark:text-zink-50 mb-3">System health</h3>
                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">PHP</dt>
                        <dd class="text-slate-900 dark:text-zink-50"><?= e($health['php_version']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">Environment</dt>
                        <dd class="text-slate-900 dark:text-zink-50"><?= e($health['environment']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">Debug</dt>
                        <dd>
                            <?php if ($health['debug']) { ?>
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800">On — disable in production</span>
                            <?php } else { ?>
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800">Off</span>
                            <?php } ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">Maintenance</dt>
                        <dd>
                            <?php if ($health['maintenance']) { ?>
                            <a href="/admin/settings" class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:border-amber-800">On — site offline</a>
                            <?php } else { ?>
                            <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:border-green-800">Off — site live</span>
                            <?php } ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">Mail driver</dt>
                        <dd class="text-slate-900 dark:text-zink-50"><?= e($health['mail_driver']) ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">Free disk</dt>
                        <dd class="text-slate-900 dark:text-zink-50"><?= $health['disk_free_gb'] !== null ? e((string) $health['disk_free_gb']).' GB' : 'n/a' ?></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-300 mb-1">Server time</dt>
                        <dd class="text-slate-900 dark:text-zink-50"><?= e($health['server_time']) ?></dd>
                    </div>
                </dl>
            </div>
        </div>

        <?php if (isset($cacheStats)) { ?>
        <div>
            {% cmp="cache-stats-card" stats="{$cacheStats}" isAdmin="true" %}
        </div>
        <?php } ?>
    </div>

</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
