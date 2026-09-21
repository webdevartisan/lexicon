{% extends "back.lex.php" %}

{% block title %}<?= $current ? 'Lift Suspension' : 'Suspend User' ?>{% endblock %}
{% block subtitle %}<?= $current ? 'Give the account back and restore what the suspension hid.' : 'Block sign-in and hide their content until the suspension ends.' ?>{% endblock %}

{% block body %}
<?php
$userId = (int) $user['id'];
$handle = '@'.$user['handle'];
$showUrl = '/admin/users/'.$userId;
$radioClass = 'form-radio border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500';
$fieldClass = 'form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700';
$selectClass = 'form-select border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700';
$heading = 'text-base font-semibold text-slate-900 dark:text-zink-50';
$muted = 'text-sm text-slate-500 dark:text-zink-300';
$oldType = (string) old('type', 'temporary');
$oldDuration = (string) old('duration', '7d');
$oldUntil = (string) old('until', '');
$oldReason = (string) old('reason', '');
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto max-w-2xl">
    {% include "areas/admin/_errors.lex.php" %}

    <?php if ($isSelf) { ?>
    <div class="card"><div class="card-body"><p class="<?= $muted ?>">You cannot suspend your own account.</p></div></div>

    <?php } elseif ($current) { ?>
    <form method="post" action="<?= e($showUrl) ?>/lift-suspension" class="card">
        {{ csrf_field() }}
        <div class="card-body">
            <h3 class="<?= $heading ?> mb-2">Lift the suspension on <?= e($handle) ?>?</h3>
            <p class="<?= $muted ?> mb-3">
                <?= $current['expires_at'] ? 'Temporary, due to end '.e(local_datetime($current['expires_at'], 'M j, Y H:i T')).'.' : 'Permanent: it only ends when someone lifts it.' ?>
                Applied by @<?= e((string) ($current['suspended_by_handle'] ?? 'unknown')) ?> on <?= e(local_datetime($current['suspended_at'], 'M j, Y H:i')) ?>.
            </p>
            <p class="<?= $muted ?> mb-3"><strong>Reason given:</strong> <?= e((string) ($current['reason'] ?? '')) ?></p>
            <p class="<?= $muted ?>">
                Lifting lets them sign in again, puts the <?= (int) $current['blogs_hidden'] ?> hidden blog(s) back to the status each had before,
                and shows the <?= (int) $current['comments_hidden'] ?> hidden comment(s) again. Comments hidden for any other reason stay hidden.
            </p>
        </div>
        {% cmp="form-footer" cancelHref="{$showUrl}" submitLabel="Lift suspension" submitIcon="unlock" %}
    </form>

    <?php } else { ?>
    <form method="post" action="<?= e($showUrl) ?>/suspend" class="card" data-suspend-form>
        {{ csrf_field() }}
        <div class="card-body flex flex-col gap-5">
            <fieldset>
                <legend class="<?= $heading ?> mb-2">How long</legend>
                <div class="flex flex-col gap-2">
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="type" value="temporary" class="<?= $radioClass ?>" <?= $oldType !== 'permanent' ? 'checked' : '' ?> data-suspend-type>
                        Temporary, lifts by itself
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                        <input type="radio" name="type" value="permanent" class="<?= $radioClass ?>" <?= $oldType === 'permanent' ? 'checked' : '' ?> data-suspend-type>
                        Permanent, until someone lifts it
                    </label>
                </div>
            </fieldset>

            <div data-suspend-duration class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="duration" class="inline-block mb-2 text-sm font-medium">Length</label>
                    <select id="duration" name="duration" class="<?= $selectClass ?> w-full" data-suspend-length>
                        <?php foreach ($durations as $durationKey => $durationSpec) { ?>
                        <option value="<?= e($durationKey) ?>" <?= $oldDuration === $durationKey ? 'selected' : '' ?>><?= e($durationSpec['label']) ?></option>
                        <?php } ?>
                        <option value="custom" <?= $oldDuration === 'custom' ? 'selected' : '' ?>>Until a date I choose</option>
                    </select>
                </div>
                <div data-suspend-custom>
                    <label for="until" class="inline-block mb-2 text-sm font-medium">Ends at</label>
                    <input type="datetime-local" id="until" name="until" value="<?= e($oldUntil) ?>" class="<?= $fieldClass ?> w-full" aria-describedby="until-hint">
                    <p id="until-hint" class="text-xs text-slate-400 dark:text-zink-400 mt-1">Your time (<?= e($viewerTimezone) ?>). Only used with “Until a date I choose”.</p>
                </div>
            </div>

            <div>
                <label for="reason" class="inline-block mb-2 text-sm font-medium">Reason</label>
                <textarea id="reason" name="reason" rows="3" required maxlength="500" class="<?= $fieldClass ?> w-full" aria-describedby="reason-hint"><?= e($oldReason) ?></textarea>
                <p id="reason-hint" class="text-xs text-slate-400 dark:text-zink-400 mt-1">Kept in their history and the audit log. They are not shown it.</p>
            </div>

            <div class="p-4 rounded-md border border-yellow-200 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-800 text-sm text-slate-700 dark:text-zink-200">
                <p class="font-semibold mb-2">What happens to <?= e($handle) ?></p>
                <ul class="list-disc ltr:pl-5 rtl:pr-5 flex flex-col gap-1">
                    <li>They are signed out everywhere and cannot sign in.</li>
                    <li>Their public profile stops being shown.</li>
                    <li>
                        <?= count($impact['solo_blogs']) ?> blog(s) they run alone will be hidden<?= $impact['solo_blogs'] !== [] ? ': '.e(implode(', ', array_column($impact['solo_blogs'], 'blog_name'))) : '' ?>.
                    </li>
                    <?php if ($impact['shared_blogs'] !== []) { ?>
                    <li>
                        <?= count($impact['shared_blogs']) ?> blog(s) they own with other people stay published, so their co-authors are not punished:
                        <?= e(implode(', ', array_column($impact['shared_blogs'], 'blog_name'))) ?>.
                    </li>
                    <?php } ?>
                    <li><?= (int) $impact['comments'] ?> comment(s) they wrote will be hidden across the site. Replies under them are hidden with them.</li>
                    <li>Nothing is deleted. Lifting the suspension puts all of it back as it was.</li>
                </ul>
            </div>
        </div>
        {% cmp="form-footer" cancelHref="{$showUrl}" submitLabel="Suspend account" submitIcon="ban" danger %}
    </form>
    <?php } ?>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/suspend-form.js"></script>
{% endblock %}
