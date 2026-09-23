{% extends "back.lex.php" %}

{% block title %}Notifications{% endblock %}
{% block subtitle %}Platform-operations events: report thresholds, mail delivery failures, a stalled scheduler.{% endblock %}

{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
    <div class="grid grid-cols-1 gap-6">
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
            <div class="flex items-center justify-between p-4 border-b border-slate-200 dark:border-zink-600">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">Admin notifications</h2>
                    <p class="text-xs text-slate-500 dark:text-zink-300 mt-0.5">
                        <?= (int) $total ?> total · <?= (int) $unreadCount ?> unread
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <?php if ($unreadCount > 0) { ?>
                    <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/read-all')) ?>">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="slate" icon="check-check" label="Mark all read" %}
                    </form>
                    <?php } ?>
                    <?php if ($total > 0) { ?>
                    <form method="post" action="<?= e(buildLocalizedUrl($listPath.'/clear-all')) ?>"
                        data-confirm="Clear all admin notifications? This cannot be undone.">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="red" icon="trash-2" label="Clear all" %}
                    </form>
                    <?php } ?>
                </div>
            </div>

            <?php if (empty($notificationRows)) { ?>
            <div class="text-center py-12 px-4">
                <i data-lucide="shield-check" class="size-10 text-slate-400 mx-auto mb-3"></i>
                <p class="text-sm text-slate-500 dark:text-zink-300">Nothing needs attention right now.</p>
            </div>
            <?php } else { ?>
            <div class="divide-y divide-slate-100 dark:divide-zink-500">
                <?php foreach ($notificationRows as $n) { ?>
                    {% include "partials/_notification_item.lex.php" %}
                <?php } ?>
            </div>

            <?php
            $totalPages = (int) ceil($total / max(1, $perPage));
                if ($totalPages > 1) {
                    ?>
            <div class="flex items-center justify-between p-4 border-t border-slate-200 dark:border-zink-600">
                <p class="text-xs text-slate-500 dark:text-zink-300">
                    Page <?= (int) $page ?> of <?= $totalPages ?>
                </p>
                <div class="flex gap-2">
                    <?php if ($page > 1) { ?>
                    <a href="<?= e(lurl($listPath.'?page='.($page - 1))) ?>"
                       class="px-3 py-1.5 text-xs border border-slate-200 dark:border-zink-500 rounded hover:bg-slate-50 dark:hover:bg-zink-600">
                        Previous
                    </a>
                    <?php } ?>
                    <?php if ($page < $totalPages) { ?>
                    <a href="<?= e(lurl($listPath.'?page='.($page + 1))) ?>"
                       class="px-3 py-1.5 text-xs border border-slate-200 dark:border-zink-500 rounded hover:bg-slate-50 dark:hover:bg-zink-600">
                        Next
                    </a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
            <?php } ?>
        </section>
    </div>
</div>
{% endblock %}
