<?php
/**
 * Visibility line
 *
 * One sentence saying who can read a post or a blog, with a coloured dot and
 * the one action that changes it. Built by VisibilityPresenter.
 *
 * Attributes:
 * - line: VisibilityPresenter output; nothing renders when it is empty
 * - formId: id of a go-live-form elsewhere on the page. Set it when the line
 *   sits inside another form, so the button submits that form instead of
 *   nesting one. Without it the line carries its own small form.
 * - returnTo: dashboard path to come back to after going live
 */
$line = is_array($line ?? null) ? $line : [];
$formId = (string) ($formId ?? '');
$returnTo = (string) ($returnTo ?? '');

$dots = [
    'live' => 'bg-emerald-500',
    'offline' => 'bg-amber-500',
    'scheduled' => 'bg-sky-500',
    'draft' => 'bg-slate-400 dark:bg-zink-300',
    'private' => 'bg-slate-400 dark:bg-zink-300',
];
$linkClass = 'font-medium text-custom-500 underline-offset-2 hover:underline focus:underline focus:decoration-2 focus:outline-none';
$action = $line['action'] ?? null;
?>
<?php if ($line !== []) { ?>
<div role="status" data-visibility-line="<?= e($line['tone']) ?>" class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm text-slate-600 dark:text-zink-200">
  <span class="relative top-[-1px] inline-block size-2 shrink-0 self-center rounded-full <?= $dots[$line['tone']] ?? $dots['draft'] ?>" aria-hidden="true"></span>
  <span>
    <strong class="font-semibold text-slate-800 dark:text-zink-50"><?= e($t($line['key'].'.lead')) ?></strong>
    <?= e($t($line['key'].'.text', $line['params'] ?? [])) ?>
  </span>
  <?php if ($action !== null && $action['method'] === 'GET') { ?>
  <a href="<?= e(base_url().$action['url']) ?>" target="_blank" rel="noopener" class="<?= $linkClass ?>"><?= e($t($action['labelKey'])) ?></a>
  <?php } elseif ($action !== null && $formId !== '') { ?>
  <button type="submit" form="<?= e($formId) ?>" class="<?= $linkClass ?>"><?= e($t($action['labelKey'])) ?></button>
  <?php } elseif ($action !== null) { ?>
  <form method="post" action="<?= e(lurl($action['url'])) ?>" class="inline">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <button type="submit" class="<?= $linkClass ?>"><?= e($t($action['labelKey'])) ?></button>
  </form>
  <?php } ?>
</div>
<?php } ?>
