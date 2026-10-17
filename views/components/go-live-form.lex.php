<?php
/**
 * Go-live form
 *
 * The standalone form a visibility-line button submits through its form
 * attribute when the line sits inside another form (the post editor, blog
 * settings). Render it outside that form. Renders nothing unless the line
 * offers going live.
 *
 * Attributes:
 * - line: VisibilityPresenter output
 * - id: form id the line's formId points at
 * - returnTo: dashboard path to come back to afterwards
 */
$line = is_array($line ?? null) ? $line : [];
$action = $line['action'] ?? null;
?>
<?php if ($action !== null && $action['method'] === 'POST') { ?>
<form method="post" action="<?= e(lurl($action['url'])) ?>" id="<?= e((string) ($id ?? 'go-live-form')) ?>" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="return_to" value="<?= e((string) ($returnTo ?? '')) ?>">
</form>
<?php } ?>
