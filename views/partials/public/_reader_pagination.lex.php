<?php
// Plain numbered pages, the same shape the rest of the front uses. No infinite
// scroll: these lists are finite, personal, and worth linking into at ?page=3.
$readerTotalPages = (int) ($pagination['totalPages'] ?? 1);

if ($readerTotalPages > 1) {
    $readerCurrent = (int) ($pagination['page'] ?? 1);
    $readerBase = (string) ($pagination['basePath'] ?? '/saved');

    // Window around the current page, with both ends always reachable.
    $readerWindow = 2;
    $readerPages = [1, $readerTotalPages];
    for ($p = $readerCurrent - $readerWindow; $p <= $readerCurrent + $readerWindow; $p++) {
        if ($p >= 1 && $p <= $readerTotalPages) {
            $readerPages[] = $p;
        }
    }
    $readerPages = array_unique($readerPages);
    sort($readerPages);

    $readerPageUrl = static fn (int $p): string => lurl($readerBase).($p > 1 ? '?page='.$p : '');
    ?>
<nav class="lx-reader-pages" aria-label="<?= e($t('reader.paginationAria')) ?>">
    <ul>
        <?php $readerPrevious = 0; ?>
        <?php foreach ($readerPages as $p) { ?>
            <?php if ($p - $readerPrevious > 1) { ?>
            <li><span class="lx-reader-gap">&hellip;</span></li>
            <?php } ?>
            <li>
                <?php if ($p === $readerCurrent) { ?>
                <span class="lx-reader-page is-current" aria-current="page"><?= (int) $p ?></span>
                <?php } else { ?>
                <a class="lx-reader-page" href="<?= e($readerPageUrl($p)) ?>"><?= (int) $p ?></a>
                <?php } ?>
            </li>
            <?php $readerPrevious = $p; ?>
        <?php } ?>
    </ul>
</nav>
<?php } ?>
