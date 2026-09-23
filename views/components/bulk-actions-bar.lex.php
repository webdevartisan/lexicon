<?php
/**
 * Bulk selection UI for a list page: a detached form the page's own checkboxes
 * attach to via form="bulk-form", a select-all/count row, and a floating
 * action bar that appears once something is checked. Behaviour lives in
 * /cp-assets/js/bulk-actions.js, which the back layout loads.
 *
 * The page owns its own checkboxes; it only has to give each one
 * class="bulk-checkbox" and form="bulk-form" and pick whatever name="..[]"
 * the bulk controller expects. Rendering them (in a card, a table cell, a
 * list row) stays page-specific, since that shape differs per list.
 *
 * Two optional hooks on the page's own markup:
 * - data-bulk-row on whatever wraps a checkbox gets a highlight while it is
 *   selected
 * - data-bulk-state="pending" on a checkbox tells the bar what that row is,
 *   which is what the appliesTo counts below are matched against
 *
 * Attributes:
 * - actionUrl: where the form posts (the controller's bulk endpoint)
 * - itemLabel: singular noun for the selected-count text, e.g. "post"
 * - actions: list of bulk buttons, each with
 *     action    value written to the hidden field and matched server-side
 *     label     visible button text
 *     icon      lucide icon name
 *     hover     hover background class, e.g. "hover:bg-green-600/30"
 *     danger    destructive styling (red text) for actions like delete
 *     confirm   confirmation text with a {n} placeholder for the live count;
 *               omit for an action that needs no confirmation
 *     appliesTo row states this action can actually change. The button then
 *               carries how many of the selected rows that is and greys out
 *               when the answer is none, so a selection of delivered mail
 *               cannot be sent to an action that only moves failed mail.
 * - hiddenFields: extra name => value pairs the bulk request also needs
 *     (e.g. a status filter to return to), beyond the CSRF token and the
 *     action field this component already writes
 * - headerSelectAll: the page renders its own #select-all, usually in a table
 *     header cell, so the standalone select-all row is left out
 * - matchTotal: how many rows the page's current filter matches in total. When
 *     that is more than one page holds, the bar offers to extend the selection
 *     to all of them, and the request then carries the filter instead of ids
 * - matchCounts: state => how many rows of it the filter matches, so an
 *     extended selection can still say what each action will really touch
 */
$actionUrl = (string) ($actionUrl ?? '');
$itemLabel = (string) ($itemLabel ?? 'item');
$actions = $actions ?? [];
$hiddenFields = $hiddenFields ?? [];
$headerSelectAll = !empty($headerSelectAll);
$matchTotal = (int) ($matchTotal ?? 0);
$matchCounts = $matchCounts ?? [];
?>
<form id="bulk-form" method="POST" action="<?= e($actionUrl) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="bulk_action" id="bulk-action" value="">
    <?php // Flipped to "filter" by the select-all-matching control in the bar.?>
    <input type="hidden" name="select_scope" id="bulk-scope" value="page">
    <?php foreach ($hiddenFields as $name => $value) { ?>
    <input type="hidden" name="<?= e((string) $name) ?>" value="<?= e((string) $value) ?>">
    <?php } ?>
</form>

<?php if (!$headerSelectAll) { ?>
<div class="flex items-center justify-between mb-3 text-xs">
    <label class="inline-flex items-center gap-2 text-slate-600 dark:text-zink-200 cursor-pointer">
        <input type="checkbox" id="select-all"
               class="form-checkbox rounded border-slate-300 dark:border-zink-500 text-custom-500 focus:ring-custom-500">
        <span>Select all on this page</span>
    </label>
    <span id="selected-count" class="text-slate-500 dark:text-zink-300"></span>
</div>
<?php } ?>

<div id="bulk-bar" data-bulk-bar class="hidden fixed bottom-3 sm:bottom-6 inset-x-3 sm:inset-x-auto sm:left-1/2 sm:-translate-x-1/2 z-[9999]"
     data-item-label="<?= e($itemLabel) ?>"
     data-match-total="<?= $matchTotal ?>"
     data-match-counts="<?= e(json_encode($matchCounts, JSON_THROW_ON_ERROR)) ?>">
    <div class="flex flex-wrap items-center justify-center gap-1.5 sm:gap-2 px-3 sm:px-4 py-2.5 sm:py-3 bg-slate-900 dark:bg-zink-700 text-white rounded-2xl sm:rounded-full shadow-lg border border-slate-700">
        <span data-bulk-selected-count class="text-sm font-medium"></span>
        <?php // Only appears once every row on the page is ticked.?>
        <button type="button" data-bulk-scope-toggle
                class="hidden text-xs font-medium underline underline-offset-2 text-sky-300 hover:text-sky-200"></button>
        <span class="pr-2 border-r border-slate-700" aria-hidden="true"></span>
        <?php foreach ($actions as $btn) {
            $hoverClass = (string) ($btn['hover'] ?? 'hover:bg-slate-600/50');
            $dangerClass = empty($btn['danger']) ? '' : ' text-red-300';
            $confirm = empty($btn['confirm']) ? '' : ' data-confirm-template="'.e((string) $btn['confirm']).'"';
            $appliesTo = empty($btn['appliesTo']) ? '' : ' data-applies-to="'.e(implode(' ', (array) $btn['appliesTo'])).'"';
            ?>
        <button type="button" data-bulk="<?= e((string) $btn['action']) ?>"<?= $confirm.$appliesTo ?>
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors disabled:opacity-40 disabled:cursor-not-allowed <?= $hoverClass.$dangerClass ?>">
            {% cache 'lucide:bulk:' . $btn['icon'] ttl=31536000 %}<i data-lucide="<?= e((string) $btn['icon']) ?>" class="size-3.5"></i>{% endcache %}
            <?= e((string) $btn['label']) ?>
            <span data-bulk-eligible-count class="hidden rounded-full bg-white/15 px-1.5 tabular-nums"></span>
        </button>
        <?php } ?>
    </div>
</div>
