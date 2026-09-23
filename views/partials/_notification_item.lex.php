<?php
// One row of a control panel notification list. $n is a presented item from
// NotificationPresenter, so the type-by-type rules (what it says, which icon,
// where it goes) live there and this only dresses them for the back office.
$listPath = $listPath ?? '/dashboard/notifications';

$tone = match ($n['tone']) {
    'positive' => 'bg-emerald-50 text-emerald-500',
    'attention' => 'bg-amber-50 text-amber-600',
    'critical' => 'bg-red-50 text-red-500',
    default => 'bg-sky-50 text-sky-500',
};
?>
<div class="flex items-stretch border-b border-slate-100 dark:border-zink-500 last:border-b-0 <?= $n['isUnread'] ? 'bg-sky-50/40 dark:bg-sky-900/10' : '' ?>">
    <?php
    // A link, not a form: opening one marks it read on the way through, and
    // the server works out where it goes from the row itself.
    ?>
    <a href="<?= e(lurl($listPath.'/'.$n['id'].'/open')) ?>" rel="nofollow"
       class="grow min-w-0 flex gap-3 p-3 hover:bg-slate-50 dark:hover:bg-zink-500">
        <span class="flex items-center justify-center size-9 rounded-md shrink-0 <?= $tone ?>">
            {% cache 'lucide:notif-icon:' . $n['icon'] ttl=31536000 %}<i data-lucide="<?= e($n['icon']) ?>" class="size-4"></i>{% endcache %}
        </span>
        <span class="grow min-w-0">
            <span class="block text-sm text-slate-900 dark:text-zink-50 truncate"><?= e($n['title']) ?></span>
            <?php if ($n['message'] !== '') { ?>
            <span class="block text-xs text-slate-500 dark:text-zink-300 mt-1 line-clamp-2"><?= e($n['message']) ?></span>
            <?php } ?>
            <span class="block text-[11px] text-slate-400 dark:text-zink-300 mt-1">
                <?= e(local_datetime($n['createdAt'], 'M j, Y · g:i a')) ?>
            </span>
        </span>
        <?php if ($n['isUnread']) { ?>
        <span class="inline-block size-2 rounded-full bg-sky-500 self-center shrink-0" title="Unread"></span>
        <?php } ?>
    </a>

    <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/'.$n['id'].'/delete')) ?>" class="flex items-center shrink-0 ltr:pr-2 rtl:pl-2">
        {{ csrf_field() }}
        <button type="submit" title="Remove this notification" aria-label="Remove this notification"
            class="p-2 rounded-md text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
            {% cache 'lucide:notif-dismiss' ttl=31536000 %}<i data-lucide="x" class="size-4"></i>{% endcache %}
        </button>
    </form>
</div>
