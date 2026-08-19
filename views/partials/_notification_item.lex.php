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
    'comment.reply' => ($payload['commenter_name'] ?? 'Someone').' replied to your comment on '.($payload['post_title'] ?? 'a post'),
    'comment.on_your_post' => ($payload['commenter_name'] ?? 'A reader').' commented on your post '.($payload['post_title'] ?? ''),
    'comment.awaiting_moderation' => 'A comment needs approval on '.($payload['post_title'] ?? 'a post'),
    // comment.created rows predate the split into four types
    'comment.on_blog', 'comment.created' => ($payload['commenter_name'] ?? 'A reader').' commented on '.($payload['post_title'] ?? 'a post')
        .(!empty($payload['awaiting_moderation']) ? ' (awaiting moderation)' : ''),
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
    'comment.reply' => 'reply',
    'comment.awaiting_moderation' => 'shield-alert',
    'comment.on_your_post', 'comment.on_blog', 'comment.created' => 'message-circle',
    default => 'bell',
};

$color = match (true) {
    in_array($type, ['post.approved', 'post.published'], true) => 'bg-emerald-50 text-emerald-500',
    in_array($type, ['post.needs_changes', 'post.workflow_disabled', 'comment.awaiting_moderation'], true) => 'bg-amber-50 text-amber-600',
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
    // Moderation alerts land on the queue; the comment isn't public yet
    'comment.awaiting_moderation' => '/dashboard/blog/'.(int) ($payload['blog_id'] ?? 0).'/comments',
    // Older comment.created rows predate the split and may still be pending
    'comment.reply', 'comment.on_your_post', 'comment.on_blog', 'comment.created' => !empty($payload['awaiting_moderation'])
        ? '/dashboard/blog/'.(int) ($payload['blog_id'] ?? 0).'/comments'
        : '/blog/'.rawurlencode((string) ($payload['blog_slug'] ?? '')).'/'.rawurlencode((string) ($payload['post_slug'] ?? ''))
            .(!empty($payload['comment_id']) ? '#comment-'.(int) $payload['comment_id'] : ''),
    default => '/dashboard/notifications',
};
?>
<?php /* Two sibling forms rather than one: a nested form would be invalid HTML,
         and the row itself has to stay a submit button for the mark-read click-through. */ ?>
<div class="flex items-stretch border-b border-slate-100 dark:border-zink-500 last:border-b-0 <?= $isUnread ? 'bg-sky-50/40 dark:bg-sky-900/10' : '' ?>">
    <form method="post" action="/dashboard/notifications/<?= (int) $n['id'] ?>/read" class="grow min-w-0">
        {{ csrf_field() }}
        <input type="hidden" name="target" value="<?= e($href) ?>">
        <button type="submit" class="w-full h-full text-left flex gap-3 p-3 hover:bg-slate-50 dark:hover:bg-zink-500">
            <div class="flex items-center justify-center size-9 rounded-md <?= $color ?> shrink-0">
                {% cache 'lucide:notif-icon:' . $icon ttl=31536000 %}<i data-lucide="<?= e($icon) ?>" class="size-4"></i>{% endcache %}
            </div>
            <div class="grow min-w-0">
                <p class="text-sm text-slate-900 dark:text-zink-50 truncate"><?= e($label) ?></p>
                <p class="text-[11px] text-slate-400 dark:text-zink-300 mt-1">
                    {% cache 'lucide:clock:notif' ttl=31536000 %}<i data-lucide="clock" class="inline-block size-3 mr-1"></i>{% endcache %}
                    <?= e(local_datetime($n['created_at'] ?? null, 'M j, Y · g:i a')) ?>
                </p>
            </div>
            <?php if ($isUnread) { ?>
            <span class="inline-block size-2 rounded-full bg-sky-500 self-center shrink-0" title="Unread"></span>
            <?php } ?>
        </button>
    </form>

    <form method="post" action="/dashboard/notifications/<?= (int) $n['id'] ?>/delete" class="flex items-center shrink-0 ltr:pr-2 rtl:pl-2">
        {{ csrf_field() }}
        <button type="submit" title="Remove this notification" aria-label="Remove this notification"
            class="p-2 rounded-md text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
            {% cache 'lucide:notif-dismiss' ttl=31536000 %}<i data-lucide="x" class="size-4"></i>{% endcache %}
        </button>
    </form>
</div>
