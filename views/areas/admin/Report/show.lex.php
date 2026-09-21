{% extends "back.lex.php" %}

{% block title %}Report case #<?= (int) $case['id'] ?>{% endblock %}
{% block subtitle %}Everything about this report in one place. Decide from the panel on the right.{% endblock %}

{% block body %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<?php
$caseId = (int) $case['id'];
$caseUrl = '/admin/reports/'.$caseId;
$isOpen = $case['status'] !== 'resolved';
$kind = $case['subject_type'] === 'post' ? 'post' : 'comment';
$status = (string) $case['status'];
$statusLabel = (string) $case['status_label'];
$contentState = (string) $case['content_state'];
$contentLabel = (string) $case['content_label'];
$hasAuthor = $author !== null;
$pendingReports = array_values(array_filter($reports, static fn (array $r): bool => $r['outcome'] === 'pending'));

$card = 'card mb-5';
$heading = 'text-base font-semibold text-slate-900 dark:text-zink-50';
$muted = 'text-sm text-slate-500 dark:text-zink-300';
$small = 'text-xs text-slate-400 dark:text-zink-400';
$dl = 'grid grid-cols-[9rem_1fr] gap-x-3 gap-y-1.5 text-sm';
$dt = 'text-slate-500 dark:text-zink-300';
$dd = 'text-slate-900 dark:text-zink-50 break-words';
$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 w-full';
$checkClass = 'form-checkbox rounded border-slate-200 dark:border-zink-500 text-custom-500 focus:ring-custom-500';
$when = static fn (?string $ts): string => $ts ? local_datetime($ts, 'M j, Y H:i') : '-';
$label = static fn (string $slug): string => $categoryLabels[$slug] ?? $slug;

$actionNames = ['hide_content' => 'Hide the '.$kind, 'suspend' => 'Suspend the author', 'escalate' => 'Send it to a person'];
$notCounted = [
    'new_account' => 'New account',
    'unfounded_history' => 'Record of unfounded reports',
    'filed_before_rules' => 'Filed before these rules',
];
$outcomeLabels = ['pending' => 'Pending', 'upheld' => 'Upheld', 'dismissed' => 'Dismissed', 'unfounded' => 'Unfounded'];
$eventLabels = [
    'moderation.rule_proposed' => 'Rule proposed an action',
    'moderation.rule_escalated' => 'Rule sent the case to a person',
    'moderation.rule_skipped' => 'Rule skipped, already done',
    'moderation.action_failed' => 'Automatic action failed',
    'moderation.content_hidden' => 'Content hidden',
    'moderation.content_restored' => 'Content restored',
    'moderation.author_warned' => 'Author warned',
    'moderation.author_suspended' => 'Author suspended',
    'moderation.review_started' => 'Review started',
    'moderation.escalated' => 'Escalated',
    'moderation.case_dismissed' => 'Case dismissed',
    'moderation.case_upheld' => 'Case upheld',
];
$decision = (string) old('_decision', '');
$reopenUphold = $decision === 'uphold';
$reopenDismiss = $decision === 'dismiss';
$reopenEscalate = $decision === 'escalate';

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

    <?php if ($case['last_error'] !== null) { ?>
    <div class="mb-5 p-4 rounded-md border border-red-200 bg-red-50 text-sm text-red-700 dark:bg-red-900/40 dark:border-red-800 dark:text-red-200" role="alert">
        <strong>An automatic action failed and nothing was changed.</strong>
        The <?= e($label((string) $case['pending_rule'])) ?> rule tried to <?= e(strtolower($actionNames[$case['pending_action']] ?? (string) $case['pending_action'])) ?>.
        Error: <?= e((string) $case['last_error']) ?>
    </div>
    <?php } elseif ($isOpen && $case['pending_action'] !== null) { ?>
    <div class="mb-5 p-4 rounded-md border border-yellow-200 bg-yellow-50 text-sm text-slate-700 dark:bg-yellow-900/20 dark:border-yellow-800 dark:text-zink-200" role="status">
        <strong>The <?= e($label((string) $case['pending_rule'])) ?> rule proposes: <?= e($actionNames[$case['pending_action']] ?? (string) $case['pending_action']) ?>.</strong>
        It did not act on its own. <?= e((string) ($case['pending_note'] ?? '')) ?>
    </div>
    <?php } ?>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        <div class="xl:col-span-2">

            <section class="<?= $card ?>" aria-labelledby="item-h">
                <div class="card-body">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h3 id="item-h" class="<?= $heading ?>">Reported <?= e($kind) ?></h3>
                        <div class="flex items-center gap-2">
                            {% cmp="status-badge" status="{$status}" label="{$statusLabel}" %}
                            <?php if ($contentState !== 'visible') { ?>
                            {% cmp="status-badge" status="{$contentState}" label="{$contentLabel}" %}
                            <?php } ?>
                            <?php if ($case['public_url'] !== null) { ?>
                            <a href="<?= e((string) $case['public_url']) ?>" target="_blank" rel="noopener" class="text-sm text-custom-500 hover:underline">
                                View on the site<span class="sr-only"> (opens in a new tab)</span>
                            </a>
                            <?php } ?>
                        </div>
                    </div>
                    <p class="<?= $small ?> mb-3">
                        <?= $case['blog_name'] ? 'In '.e((string) $case['blog_name']) : '' ?>
                        <?= $kind === 'comment' && $case['post_title'] ? ' on "'.e((string) $case['post_title']).'"' : '' ?>
                    </p>

                    <?php if ($contentState === 'deleted') { ?>
                    <p class="<?= $muted ?> mb-2">This <?= e($kind) ?> has since been deleted. This is how it read when the first report arrived:</p>
                    <blockquote class="p-3 rounded-md bg-slate-50 dark:bg-zink-700 text-sm whitespace-pre-line break-words"><?= e((string) ($case['subject_snapshot'] ?? '')) ?></blockquote>
                    <?php } elseif ($kind === 'post') { ?>
                    <h4 class="text-lg font-semibold mb-2 text-slate-900 dark:text-zink-50"><?= e((string) $case['post_title']) ?></h4>
                    <div class="text-sm leading-relaxed break-words max-h-[28rem] overflow-y-auto p-3 rounded-md border border-slate-200 dark:border-zink-500">
                        <?= $postHtml ?>
                    </div>
                    <?php } else { ?>
                    <blockquote class="p-3 rounded-md bg-slate-50 dark:bg-zink-700 text-sm break-words"><?= nl2br(e((string) $case['comment_content'])) ?></blockquote>
                    <?php } ?>
                </div>
            </section>

            <section class="<?= $card ?>" aria-labelledby="reports-h">
                <div class="card-body">
                    <h3 id="reports-h" class="<?= $heading ?> mb-3"><?= count($reports) ?> report<?= count($reports) === 1 ? '' : 's' ?></h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left bg-slate-100 dark:bg-zink-600 text-xs uppercase tracking-wide text-slate-500 dark:text-zink-200">
                                <tr>
                                    <th scope="col" class="px-3 py-2 font-semibold">Reporter</th>
                                    <th scope="col" class="px-3 py-2 font-semibold">Reason</th>
                                    <th scope="col" class="px-3 py-2 font-semibold">Counts toward rules</th>
                                    <th scope="col" class="px-3 py-2 font-semibold">Outcome</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-zink-600 align-top">
                                <?php foreach ($reports as $report) {
                                    $unfounded = (int) $report['reporter_unfounded']; ?>
                                <tr>
                                    <td class="px-3 py-2">
                                        <?php if ($report['reporter_id'] !== null && $report['reporter_handle'] !== null) { ?>
                                        <a href="/admin/reports/reporters/<?= (int) $report['reporter_id'] ?>" class="hover:text-custom-500 hover:underline">@<?= e((string) $report['reporter_handle']) ?></a>
                                        <?php } else { ?>
                                        <span class="<?= $small ?>">Deleted account</span>
                                        <?php } ?>
                                        <span class="block <?= $small ?>">
                                            <?= e($when($report['created_at'])) ?> &middot; filed <?= (int) $report['reporter_filed'] ?>
                                        </span>
                                        <?php if ($unfounded > 0) { ?>
                                        <span class="block text-xs font-medium text-amber-700 dark:text-amber-300"><?= $unfounded ?> earlier report<?= $unfounded === 1 ? ' was' : 's were' ?> ruled unfounded</span>
                                        <?php } ?>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="font-medium"><?= e($label((string) $report['category'])) ?></span>
                                        <?php if (!empty($report['details'])) { ?>
                                        <p class="mt-1 text-slate-600 dark:text-zink-200 whitespace-pre-line break-words"><?= e((string) $report['details']) ?></p>
                                        <?php } ?>
                                    </td>
                                    <td class="px-3 py-2">
                                        <?= (int) $report['counts_toward_threshold'] === 1
                                            ? 'Yes'
                                            : 'No: '.e($notCounted[$report['not_counted_reason']] ?? (string) $report['not_counted_reason']) ?>
                                    </td>
                                    <td class="px-3 py-2"><?= e($outcomeLabels[$report['outcome']] ?? (string) $report['outcome']) ?></td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="<?= $card ?>" aria-labelledby="timeline-h">
                <div class="card-body">
                    <h3 id="timeline-h" class="<?= $heading ?> mb-3">What has happened</h3>
                    <?php if ($timeline === []) { ?>
                    <p class="<?= $muted ?>">No decisions yet. Reports arrived on <?= e($when($case['first_reported_at'])) ?>.</p>
                    <?php } else { ?>
                    <ol class="flex flex-col gap-3 text-sm">
                        <?php foreach ($timeline as $event) {
                            $d = $event['details'];
                            $isSystem = ($d['actor_type'] ?? '') === 'system';
                            $who = $isSystem
                                ? 'System, '.$label((string) ($d['rule'] ?? '')).' rule'
                                : '@'.($event['actor_handle'] ?? 'deleted account');
                            $extra = $d['note'] ?? $d['reason'] ?? $d['message'] ?? null;
                            if (isset($d['held_because'])) {
                                $extra = $holdNotes[$d['held_because']] ?? (string) $d['held_because'];
                            }
                            if (isset($d['error'])) {
                                $extra = 'Error: '.$d['error'];
                            } ?>
                        <li class="flex gap-3">
                            <span class="shrink-0 mt-1 inline-block size-2 rounded-full <?= $isSystem ? 'bg-sky-500' : 'bg-custom-500' ?>" aria-hidden="true"></span>
                            <div>
                                <p class="text-slate-900 dark:text-zink-50">
                                    <span class="font-medium"><?= e($eventLabels[$event['action']] ?? (string) $event['action']) ?></span>
                                    <?php if (isset($d['action']) && $event['action'] === 'moderation.rule_proposed') { ?>
                                    : <?= e($actionNames[$d['action']] ?? (string) $d['action']) ?>
                                    <?php } ?>
                                </p>
                                <p class="<?= $small ?>"><?= e($who) ?> &middot; <?= e($when($event['created_at'])) ?></p>
                                <?php if ($extra !== null && $extra !== '') { ?>
                                <p class="mt-1 text-slate-600 dark:text-zink-200 whitespace-pre-line break-words"><?= e((string) $extra) ?></p>
                                <?php } ?>
                            </div>
                        </li>
                        <?php } ?>
                    </ol>
                    <?php } ?>
                </div>
            </section>
        </div>

        <div>
            <section class="<?= $card ?>" aria-labelledby="decide-h">
                <div class="card-body">
                    <h3 id="decide-h" class="<?= $heading ?> mb-3">Decide</h3>
                    <?php if (!$isOpen) { ?>
                    <p class="<?= $muted ?>">
                        <?= $case['resolution'] === 'upheld' ? 'Upheld' : 'Dismissed' ?> on <?= e($when($case['resolved_at'])) ?>.
                    </p>
                    <?php if (!empty($case['resolution_note'])) { ?>
                    <p class="mt-2 text-sm whitespace-pre-line break-words"><?= e((string) $case['resolution_note']) ?></p>
                    <?php } ?>
                    <p class="<?= $small ?> mt-3">A new report on this <?= e($kind) ?> opens a new case.</p>
                    <?php } else { ?>
                    <div class="flex flex-col gap-2">
                        {% cmp="btn" variant="green" icon="check" label="Uphold the reports" dataModalTarget="upholdModal" %}
                        {% cmp="btn" variant="slate" icon="x" label="Dismiss the reports" dataModalTarget="dismissModal" %}
                        <?php if ($canSuspend) { ?>
                        <?php $suspendHref = '/admin/users/'.(int) $case['subject_author_id'].'/suspend?case='.$caseId; ?>
                        {% cmp="btn" variant="red" icon="ban" label="Suspend the author" href="{$suspendHref}" %}
                        <?php } ?>
                        <?php if ($status !== 'escalated') { ?>
                        {% cmp="btn" variant="yellow" icon="arrow-up-circle" label="Escalate" dataModalTarget="escalateModal" %}
                        <?php } ?>
                        <?php if (in_array($status, ['open', 'escalated'], true)) { ?>
                        <form method="post" action="<?= e($caseUrl) ?>/review">
                            {{ csrf_field() }}
                            {% cmp="btn" type="submit" variant="blue" icon="play" label="Mark as in review" addClass="w-full" %}
                        </form>
                        <?php } ?>
                    </div>
                    <?php if (!$canSuspend && $hasAuthor) { ?>
                    <p class="<?= $small ?> mt-3">Suspending needs the user management permission. Escalate the case if the author should be suspended.</p>
                    <?php } ?>
                    <?php } ?>
                </div>
            </section>

            <section class="<?= $card ?>" aria-labelledby="author-h">
                <div class="card-body">
                    <h3 id="author-h" class="<?= $heading ?> mb-3">Author</h3>
                    <?php if (!$hasAuthor) { ?>
                    <p class="<?= $muted ?>">
                        <?= $case['subject_author_id'] === null ? 'Posted as a guest.' : 'The account no longer exists. It was @'.e((string) ($case['subject_author_handle'] ?? 'unknown')).'.' ?>
                    </p>
                    <?php } else { ?>
                    <dl class="<?= $dl ?>">
                        <dt class="<?= $dt ?>">Account</dt>
                        <dd class="<?= $dd ?>"><?php if ($canViewUsers) { ?><a href="/admin/users/<?= (int) $author['id'] ?>" class="text-custom-500 hover:underline">@<?= e((string) $author['handle']) ?></a><?php } else { ?>@<?= e((string) $author['handle']) ?><?php } ?></dd>
                        <dt class="<?= $dt ?>">Joined</dt>
                        <dd class="<?= $dd ?>"><?= e(local_datetime($author['created_at'] ?? null, 'M j, Y')) ?></dd>
                        <dt class="<?= $dt ?>">Status</dt>
                        <dd class="<?= $dd ?>"><?= $author['suspended_at'] !== null ? 'Suspended' : ((int) $author['is_active'] === 1 ? 'Active' : 'Deactivated') ?></dd>
                        <dt class="<?= $dt ?>">Earlier cases</dt>
                        <dd class="<?= $dd ?>">
                            <?= (int) $authorRecord['total'] ?>
                            (<?= (int) $authorRecord['upheld'] ?> upheld, <?= (int) $authorRecord['dismissed'] ?> dismissed, <?= (int) $authorRecord['open'] ?> open)
                        </dd>
                        <dt class="<?= $dt ?>">Warnings</dt>
                        <dd class="<?= $dd ?>"><?= (int) $authorWarnings ?></dd>
                        <dt class="<?= $dt ?>">Suspensions</dt>
                        <dd class="<?= $dd ?>">
                            <?= count($authorSuspensions) ?>
                            <?php $byRule = count(array_filter($authorSuspensions, static fn ($s) => ($s['source'] ?? '') === 'rule')); ?>
                            <?= $byRule > 0 ? '('.$byRule.' applied by a rule)' : '' ?>
                        </dd>
                    </dl>
                    <?php } ?>
                </div>
            </section>

            <section class="<?= $card ?>" aria-labelledby="facts-h">
                <div class="card-body">
                    <h3 id="facts-h" class="<?= $heading ?> mb-3">Case</h3>
                    <dl class="<?= $dl ?>">
                        <dt class="<?= $dt ?>">Priority</dt><dd class="<?= $dd ?>"><?= (int) $case['priority'] ?></dd>
                        <dt class="<?= $dt ?>">Most serious</dt><dd class="<?= $dd ?>"><?= e($label((string) ($case['top_category'] ?? ''))) ?></dd>
                        <dt class="<?= $dt ?>">Reports</dt><dd class="<?= $dd ?>"><?= (int) $case['report_count'] ?> (<?= (int) $case['counted_report_count'] ?> counted)</dd>
                        <dt class="<?= $dt ?>">First report</dt><dd class="<?= $dd ?>"><?= e($when($case['first_reported_at'])) ?></dd>
                        <dt class="<?= $dt ?>">Last report</dt><dd class="<?= $dd ?>"><?= e($when($case['last_reported_at'])) ?></dd>
                    </dl>
                </div>
            </section>
        </div>
    </div>
</div>

<?php if ($isOpen) { ?>
<?php
$upholdHide = $decision === 'uphold' ? old('hide', '') === '1' : $case['pending_action'] === 'hide_content';
$upholdWarn = $decision === 'uphold' && old('warn', '') === '1';
ob_start(); ?>
<form method="post" action="<?= e($caseUrl) ?>/uphold" id="upholdForm" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_decision" value="uphold">
    <?= $decision === 'uphold' ? $dialogErrors : '' ?>
    <p class="<?= $muted ?>">The reports were right. Every pending report is recorded as upheld.</p>
    <?php if ($contentState === 'visible') { ?>
    <label class="flex items-start gap-2 text-sm">
        <input type="checkbox" name="hide" value="1" class="<?= $checkClass ?> mt-0.5" <?= $upholdHide ? 'checked' : '' ?>>
        <span>Hide the <?= e($kind) ?> from readers. It can be restored later.</span>
    </label>
    <?php } ?>
    <?php if ($hasAuthor) { ?>
    <label class="flex items-start gap-2 text-sm">
        <input type="checkbox" name="warn" value="1" class="<?= $checkClass ?> mt-0.5" <?= $upholdWarn ? 'checked' : '' ?>>
        <span>Warn @<?= e((string) $author['handle']) ?> by notification and email</span>
    </label>
    <div>
        <label for="uphold-warning" class="inline-block mb-1 text-sm font-medium">Message to the author</label>
        <textarea id="uphold-warning" name="warning" rows="3" maxlength="2000" class="<?= $fieldClass ?>" aria-describedby="uphold-warning-hint"><?= e($decision === 'uphold' ? (string) old('warning', '') : '') ?></textarea>
        <p id="uphold-warning-hint" class="<?= $small ?> mt-1">Only sent when the warning is ticked. They see it word for word.</p>
    </div>
    <?php } ?>
    <div>
        <label for="uphold-note" class="inline-block mb-1 text-sm font-medium">Why (kept in the case history)</label>
        <textarea id="uphold-note" name="note" rows="3" required minlength="3" maxlength="1000" class="<?= $fieldClass ?>"><?= e($decision === 'uphold' ? (string) old('note', '') : '') ?></textarea>
    </div>
</form>
<?php $upholdBody = ob_get_clean(); ?>
{% cmp="modal" id="upholdModal" title="Uphold the reports" icon="check" variant="success" size="lg" body="{$upholdBody}" form="upholdForm" confirmText="Uphold" openOnLoad="{$reopenUphold}" %}

<?php ob_start(); ?>
<form method="post" action="<?= e($caseUrl) ?>/dismiss" id="dismissForm" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_decision" value="dismiss">
    <?= $decision === 'dismiss' ? $dialogErrors : '' ?>
    <p class="<?= $muted ?>">
        The reports were wrong.<?= $contentState === 'hidden' ? ' The hidden '.e($kind).' becomes visible again.' : '' ?>
    </p>
    <?php if ($pendingReports !== []) { ?>
    <fieldset>
        <legend class="text-sm font-medium mb-1">Mark reports as unfounded</legend>
        <p class="<?= $small ?> mb-2">Only tick a report that was plainly made in bad faith. Unfounded reports count against the person who filed them.</p>
        <div class="flex flex-col gap-1.5">
            <?php foreach ($pendingReports as $report) { ?>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" name="unfounded[]" value="<?= (int) $report['id'] ?>" class="<?= $checkClass ?> mt-0.5">
                <span>
                    <?= $report['reporter_handle'] !== null ? '@'.e((string) $report['reporter_handle']) : 'Deleted account' ?>,
                    <?= e($label((string) $report['category'])) ?>
                </span>
            </label>
            <?php } ?>
        </div>
    </fieldset>
    <?php } ?>
    <div>
        <label for="dismiss-note" class="inline-block mb-1 text-sm font-medium">Why (kept in the case history)</label>
        <textarea id="dismiss-note" name="note" rows="3" required minlength="3" maxlength="1000" class="<?= $fieldClass ?>"><?= e($decision === 'dismiss' ? (string) old('note', '') : '') ?></textarea>
    </div>
</form>
<?php $dismissBody = ob_get_clean(); ?>
{% cmp="modal" id="dismissModal" title="Dismiss the reports" icon="x-circle" size="lg" body="{$dismissBody}" form="dismissForm" confirmText="Dismiss" openOnLoad="{$reopenDismiss}" %}

<?php ob_start(); ?>
<form method="post" action="<?= e($caseUrl) ?>/escalate" id="escalateForm" class="flex flex-col gap-4">
    <?= csrf_field() ?>
    <input type="hidden" name="_decision" value="escalate">
    <?= $decision === 'escalate' ? $dialogErrors : '' ?>
    <p class="<?= $muted ?>">For a case that needs someone with more authority, for example to suspend the author.</p>
    <div>
        <label for="escalate-note" class="inline-block mb-1 text-sm font-medium">What should they look at?</label>
        <textarea id="escalate-note" name="note" rows="3" required minlength="3" maxlength="1000" class="<?= $fieldClass ?>"><?= e($decision === 'escalate' ? (string) old('note', '') : '') ?></textarea>
    </div>
</form>
<?php $escalateBody = ob_get_clean(); ?>
{% cmp="modal" id="escalateModal" title="Escalate this case" icon="arrow-up-circle" variant="warning" body="{$escalateBody}" form="escalateForm" confirmText="Escalate" openOnLoad="{$reopenEscalate}" %}
<?php } ?>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/modal.js"></script>
{% endblock %}
