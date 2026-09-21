{% extends "back.lex.php" %}

{% block title %}<?= e('@'.$user['handle']) ?>{% endblock %}
{% block subtitle %}Everything on record for this account. Read-only: opening this page changes nothing.{% endblock %}

{% block body %}
<?php
$userId = (int) $user['id'];
$base = '/admin/users/'.$userId;
$fullName = trim(($user['first_name'] ?? '').' '.($user['last_name'] ?? ''));
$isSuspended = $user['suspended_at'] !== null;
$accountStatus = $isSuspended ? 'suspended' : ((int) $user['is_active'] === 1 ? 'active' : 'inactive');
$accountLabel = $isSuspended ? 'Suspended' : ((int) $user['is_active'] === 1 ? 'Active' : 'Deactivated');
$mayActOnTarget = !$targetIsAdmin || $actorIsAdmin;

$card = 'card mb-5';
$heading = 'text-base font-semibold text-slate-900 dark:text-zink-50';
$hint = 'text-xs text-slate-400 dark:text-zink-400';
$dl = 'grid grid-cols-1 sm:grid-cols-[12rem_1fr] gap-x-4 gap-y-2 text-sm';
$dt = 'text-slate-500 dark:text-zink-300';
$dd = 'text-slate-900 dark:text-zink-50 break-words';
$th = 'px-3.5 py-2.5 font-semibold';
$td = 'px-3.5 py-2.5';
$tableHead = 'text-left bg-slate-100 dark:bg-zink-600 text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200';
$tableBody = 'divide-y divide-slate-100 dark:divide-zink-600 text-sm';
$empty = 'text-sm text-slate-500 dark:text-zink-300';

$when = static fn (?string $ts): string => $ts ? local_datetime($ts, 'M j, Y H:i') : '—';
$text = static fn (mixed $v): string => ($v === null || $v === '') ? '—' : (string) $v;
// A rule-applied suspension has no person behind it and must never read as if one did.
$suspendedBy = static fn (array $s): string => ($s['source'] ?? 'moderator') === 'rule'
    ? 'Automatically, by the '.($s['rule'] ?? 'unknown').' rule'
    : '@'.$text($s['suspended_by_handle']);
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <?php if ($isSuspended && $suspension) { ?>
    <div class="mb-5 p-4 rounded-md border border-red-200 bg-red-50 text-red-700 dark:bg-red-900/40 dark:border-red-800 dark:text-red-200 text-sm" role="status">
        <strong>Suspended <?= $suspension['expires_at'] ? 'until '.e($when($suspension['expires_at'])) : 'permanently' ?>.</strong>
        <?= e($text($suspension['reason'])) ?>
        <span class="block mt-1">By <?= e($suspendedBy($suspension)) ?> on <?= e($when($suspension['suspended_at'])) ?>. <?= (int) $suspension['blogs_hidden'] ?> blog(s) and <?= (int) $suspension['comments_hidden'] ?> comment(s) hidden.</span>
    </div>
    <?php } ?>

    <div class="<?= $card ?>">
        <div class="card-body flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex items-center gap-4">
                <?php if (!empty($profile['avatar_url'])) { ?>
                <img src="<?= e($profile['avatar_url']) ?>" alt="" class="size-14 rounded-full object-cover" loading="lazy">
                <?php } ?>
                <div>
                    <p class="text-lg font-semibold text-slate-900 dark:text-zink-50">@<?= e($user['handle']) ?></p>
                    <p class="text-sm text-slate-500 dark:text-zink-300"><?= e($fullName !== '' ? $fullName : 'No name set') ?> · <?= e($user['email']) ?></p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        {% cmp="status-badge" status="{$accountStatus}" label="{$accountLabel}" %}
                        <?php foreach ($siteRoles as $siteRole) { ?>
                        <span class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium rounded-full border bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500"><?= e($siteRole['role_name']) ?></span>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if ($mayActOnTarget) { ?>
                {% cmp="btn" href="{$base}/edit" variant="blue" icon="pencil" label="Edit profile" %}
                <?php } ?>
                <?php $previewAttrs = ['target' => '_blank', 'rel' => 'noopener']; ?>
                {% cmp="btn" href="{$base}/preview" variant="slate" icon="external-link" label="Preview profile" attrs="{$previewAttrs}" %}
            </div>
        </div>
    </div>

    <nav aria-label="Sections on this page" class="mb-5 flex flex-wrap gap-x-4 gap-y-1 text-sm">
        <a href="#account" class="text-custom-500 hover:underline">Account</a>
        <a href="#access" class="text-custom-500 hover:underline">Access</a>
        <a href="#content" class="text-custom-500 hover:underline">Content</a>
        <a href="#reports" class="text-custom-500 hover:underline">Reports</a>
        <a href="#moderation" class="text-custom-500 hover:underline">Moderation</a>
        <a href="#security" class="text-custom-500 hover:underline">Security</a>
        <a href="#activity" class="text-custom-500 hover:underline">Activity</a>
    </nav>

    <section id="account" class="<?= $card ?>" aria-labelledby="account-h">
        <div class="card-body">
            <h3 id="account-h" class="<?= $heading ?> mb-3">Account</h3>
            <dl class="<?= $dl ?>">
                <dt class="<?= $dt ?>">ID</dt><dd class="<?= $dd ?>"><?= $userId ?></dd>
                <dt class="<?= $dt ?>">Display name</dt><dd class="<?= $dd ?>"><?= e($text($user['display_name_cached'])) ?></dd>
                <dt class="<?= $dt ?>">Joined</dt><dd class="<?= $dd ?>"><?= e($when($user['created_at'])) ?></dd>
                <dt class="<?= $dt ?>">Last sign-in</dt><dd class="<?= $dd ?>"><?= e($when($user['last_login'])) ?></dd>
                <dt class="<?= $dt ?>">Last updated</dt><dd class="<?= $dd ?>"><?= e($when($user['updated_at'])) ?></dd>
                <dt class="<?= $dt ?>">Age confirmed</dt><dd class="<?= $dd ?>"><?= e($when($user['age_confirmed_at'])) ?></dd>
                <dt class="<?= $dt ?>">Profile</dt>
                <dd class="<?= $dd ?>">
                    <?php if ($publicVisibility['visible']) { ?>
                    Public, visitors can see it.
                    <?php } else { ?>
                    Not visible to visitors: <?= e(implode(' ', $publicVisibility['reasons'])) ?>
                    <?php } ?>
                </dd>
                <dt class="<?= $dt ?>">Bio</dt><dd class="<?= $dd ?>"><?= e($text($profile['bio'] ?? null)) ?></dd>
                <dt class="<?= $dt ?>">Occupation</dt><dd class="<?= $dd ?>"><?= e($text($profile['occupation'] ?? null)) ?></dd>
                <dt class="<?= $dt ?>">Location</dt><dd class="<?= $dd ?>"><?= e($text($profile['location'] ?? null)) ?></dd>
                <dt class="<?= $dt ?>">Links</dt>
                <dd class="<?= $dd ?>">
                    <?php if ($socialLinks === []) { ?>—<?php } ?>
                    <?php foreach ($socialLinks as $network => $url) { ?>
                    <span class="block"><?= e(ucfirst($network)) ?>: <?= e($url) ?></span>
                    <?php } ?>
                </dd>
                <dt class="<?= $dt ?>">Timezone</dt><dd class="<?= $dd ?>"><?= e($text($preferences['timezone'] ?? null)) ?></dd>
                <dt class="<?= $dt ?>">Language</dt><dd class="<?= $dd ?>"><?= e($text($preferences['locale'] ?? null)) ?></dd>
                <dt class="<?= $dt ?>">Shown as</dt><dd class="<?= $dd ?>"><?= ($preferences['display_name_preference'] ?? 'handle') === 'name' ? 'Their name' : 'Their @tag' ?></dd>
                <?php if ($preferences === null) { ?>
                <dt class="<?= $dt ?>">Preferences</dt><dd class="<?= $dd ?>">Never saved; defaults apply.</dd>
                <?php } ?>
            </dl>
        </div>
    </section>

    <section id="access" class="<?= $card ?>" aria-labelledby="access-h">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 id="access-h" class="<?= $heading ?>">Access</h3>
                <div class="flex flex-wrap gap-2">
                    <?php if ($canAssignSiteRoles) { ?>
                    {% cmp="btn" href="{$base}/site-role" variant="slate" icon="shield" label="Change site role" %}
                    <?php } ?>
                    <?php if ($mayActOnTarget) { ?>
                    {% cmp="btn" href="{$base}/blog-roles" variant="slate" icon="users" label="Change blog roles" %}
                    <?php } ?>
                </div>
            </div>
            <p class="<?= $hint ?> mb-2">Site role</p>
            <?php if ($siteRoles === []) { ?>
            <p class="<?= $empty ?> mb-4">No site role.</p>
            <?php } else { ?>
            <ul class="text-sm mb-4">
                <?php foreach ($siteRoles as $siteRole) { ?>
                <li><?= e($siteRole['role_name']) ?> <span class="<?= $hint ?>">since <?= e($when($siteRole['assigned_at'])) ?><?= $siteRole['assigned_by_handle'] ? ', given by @'.e($siteRole['assigned_by_handle']) : '' ?></span></li>
                <?php } ?>
            </ul>
            <?php } ?>
            <p class="<?= $hint ?> mb-2">Blogs</p>
            <?php if ($blogs === []) { ?>
            <p class="<?= $empty ?>">Not a member of any blog.</p>
            <?php } else { ?>
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="<?= $tableHead ?>"><tr><th scope="col" class="<?= $th ?>">Blog</th><th scope="col" class="<?= $th ?>">Role</th><th scope="col" class="<?= $th ?>">Status</th><th scope="col" class="<?= $th ?>">Posts</th><th scope="col" class="<?= $th ?>">Team</th></tr></thead>
                    <tbody class="<?= $tableBody ?>">
                        <?php foreach ($blogs as $blogRow) {
                            $blogStatus = (string) $blogRow['status']; ?>
                        <tr>
                            <td class="<?= $td ?>"><a class="text-custom-500 hover:underline" href="/admin/blogs/<?= (int) $blogRow['id'] ?>/show"><?= e($blogRow['blog_name']) ?></a></td>
                            <td class="<?= $td ?> capitalize"><?= e($blogRow['user_role']) ?></td>
                            <td class="<?= $td ?>">{% cmp="status-badge" status="{$blogStatus}" %}</td>
                            <td class="<?= $td ?>"><?= (int) $blogRow['post_count'] ?></td>
                            <td class="<?= $td ?>"><?= (int) $blogRow['author_count'] ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </section>

    <section id="content" class="<?= $card ?>" aria-labelledby="content-h">
        <div class="card-body">
            <h3 id="content-h" class="<?= $heading ?> mb-3">Content</h3>
            <dl class="<?= $dl ?> mb-4">
                <dt class="<?= $dt ?>">Posts</dt><dd class="<?= $dd ?>"><?= $counts['posts'] ?? 0 ?> (<?= $counts['published_posts'] ?? 0 ?> published)</dd>
                <dt class="<?= $dt ?>">Comments</dt><dd class="<?= $dd ?>"><?= $counts['comments'] ?? 0 ?> (<?= $counts['hidden_comments'] ?? 0 ?> hidden by suspension)</dd>
            </dl>

            <p class="<?= $hint ?> mb-2">Latest posts</p>
            <?php if ($recentPosts === []) { ?>
            <p class="<?= $empty ?> mb-4">No posts.</p>
            <?php } else { ?>
            <div class="overflow-x-auto mb-4">
                <table class="w-full whitespace-nowrap">
                    <thead class="<?= $tableHead ?>"><tr><th scope="col" class="<?= $th ?>">Title</th><th scope="col" class="<?= $th ?>">Blog</th><th scope="col" class="<?= $th ?>">Status</th><th scope="col" class="<?= $th ?>">Created</th></tr></thead>
                    <tbody class="<?= $tableBody ?>">
                        <?php foreach ($recentPosts as $postRow) {
                            $postStatus = (string) $postRow['status']; ?>
                        <tr>
                            <td class="<?= $td ?>"><a class="text-custom-500 hover:underline" href="/admin/posts/<?= (int) $postRow['id'] ?>/show"><?= e(truncate((string) $postRow['title'], 60)) ?></a></td>
                            <td class="<?= $td ?>"><?= e($text($postRow['blog_name'])) ?></td>
                            <td class="<?= $td ?>">{% cmp="status-badge" status="{$postStatus}" %}</td>
                            <td class="<?= $td ?>"><?= e($when($postRow['created_at'])) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>

            <p class="<?= $hint ?> mb-2">Latest comments, including hidden and removed ones</p>
            <?php if ($recentComments === []) { ?>
            <p class="<?= $empty ?>">No comments.</p>
            <?php } else { ?>
            <ul class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                <?php foreach ($recentComments as $commentRow) {
                    $commentState = $commentRow['deleted_at'] !== null ? 'Removed' : ($commentRow['hidden_at'] !== null ? 'Hidden (suspension)' : ucfirst((string) $commentRow['status'])); ?>
                <li class="py-2">
                    <p class="text-slate-900 dark:text-zink-50"><?= e(truncate((string) $commentRow['content'], 160)) ?></p>
                    <p class="<?= $hint ?>">On “<?= e(truncate((string) $commentRow['post_title'], 60)) ?>” · <?= e($when($commentRow['created_at'])) ?> · <?= e($commentState) ?><?= (int) $commentRow['reports_count'] > 0 ? ' · '.(int) $commentRow['reports_count'].' report(s)' : '' ?></p>
                </li>
                <?php } ?>
            </ul>
            <?php } ?>
        </div>
    </section>

    <?php
    $asAuthor = $moderation['asAuthor'];
    $asReporter = $moderation['asReporter'];
    $authorQueueHref = '/admin/reports?status=all&author='.rawurlencode((string) $user['handle']);
    $reporterHref = '/admin/reports/reporters/'.(int) $user['id'];
    ?>
    <section id="reports" class="<?= $card ?>" aria-labelledby="reports-h">
        <div class="card-body">
            <h3 id="reports-h" class="<?= $heading ?> mb-3">Reports</h3>
            <dl class="<?= $dl ?>">
                <dt class="<?= $dt ?>">Cases about their content</dt>
                <dd class="<?= $dd ?>">
                    <?= $asAuthor['total'] ?> (<?= $asAuthor['open'] ?> open, <?= $asAuthor['upheld'] ?> upheld, <?= $asAuthor['dismissed'] ?> dismissed)
                    <?php if ($canHandleReports && $asAuthor['total'] > 0) { ?> · <a href="<?= e($authorQueueHref) ?>" class="text-custom-500 hover:underline">See the cases</a><?php } ?>
                </dd>
                <dt class="<?= $dt ?>">Warnings from moderators</dt><dd class="<?= $dd ?>"><?= (int) $moderation['warnings'] ?></dd>
                <dt class="<?= $dt ?>">Reports they filed</dt>
                <dd class="<?= $dd ?>">
                    <?= $asReporter['filed'] ?> (<?= $asReporter['upheld'] ?> upheld, <?= $asReporter['dismissed'] ?> dismissed, <?= $asReporter['unfounded'] ?> unfounded, <?= $asReporter['pending'] ?> waiting)
                    <?php if ($canHandleReports) { ?> · <a href="<?= e($reporterHref) ?>" class="text-custom-500 hover:underline">Reporter record</a><?php } ?>
                </dd>
                <dt class="<?= $dt ?>">Reporting</dt>
                <dd class="<?= $dd ?>"><?= $moderation['reportsPausedUntil'] === null ? 'Allowed' : 'Paused until '.e(local_datetime($moderation['reportsPausedUntil'], 'M j, Y')) ?></dd>
            </dl>
        </div>
    </section>

    <section id="moderation" class="<?= $card ?>" aria-labelledby="moderation-h">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 id="moderation-h" class="<?= $heading ?>">Suspensions</h3>
                <?php if ($mayActOnTarget && !$isSelf) { ?>
                <?php $suspendLabel = $isSuspended ? 'Lift suspension' : 'Suspend'; ?>
                {% cmp="btn" href="{$base}/suspend" variant="slate" icon="ban" label="{$suspendLabel}" %}
                <?php } ?>
            </div>
            <?php if ($suspensionHistory === []) { ?>
            <p class="<?= $empty ?>">Never suspended.</p>
            <?php } else { ?>
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="<?= $tableHead ?>"><tr><th scope="col" class="<?= $th ?>">Type</th><th scope="col" class="<?= $th ?>">From</th><th scope="col" class="<?= $th ?>">Until</th><th scope="col" class="<?= $th ?>">By</th><th scope="col" class="<?= $th ?>">Lifted</th><th scope="col" class="<?= $th ?>">Reason</th></tr></thead>
                    <tbody class="<?= $tableBody ?>">
                        <?php foreach ($suspensionHistory as $row) { ?>
                        <tr>
                            <td class="<?= $td ?> capitalize"><?= e($row['type']) ?></td>
                            <td class="<?= $td ?>"><?= e($when($row['suspended_at'])) ?></td>
                            <td class="<?= $td ?>"><?= $row['expires_at'] ? e($when($row['expires_at'])) : 'Permanent' ?></td>
                            <td class="<?= $td ?>"><?= e($suspendedBy($row)) ?></td>
                            <td class="<?= $td ?>"><?= $row['lifted_at'] ? e($when($row['lifted_at'])).' ('.e((string) $row['lift_kind']).($row['lifted_by_handle'] ? ', @'.e($row['lifted_by_handle']) : '').')' : 'In force' ?></td>
                            <td class="px-3.5 py-2.5 whitespace-normal"><?= e($text($row['reason'])) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </section>

    <section id="security" class="<?= $card ?>" aria-labelledby="security-h">
        <div class="card-body">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <h3 id="security-h" class="<?= $heading ?>">Security</h3>
                <div class="flex flex-wrap gap-2">
                    <?php if ($mayActOnTarget && !$isSelf) { ?>
                    {% cmp="btn" href="{$base}/password" variant="slate" icon="key-round" label="Reset password" %}
                    <?php } ?>
                    <?php if ($impersonationRefusal === null) { ?>
                    {% cmp="btn" href="{$base}/impersonate" variant="slate" icon="log-in" label="Log in as" %}
                    <?php } ?>
                </div>
            </div>
            <dl class="<?= $dl ?> mb-4">
                <dt class="<?= $dt ?>">Pending email change</dt>
                <dd class="<?= $dd ?>"><?= $pendingEmail ? e($pendingEmail['new_email']).((int) $pendingEmail['is_expired'] === 1 ? ' (link expired)' : ' (awaiting confirmation)') : '—' ?></dd>
                <dt class="<?= $dt ?>">Scheduled deletion</dt>
                <dd class="<?= $dd ?>"><?= $pendingErasure ? 'Requested by them, due '.e($when($pendingErasure['scheduled_for'] ?? null)) : '—' ?></dd>
                <?php if ($impersonationRefusal !== null) { ?>
                <dt class="<?= $dt ?>">Log in as</dt><dd class="<?= $dd ?>"><?= e($impersonationRefusal) ?></dd>
                <?php } ?>
            </dl>
            <p class="<?= $hint ?> mb-2">Impersonation sessions, as the administrator or as the account signed in as</p>
            <?php if ($impersonations === []) { ?>
            <p class="<?= $empty ?>">None.</p>
            <?php } else { ?>
            <div class="overflow-x-auto">
                <table class="w-full whitespace-nowrap">
                    <thead class="<?= $tableHead ?>"><tr><th scope="col" class="<?= $th ?>">Administrator</th><th scope="col" class="<?= $th ?>">Signed in as</th><th scope="col" class="<?= $th ?>">Started</th><th scope="col" class="<?= $th ?>">Ended</th><th scope="col" class="<?= $th ?>">IP</th><th scope="col" class="<?= $th ?>">Reason</th></tr></thead>
                    <tbody class="<?= $tableBody ?>">
                        <?php foreach ($impersonations as $row) { ?>
                        <tr>
                            <td class="<?= $td ?>">@<?= e($text($row['admin_handle'] ?? '#'.$row['admin_id'])) ?></td>
                            <td class="<?= $td ?>">@<?= e($text($row['target_handle'] ?? '#'.$row['target_user_id'])) ?></td>
                            <td class="<?= $td ?>"><?= e($when($row['started_at'])) ?></td>
                            <td class="<?= $td ?>"><?= $row['ended_at'] ? e($when($row['ended_at'])).' ('.e((string) $row['end_kind']).')' : 'Not ended cleanly' ?></td>
                            <td class="<?= $td ?>"><?= e($text($row['ip_address'])) ?></td>
                            <td class="px-3.5 py-2.5 whitespace-normal"><?= e($text($row['reason'])) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>
    </section>

    <section id="activity" class="<?= $card ?>" aria-labelledby="activity-h">
        <div class="card-body">
            <h3 id="activity-h" class="<?= $heading ?> mb-1">Activity</h3>
            <p class="<?= $hint ?> mb-3">The latest 50 audit entries in each direction. Rows marked “as @…” were done by an administrator while signed in as someone.</p>
            <?php foreach (['What they did' => $activityByUser, 'What was done to this account' => $activityOnUser] as $activityTitle => $activityRows) { ?>
            <p class="<?= $hint ?> mb-2"><?= e($activityTitle) ?></p>
            <?php if ($activityRows === []) { ?>
            <p class="<?= $empty ?> mb-4">Nothing recorded.</p>
            <?php } else { ?>
            <div class="overflow-x-auto mb-4">
                <table class="w-full whitespace-nowrap">
                    <thead class="<?= $tableHead ?>"><tr><th scope="col" class="<?= $th ?>">When</th><th scope="col" class="<?= $th ?>">By</th><th scope="col" class="<?= $th ?>">Action</th><th scope="col" class="<?= $th ?>">Target</th><th scope="col" class="<?= $th ?>">IP</th></tr></thead>
                    <tbody class="<?= $tableBody ?>">
                        <?php foreach ($activityRows as $row) {
                            $details = json_decode((string) ($row['details'] ?? ''), true);
                            $actingAs = is_array($details) && isset($details['acting_as']) ? (int) $details['acting_as'] : null; ?>
                        <tr>
                            <td class="<?= $td ?>"><?= e($when($row['created_at'])) ?></td>
                            <td class="<?= $td ?>">@<?= e($text($row['actor_handle'] ?? 'system')) ?><?= $actingAs !== null ? ' <span class="'.$hint.'">as #'.$actingAs.'</span>' : '' ?></td>
                            <td class="<?= $td ?>"><?= e($row['action']) ?></td>
                            <td class="<?= $td ?>"><?= e($text($row['resource_type'])) ?><?= $row['resource_id'] !== null ? ' #'.(int) $row['resource_id'] : '' ?></td>
                            <td class="<?= $td ?>"><?= e($text($row['ip_address'])) ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
            <?php } ?>
        </div>
    </section>

    <?php if ($mayActOnTarget && !$isSelf) { ?>
    <div class="flex justify-end mb-5">
        {% cmp="btn" href="{$base}/delete" variant="red" icon="trash-2" label="Delete user" %}
    </div>
    <?php } ?>
</div>
{% endblock %}
