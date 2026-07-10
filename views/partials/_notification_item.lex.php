<?php
$payload = json_decode((string) ($n['data'] ?? '{}'), true) ?: [];
$type = (string) ($n['type'] ?? '');
$isUnread = empty($n['read_at']);

// Per-type label, icon, color, and target link.
$label = match ($type) {
    'blog.invite' => 'You were invited to a blog as '.($payload['role'] ?? ''),
    'blog.invite_declined' => ($payload['declined_email'] ?? 'Someone').' declined an invite',
    'post.submitted' => 'A post was submitted for your review',
    'post.submitted_unassigned' => 'A post was submitted for review',
    'post.approved' => 'Your post '.($payload['post_title'] ?? '').' was approved',
    'post.needs_changes' => 'Changes requested on '.($payload['post_title'] ?? ''),
    'post.published' => 'Your post '.($payload['post_title'] ?? '').' is live',
    'post.reviewer_assigned' => 'You were assigned to review a post',
    'post.reviewer_stale' => 'Reviewer reset on a post',
    'post.workflow_disabled' => 'A post of yours was reset to draft',
    'collaborator.role_changed' => 'Your role on '.($payload['blog_name'] ?? '').' changed',
    'collaborator.removed' => 'You were removed from '.($payload['blog_name'] ?? ''),
    default => $type,
};

$icon = match ($type) {
    'blog.invite', 'blog.invite_declined' => 'mail',
    'post.submitted', 'post.submitted_unassigned' => 'send',
    'post.approved' => 'check-circle',
    'post.needs_changes' => 'pen-line',
    'post.published' => 'megaphone',
    'post.reviewer_assigned' => 'user-check',
    'post.reviewer_stale', 'post.workflow_disabled' => 'rotate-ccw',
    'collaborator.role_changed', 'collaborator.removed' => 'users',
    default => 'bell',
};

$color = match (true) {
    in_array($type, ['post.approved', 'post.published'], true) => 'bg-emerald-50 text-emerald-500',
    in_array($type, ['post.needs_changes', 'post.workflow_disabled'], true) => 'bg-amber-50 text-amber-600',
    in_array($type, ['collaborator.removed'], true) => 'bg-red-50 text-red-500',
    default => 'bg-sky-50 text-sky-500',
};

$href = match ($type) {
    'post.submitted', 'post.submitted_unassigned', 'post.reviewer_assigned',
    'post.reviewer_stale' => '/dashboard/post/'.(int) ($payload['post_id'] ?? 0).'/review',
    'post.approved', 'post.needs_changes' => '/dashboard/post/'.(int) ($payload['post_id'] ?? 0).'/edit',
    'post.workflow_disabled' => '/dashboard/post/'.(int) ($payload['post_id'] ?? 0).'/edit',
    'post.published' => '/blog/'.rawurlencode((string) ($payload['blog_slug'] ?? '')).'/'.rawurlencode((string) ($payload['post_slug'] ?? '')),
    'blog.invite' => '/invite/'.rawurlencode((string) ($payload['token'] ?? '')),
    default => '/dashboard/notifications',
};
?>
<form method="post" action="/dashboard/notifications/<?= (int) $n['id'] ?>/read" class="block border-b border-slate-100 dark:border-zink-500 last:border-b-0">
    {{ csrf_field() }}
    <input type="hidden" name="target" value="<?= e($href) ?>">
    <button type="submit" class="w-full text-left flex gap-3 p-3 hover:bg-slate-50 dark:hover:bg-zink-500 <?= $isUnread ? 'bg-sky-50/40 dark:bg-sky-900/10' : '' ?>">
        <div class="flex items-center justify-center size-9 rounded-md <?= $color ?> shrink-0">
            {% cache 'lucide:notif-icon:' . $icon ttl=3600 %}<i data-lucide="<?= e($icon) ?>" class="size-4"></i>{% endcache %}
        </div>
        <div class="grow min-w-0">
            <p class="text-sm text-slate-900 dark:text-zink-50 truncate"><?= e($label) ?></p>
            <p class="text-[11px] text-slate-400 dark:text-zink-300 mt-1">
                {% cache 'lucide:clock:notif' ttl=3600 %}<i data-lucide="clock" class="inline-block size-3 mr-1"></i>{% endcache %}
                <?= e(date('M j, Y · g:i a', strtotime((string) ($n['created_at'] ?? 'now')))) ?>
            </p>
        </div>
        <?php if ($isUnread) { ?>
        <span class="inline-block size-2 rounded-full bg-sky-500 self-center shrink-0" title="Unread"></span>
        <?php } ?>
    </button>
</form>
