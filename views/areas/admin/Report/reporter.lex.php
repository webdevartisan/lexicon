{% extends "back.lex.php" %}

{% block title %}Reporter @<?= e((string) $reporter['handle']) ?>{% endblock %}
{% block subtitle %}Everything this person has reported, how it turned out, and what moderators have done about it.{% endblock %}

{% block body %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<?php
$muted = 'text-sm text-slate-500 dark:text-zink-300';
$small = 'text-xs text-slate-400 dark:text-zink-400';
$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 w-full';
$labelClass = 'inline-block mb-1 text-sm font-medium';
$base = '/admin/reports/reporters/'.(int) $reporter['id'];

$outcomeLabels = ['pending' => 'Waiting', 'upheld' => 'Upheld', 'dismissed' => 'Dismissed', 'unfounded' => 'Unfounded'];
$notCounted = ['new_account' => 'new account', 'unfounded_history' => 'unfounded history', 'filed_before_rules' => 'filed before the rules'];
$eventLabels = [
    'moderation.reporter_warned' => 'Warned about unfounded reports',
    'moderation.reporting_paused' => 'Reporting paused',
    'moderation.reporting_resumed' => 'Pause ended early',
];

$decided = $summary['upheld'] + $summary['dismissed'] + $summary['unfounded'];
$unfoundedShare = $decided > 0 ? (int) round($summary['unfounded'] / $decided * 100) : null;

$decision = (string) old('_decision', '');
$reopenWarn = $decision === 'warn';
$reopenPause = $decision === 'pause';
$reopenResume = $decision === 'resume';

// The page-level error list sits behind the reopened dialog, so the dialog repeats it
$dialogErrors = '';
if ($decision !== '' && !empty($errors)) {
    $items = array_map(static fn ($error): string => '<li>'.e(is_array($error) ? implode(' ', $error) : $error).'</li>', (array) $errors);
    $dialogErrors = '<div role="alert" class="px-4 py-3 text-sm text-red-600 border border-red-200 rounded-md bg-red-50 dark:bg-red-500/10 dark:border-red-500/40 dark:text-red-300"><ul class="list-disc ltr:pl-5 rtl:pr-5">'
        .implode('', $items).'</ul></div>';
}
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
    {% include "areas/admin/_errors.lex.php" %}

    <?php if ($pausedUntil !== null) { ?>
    <div class="px-4 py-3 mb-4 text-sm border rounded-md border-amber-200 bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:border-amber-500/40 dark:text-amber-200">
        Their reports are paused until <?= e(local_datetime($pausedUntil, 'M j, Y g:i a')) ?>. Reports they try to send are refused with that date.
    </div>
    <?php } ?>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2 flex flex-col gap-5">
            <section class="card">
                <div class="card-body">
                    <h2 class="text-base font-semibold mb-3">Reports they filed</h2>
                    <?php if ($filed === []) { ?>
                    <p class="<?= $muted ?>">They have not reported anything.</p>
                    <?php } else { ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left <?= $small ?> uppercase">
                                <tr class="border-b border-slate-200 dark:border-zink-500">
                                    <th scope="col" class="py-2 pr-4 font-medium">Reported item</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">Reason</th>
                                    <th scope="col" class="py-2 pr-4 font-medium">Filed</th>
                                    <th scope="col" class="py-2 font-medium">Outcome</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zink-600">
                                <?php foreach ($filed as $report) {
                                    $outcome = (string) $report['outcome'];
                                    $outcomeLabel = $outcomeLabels[$outcome] ?? ucfirst($outcome);
                                    ?>
                                <tr>
                                    <td class="py-2.5 pr-4 align-top">
                                        <a href="/admin/reports/<?= (int) $report['case_id'] ?>" class="font-medium hover:text-custom-500">
                                            <?= e(truncate((string) ($report['subject_snapshot'] ?? 'Reported '.$report['subject_type']), 70)) ?>
                                        </a>
                                        <p class="<?= $small ?>">
                                            <?= e(ucfirst((string) $report['subject_type'])) ?> · case #<?= (int) $report['case_id'] ?>
                                            <?php if ($report['subject_author_handle'] !== null) { ?> · by @<?= e((string) $report['subject_author_handle']) ?><?php } ?>
                                        </p>
                                        <?php if (!empty($report['details'])) { ?>
                                        <p class="<?= $muted ?> mt-1 break-words">"<?= e(truncate((string) $report['details'], 160)) ?>"</p>
                                        <?php } ?>
                                    </td>
                                    <td class="py-2.5 pr-4 align-top">
                                        <?= e($categoryLabels[$report['category']] ?? (string) $report['category']) ?>
                                        <?php if ((int) $report['counts_toward_threshold'] !== 1 && $report['not_counted_reason'] !== null) { ?>
                                        <p class="<?= $small ?>">Not counted: <?= e($notCounted[$report['not_counted_reason']] ?? (string) $report['not_counted_reason']) ?></p>
                                        <?php } ?>
                                    </td>
                                    <td class="py-2.5 pr-4 align-top whitespace-nowrap"><?= e(local_datetime((string) $report['created_at'], 'M j, Y')) ?></td>
                                    <td class="py-2.5 align-top">{% cmp="status-badge" status="{$outcome}" label="{$outcomeLabel}" %}</td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="<?= $small ?> mt-3">The 50 most recent.</p>
                    <?php } ?>
                </div>
            </section>

            <section class="card">
                <div class="card-body">
                    <h2 class="text-base font-semibold mb-3">Warnings and pauses</h2>
                    <?php if ($history === []) { ?>
                    <p class="<?= $muted ?>">No moderator has warned them or paused their reporting.</p>
                    <?php } else { ?>
                    <ol class="flex flex-col gap-3">
                        <?php foreach ($history as $event) {
                            $details = $event['details'];
                            ?>
                        <li class="text-sm">
                            <p>
                                <span class="font-medium"><?= e($eventLabels[$event['action']] ?? $event['action']) ?></span>
                                <span class="<?= $small ?>">
                                    <?php if (isset($details['rule'])) { ?>
                                    automatically, at the unfounded-report limit,
                                    <?php } else { ?>
                                    by <?= $event['actor_handle'] !== null ? '@'.e((string) $event['actor_handle']) : 'a deleted account' ?>,
                                    <?php } ?>
                                    <?= e(local_datetime($event['created_at'], 'M j, Y g:i a')) ?>
                                </span>
                            </p>
                            <?php if (isset($details['until'])) { ?>
                            <p class="<?= $muted ?>">For <?= (int) ($details['days'] ?? 0) ?> days, until <?= e(local_datetime((string) $details['until'], 'M j, Y')) ?>.</p>
                            <?php } ?>
                            <?php foreach (['message', 'note'] as $key) {
                                if (!empty($details[$key])) { ?>
                            <p class="<?= $muted ?> whitespace-pre-line break-words">"<?= e((string) $details[$key]) ?>"</p>
                            <?php }
                                } ?>
                        </li>
                        <?php } ?>
                    </ol>
                    <?php } ?>
                </div>
            </section>
        </div>

        <div class="flex flex-col gap-5">
            <section class="card">
                <div class="card-body">
                    <h2 class="text-base font-semibold mb-3">Their record</h2>
                    <dl class="grid grid-cols-2 gap-y-1.5 text-sm">
                        <dt class="<?= $muted ?>">Filed</dt><dd><?= (int) $summary['filed'] ?></dd>
                        <dt class="<?= $muted ?>">Waiting</dt><dd><?= (int) $summary['pending'] ?></dd>
                        <dt class="<?= $muted ?>">Upheld</dt><dd><?= (int) $summary['upheld'] ?></dd>
                        <dt class="<?= $muted ?>">Dismissed</dt><dd><?= (int) $summary['dismissed'] ?></dd>
                        <dt class="<?= $muted ?>">Unfounded</dt><dd><?= (int) $summary['unfounded'] ?></dd>
                        <dt class="<?= $muted ?>">Unfounded share</dt><dd><?= $unfoundedShare === null ? 'None decided yet' : $unfoundedShare.'% of decided' ?></dd>
                        <dt class="<?= $muted ?>">Account</dt><dd>Joined <?= e(local_datetime((string) $reporter['created_at'], 'M j, Y')) ?></dd>
                    </dl>
                    <p class="<?= $small ?> mt-3">
                        Weigh the number, the share, how serious the reports were and whether they look deliberate before acting (DSA Art. 23(3)).
                        Dismissed means the report was wrong. Unfounded means it was plainly made in bad faith.
                    </p>
                </div>
            </section>

            <section class="card">
                <div class="card-body flex flex-col gap-3">
                    <h2 class="text-base font-semibold">Act on their reporting</h2>
                    {% cmp="btn" variant="yellow" icon="alert-triangle" label="Warn about unfounded reports" dataModalTarget="warnModal" %}
                    <?php if ($pausedUntil !== null) { ?>
                        {% cmp="btn" variant="slate" icon="flag" label="End the pause" dataModalTarget="resumeModal" %}
                    <?php } elseif ($warned) { ?>
                        {% cmp="btn" variant="red" icon="flag-off" label="Pause their reporting" dataModalTarget="pauseModal" %}
                    <?php } else { ?>
                        <p class="<?= $small ?>">Their reporting can only be paused after they have been warned.</p>
                    <?php } ?>
                </div>
            </section>
        </div>
    </div>
</div>

<?php ob_start(); ?>
<form method="post" action="<?= e($base) ?>/warn" id="warnForm" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_decision" value="warn">
    <?= $reopenWarn ? $dialogErrors : '' ?>
    <p class="<?= $muted ?>">They get a notification and an email with your words, saying their reporting may be paused if this goes on.</p>
    <div>
        <label for="warn-message" class="<?= $labelClass ?>">Message to them</label>
        <textarea id="warn-message" name="message" rows="4" required minlength="10" maxlength="2000" class="<?= $fieldClass ?>"><?= e($reopenWarn ? (string) old('message', '') : '') ?></textarea>
    </div>
</form>
<?php $warnBody = ob_get_clean(); ?>
{% cmp="modal" id="warnModal" title="Warn about unfounded reports" icon="alert-triangle" variant="warning" size="lg" body="{$warnBody}" form="warnForm" confirmText="Send warning" openOnLoad="{$reopenWarn}" %}

<?php if ($warned && $pausedUntil === null) { ?>
<?php
$pickedDays = $reopenPause ? (string) old('days', '30') : '30';
ob_start(); ?>
<form method="post" action="<?= e($base) ?>/pause" id="pauseForm" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_decision" value="pause">
    <?= $reopenPause ? $dialogErrors : '' ?>
    <p class="<?= $muted ?>">Reports they send are refused until the pause ends, with the date shown to them. Reports already filed are not touched.</p>
    <div>
        <label for="pause-days" class="<?= $labelClass ?>">How long</label>
        <select id="pause-days" name="days" class="<?= $fieldClass ?>">
            <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days', 180 => '180 days', 365 => 'A year'] as $days => $text) { ?>
            <option value="<?= $days ?>" <?= $pickedDays === (string) $days ? 'selected' : '' ?>><?= $text ?></option>
            <?php } ?>
        </select>
    </div>
    <div>
        <label for="pause-note" class="<?= $labelClass ?>">Why (kept in their history)</label>
        <textarea id="pause-note" name="note" rows="3" required minlength="3" maxlength="1000" class="<?= $fieldClass ?>"><?= e($reopenPause ? (string) old('note', '') : '') ?></textarea>
    </div>
</form>
<?php $pauseBody = ob_get_clean(); ?>
{% cmp="modal" id="pauseModal" title="Pause their reporting" icon="flag-off" variant="danger" size="lg" body="{$pauseBody}" form="pauseForm" confirmText="Pause" openOnLoad="{$reopenPause}" %}
<?php } ?>

<?php if ($pausedUntil !== null) { ?>
<?php ob_start(); ?>
<form method="post" action="<?= e($base) ?>/resume" id="resumeForm" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_decision" value="resume">
    <?= $reopenResume ? $dialogErrors : '' ?>
    <div>
        <label for="resume-note" class="<?= $labelClass ?>">Why end it early (kept in their history)</label>
        <textarea id="resume-note" name="note" rows="3" required minlength="3" maxlength="1000" class="<?= $fieldClass ?>"><?= e($reopenResume ? (string) old('note', '') : '') ?></textarea>
    </div>
</form>
<?php $resumeBody = ob_get_clean(); ?>
{% cmp="modal" id="resumeModal" title="End the pause" icon="flag" body="{$resumeBody}" form="resumeForm" confirmText="End the pause" openOnLoad="{$reopenResume}" %}
<?php } ?>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/modal.js"></script>
{% endblock %}
