<?php
/**
 * The report dialog script, carrying its wording in the reader's language.
 *
 * Loaded by the comment thread and by every theme's post actions; the script
 * sets itself up once, so a page that loads it twice reads the first copy.
 */
$reportText = ['title' => ['comment' => $t('reportDialog.title.comment'), 'post' => $t('reportDialog.title.post')]];

foreach (['intro', 'reasons', 'details', 'cancel', 'send', 'sending', 'close', 'pickReason', 'thanks', 'failed', 'reasonsFailed'] as $reportKey) {
    $reportText[$reportKey] = $t('reportDialog.'.$reportKey);
}
?>
<script defer src="<?= e(versioned_asset('/assets/js/report-dialog.js')) ?>" data-lang="<?= e($chromeLang) ?>" data-dir="<?= e($chromeDir) ?>" data-text="<?= e(json_encode($reportText, JSON_UNESCAPED_UNICODE)) ?>"></script>
