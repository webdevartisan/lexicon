{% extends "back.lex.php" %}

{% block title %}Mail Queue{% endblock %}
{% block subtitle %}Outbound email waiting on the delivery worker, and anything that failed along the way.{% endblock %}

{% block body %}
<?php
$basePath = '/admin/mail-queue';

// Tiles double as status filters, so each one carries the query it applies
$tiles = [
    ['status' => 'pending', 'label' => 'Pending', 'icon' => 'clock'],
    ['status' => 'sending', 'label' => 'Sending', 'icon' => 'send'],
    ['status' => 'sent', 'label' => 'Sent', 'icon' => 'check-circle'],
    ['status' => 'failed', 'label' => 'Failed', 'icon' => 'alert-circle'],
];

// Relative wording reads better than a timestamp for something due imminently
$dueIn = static function (?string $when): string {
    if (empty($when)) {
        return '—';
    }

    $seconds = strtotime($when) - time();

    if ($seconds <= 0) {
        return 'due now';
    }
    if ($seconds < 60) {
        return 'in '.$seconds.'s';
    }
    if ($seconds < 3600) {
        return 'in '.(int) round($seconds / 60).'m';
    }

    return 'in '.(int) round($seconds / 3600).'h';
};
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <?php if (!$mailEnabled) { ?>
    <div class="flex items-start gap-3 p-4 mb-4 rounded-md bg-amber-50 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/30">
        {% cache 'lucide:alert-triangle:mailoff' ttl=31536000 %}<i data-lucide="alert-triangle" class="size-5 text-amber-600 dark:text-amber-300 shrink-0 mt-0.5"></i>{% endcache %}
        <div class="text-sm">
            <p class="font-medium text-amber-800 dark:text-amber-200">Outgoing mail is switched off</p>
            <p class="text-amber-700 dark:text-amber-300 mt-0.5">
                <code>MAIL_ENABLED</code> is false, so the worker leaves the queue untouched rather than
                spending delivery attempts. Queued mail waits here until it is switched back on.
            </p>
        </div>
    </div>
    <?php } ?>

    <!-- Status summary, each tile filters the table below -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
        <?php foreach ($tiles as $tile) {
            $isActive = $statusFilter === $tile['status'];
            $count = (int) ($counts[$tile['status']] ?? 0);
            $isTrouble = $tile['status'] === 'failed' && $count > 0;
            ?>
        <a href="<?= e($basePath.'?status='.$tile['status']) ?>"
           class="p-4 rounded-md border transition-colors <?= $isActive
               ? 'bg-custom-50 border-custom-300 dark:bg-custom-500/10 dark:border-custom-500/40'
               : 'bg-slate-50 border-slate-200 hover:bg-slate-100 dark:bg-zink-600 dark:border-zink-500 dark:hover:bg-zink-500' ?>">
            <div class="flex items-center justify-between">
                <span class="text-2xl font-semibold <?= $isTrouble ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-zink-50' ?>">
                    <?= number_format($count) ?>
                </span>
                {% cache 'lucide:mailqueue:' . $tile['icon'] ttl=31536000 %}<i data-lucide="<?= e($tile['icon']) ?>" class="size-4 text-slate-400"></i>{% endcache %}
            </div>
            <div class="text-xs text-slate-500 dark:text-zink-300 mt-1"><?= e($tile['label']) ?></div>
        </a>
        <?php } ?>
    </div>

    <!-- Filters and bulk actions -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <?php
                // The select component maps a flat list to value => Ucfirst labels;
                // prepend the "all" sentinel so it reads as a real filter option.
                $statusChoices = ['' => 'All statuses'];
foreach ($statusOptions as $opt) {
    $statusChoices[$opt] = ucfirst($opt);
}
?>
                <form method="GET" action="<?= e($basePath) ?>" class="flex flex-col sm:flex-row sm:items-center gap-3 grow">
                    {% cmp="select" name="status" options="{$statusChoices}" selectedKey="{$statusFilter}" onchange="this.form.submit()" %}
                    <div class="grow sm:max-w-xs">
                        {% cmp="input" type="search" name="q" value="{$searchFilter}" placeholder="Search recipient…" %}
                    </div>
                    {% cmp="btn" type="submit" variant="blue" icon="search" label="Search" %}
                    <?php if ($statusFilter !== '' || $searchFilter !== '') { ?>
                    {% cmp="btn" href="{$basePath}" variant="slate" icon="x" label="Clear" %}
                    <?php } ?>
                </form>

                <div class="flex flex-wrap gap-2 lg:justify-end shrink-0">
                    <?php if ((int) ($counts['failed'] ?? 0) > 0) { ?>
                    <form method="POST" action="<?= buildLocalizedUrl($basePath.'/retry-all') ?>"
                          onsubmit="return confirm('Requeue every failed email? They will go out on the next worker run.')">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="yellow" icon="refresh-cw" label="Retry all failed" %}
                    </form>
                    <?php } ?>

                    <?php if ((int) ($counts['sent'] ?? 0) > 0) { ?>
                    <form method="POST" action="<?= buildLocalizedUrl($basePath.'/prune') ?>"
                          onsubmit="return confirm('Delete delivered emails older than 30 days? Pending and failed mail is untouched.')">
                        {{ csrf_field() }}
                        {% cmp="btn" type="submit" variant="slate" icon="trash-2" label="Prune delivered" %}
                    </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    {% if entries|empty %}
        {% cmp="empty-state" icon="mail-check" title="Nothing in the queue" message="Subscriber announcements appear here when a post is published, and clear as the delivery worker sends them." %}
    {% else %}
    <div class="card">
        <div class="card-body p-0 overflow-x-auto">
            <table class="w-full whitespace-nowrap">
                <thead class="text-left bg-slate-100 dark:bg-zink-600">
                    <tr class="text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                        <th class="px-3.5 py-2.5 font-semibold">ID</th>
                        <th class="px-3.5 py-2.5 font-semibold">Recipient</th>
                        <th class="px-3.5 py-2.5 font-semibold">Subject</th>
                        <th class="px-3.5 py-2.5 font-semibold">Status</th>
                        <th class="px-3.5 py-2.5 font-semibold">Attempts</th>
                        <th class="px-3.5 py-2.5 font-semibold">Queued</th>
                        <th class="px-3.5 py-2.5 font-semibold">Next / Sent</th>
                        <th class="px-3.5 py-2.5 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-zink-600 text-sm">
                    {% foreach ($entries as $entry): %}
                    <?php
        $entryStatus = (string) $entry['status'];
$attempts = (int) $entry['attempts'];
$maxAttempts = (int) $entry['max_attempts'];
$isFailed = $entryStatus === 'failed';
$error = (string) ($entry['last_error'] ?? '');
?>
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-zink-700/40 transition-colors align-top">
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300"><?= (int) $entry['id'] ?></td>
                        <td class="px-3.5 py-2.5 font-medium text-slate-900 dark:text-zink-50">
                            <?= e((string) $entry['to_email']) ?>
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300 whitespace-normal max-w-xs">
                            <?= e(truncate((string) $entry['subject'], 70)) ?>
                            <?php if ($entry['related_type'] !== null) { ?>
                            <span class="block text-xs text-slate-400 dark:text-zink-400 mt-0.5">
                                <?= e((string) $entry['related_type']) ?> #<?= (int) $entry['related_id'] ?>
                            </span>
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5">
                            {% cmp="status-badge" status="{$entryStatus}" %}
                        </td>
                        <td class="px-3.5 py-2.5 text-slate-500 dark:text-zink-300">
                            <span class="<?= $isFailed ? 'text-red-600 dark:text-red-400 font-medium' : '' ?>">
                                <?= $attempts ?> / <?= $maxAttempts ?>
                            </span>
                        </td>
                        <td class="px-3.5 py-2.5 text-xs text-slate-500 dark:text-zink-300">
                            <?= e(date('M j, g:i a', strtotime((string) $entry['created_at']))) ?>
                        </td>
                        <td class="px-3.5 py-2.5 text-xs text-slate-500 dark:text-zink-300">
                            <?php if ($entryStatus === 'sent') { ?>
                                <?= e(date('M j, g:i a', strtotime((string) $entry['sent_at']))) ?>
                            <?php } elseif ($entryStatus === 'pending') { ?>
                                <?= e($dueIn((string) $entry['next_attempt_at'])) ?>
                            <?php } else { ?>
                                —
                            <?php } ?>
                        </td>
                        <td class="px-3.5 py-2.5">
                            <div class="flex items-center justify-end gap-1">
                                <?php if ($isFailed) { ?>
                                <form method="POST" action="<?= buildLocalizedUrl($basePath.'/'.(int) $entry['id'].'/retry') ?>" class="m-0">
                                    {{ csrf_field() }}
                                    <button type="submit"
                                            data-tooltip data-tooltip-content="Queue this email again" data-tooltip-placement="top"
                                            aria-label="Queue this email again"
                                            class="p-2 rounded-md text-slate-500 hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10 transition-colors">
                                        {% cache 'lucide:refresh-cw:one' ttl=31536000 %}<i data-lucide="refresh-cw" class="size-4"></i>{% endcache %}
                                    </button>
                                </form>
                                <?php } elseif ($entryStatus === 'sent') { ?>
                                {% cache 'lucide:check-mq' ttl=31536000 %}<i data-lucide="check" class="size-4 text-green-500" aria-label="Delivered"></i>{% endcache %}
                                <?php } else { ?>
                                <?php // pending or sending: nothing to do, the worker owns it?>
                                <span class="text-slate-400 dark:text-zink-300 text-xs italic">waiting for worker</span>
                                <?php } ?>
                            </div>
                        </td>
                    </tr>
                    <?php if ($isFailed && $error !== '') { ?>
                    <tr class="bg-red-50/50 dark:bg-red-900/10">
                        <td colspan="8" class="px-3.5 pb-2.5 pt-0 text-xs text-red-700 dark:text-red-300 whitespace-normal break-words">
                            <?= e(truncate($error, 240)) ?>
                        </td>
                    </tr>
                    <?php } ?>
                    {% endforeach; %}
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        {% cmp="paginator" pagination="{$pagination}" pageParam="page" basePath="{$basePath}" itemSingular="email" itemPlural="emails" %}
    </div>
    {% endif %}
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/tooltip.js"></script>
{% endblock %}
