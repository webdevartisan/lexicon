<?php
// Shown instead of the list when there is nothing to show. Two cases, and they
// are not the same thing: an empty list needs somewhere to go next, while a
// page past the end of a real list needs its way back. Telling somebody with
// five hundred saves that they have not saved anything would be a lie.
$readerOutOfRange = !empty($pagination['outOfRange']);
$readerBase = (string) ($pagination['basePath'] ?? '/saved');
?>
<div class="lx-reader-empty">
    <?php if ($readerOutOfRange) { ?>
        <p><?= e($t('reader.outOfRange')) ?></p>
        <a class="lx-btn lx-btn--quiet" href="<?= e(lurl($readerBase)) ?>"><?= e($t('reader.outOfRangeAction')) ?></a>
    <?php } else { ?>
        <p><?= e($readerEmptyMessage ?? '') ?></p>
        <a class="lx-btn lx-btn--primary" href="<?= e(lurl('/blogs')) ?>"><?= e($t('reader.exploreBlogs')) ?></a>
    <?php } ?>
</div>
