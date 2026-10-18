{% extends "back.lex.php" %}

{% block title %}Rate Limits{% endblock %}
{% block subtitle %}Emails and IP addresses currently locked out or blocked.{% endblock %}

{% block body %}
<?php
// Waits are shown in whole minutes, or hours and days once they are long, because that is how a person reads them
$timeLeft = static function (int $seconds): string {
    if ($seconds >= 86400) {
        return (int) ceil($seconds / 86400).' d';
    }
    if ($seconds >= 7200) {
        return (int) ceil($seconds / 3600).' h';
    }

    return max(1, (int) ceil($seconds / 60)).' min';
};
$durationChoices = array_map(static fn (array $d): string => $d['label'], $durations);
$pill = 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
        <p class="text-sm text-slate-500 dark:text-zink-300">
            Limiters lock people out after repeated failed attempts. You can also block someone by hand.
        </p>
        <div class="flex flex-wrap gap-2">
            {% cmp="btn" type="button" variant="red" icon="ban" label="Block email or IP" dataModalTarget="blockSubjectModal" %}
            {% cmp="btn" type="button" variant="blue" icon="unlock" label="Unlock email or IP" dataModalTarget="unlockSubjectModal" %}
        </div>
    </div>

    {% if blocked|empty %}
        {% cmp="empty-state" icon="shield-check" title="Nobody is locked out" message="Anyone a limiter locks out, or you block, will be listed here." %}
    {% else %}
    <div class="bg-white dark:bg-zink-700 border border-slate-200 dark:border-zink-500 rounded-lg shadow-md">
        <div class="max-h-[28rem] overflow-auto">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10 bg-white dark:bg-zink-700 shadow-sm">
                    <tr class="text-left text-slate-500 dark:text-zink-300 border-b border-slate-200 dark:border-zink-500">
                        <th class="h-12 px-4 font-medium">Who</th>
                        <th class="h-12 px-4 font-medium">Where</th>
                        <th class="h-12 px-4 font-medium">Status</th>
                        <th class="h-12 px-4 font-medium">Since</th>
                        <th class="h-12 px-4 font-medium text-right">Time left</th>
                        <th class="h-12 px-4 font-medium text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($blocked as $i => $row) {
                    $rowUnlockAttrs = [
                        'form' => 'unlockRowForm'.$i,
                        'data-confirm' => 'Lifts every lock and counter held against '.$row['subject'].'. They can try again straight away.',
                        'data-confirm-title' => 'Unlock this '.($row['kind'] === 'ip' ? 'IP' : 'email').'?',
                        'data-confirm-ok' => 'Unlock',
                        'data-confirm-tone' => 'default',
                    ];
                    ?>
                    <tr class="border-b border-slate-100 dark:border-zink-600 last:border-0 transition-colors hover:bg-slate-50 dark:hover:bg-zink-600/50">
                        <td class="p-4 align-middle">
                            <div class="font-medium font-mono"><?= e($row['subject']) ?></div>
                            <div class="text-xs text-slate-500 dark:text-zink-300"><?= $row['kind'] === 'ip' ? 'IP address' : 'Email' ?></div>
                        </td>
                        <td class="p-4 align-middle"><?= e($row['label']) ?></td>
                        <td class="p-4 align-middle">
                            <?php if ($row['manual']) { ?>
                            <span class="<?= $pill ?> bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400">Blocked by an admin</span>
                            <?php } elseif ($row['challenge']) { ?>
                            <span class="<?= $pill ?> bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-300" title="Sign-in still works after a quick browser check">Must pass the sign-in check</span>
                            <?php } else { ?>
                            <span class="<?= $pill ?> bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">Locked out</span>
                            <?php } ?>
                        </td>
                        <td class="p-4 align-middle"><?= e(date('Y-m-d H:i', $row['locked_at'])) ?></td>
                        <td class="p-4 align-middle text-right"><?= e($timeLeft($row['seconds_left'])) ?></td>
                        <td class="p-4 align-middle text-center">
                            {% cmp="btn" type="submit" variant="slate" icon="unlock" label="Unlock" attrs="{$rowUnlockAttrs}" %}
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 text-sm font-medium border-t border-slate-200 dark:border-zink-500 rounded-b-lg bg-slate-50 dark:bg-zink-600/40">
            Total: <?= count($blocked) ?> <?= count($blocked) === 1 ? 'entry' : 'entries' ?>
        </div>
    </div>
    {% endif %}
</div>

<?php foreach ($blocked as $i => $row) {
    $rowForm = 'unlockRowForm'.$i;
    ?>
<form method="POST" action="/admin/rate-limits/unlock" id="<?= e($rowForm) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="subject" value="<?= e($row['subject']) ?>">
</form>
<?php } ?>

<?php ob_start(); ?>
<form method="POST" action="/admin/rate-limits/unlock" id="unlockSubjectForm" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <p class="text-sm text-slate-500 dark:text-zink-300">
        Clears every lock and counter held against it, across login, password reset and comments.
        This also lifts short blocks that are not listed.
    </p>
    {% cmp="input" type="text" name="subject" label="Email or IP" placeholder="person@example.com or 203.0.113.7" required="1" %}
</form>
<?php $unlockBody = ob_get_clean(); ?>
{% cmp="modal" id="unlockSubjectModal" title="Unlock email or IP" icon="unlock" body="{$unlockBody}" form="unlockSubjectForm" confirmText="Unlock" confirmIcon="unlock" %}

<?php ob_start(); ?>
<form method="POST" action="/admin/rate-limits/block" id="blockSubjectForm" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <p class="text-sm text-slate-500 dark:text-zink-300">
        An email cannot log in or reset its password. An IP address is refused on every page of the site.
    </p>
    {% cmp="input" type="text" name="block_subject" label="Email or IP" placeholder="person@example.com or 203.0.113.7" required="1" %}
    {% cmp="select" name="duration" label="For how long" options="{$durationChoices}" selectedKey="24h" %}
</form>
<?php $blockBody = ob_get_clean(); ?>
{% cmp="modal" id="blockSubjectModal" title="Block email or IP" icon="ban" variant="danger" body="{$blockBody}" form="blockSubjectForm" confirmText="Block" confirmIcon="ban" %}
{% endblock %}

{% block scripts %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<script src="/cp-assets/js/modal.js"></script>
{% endblock %}
