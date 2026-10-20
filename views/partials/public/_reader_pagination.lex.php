<?php
// Numbered pages, using the shared .lx-pagination primitive so reader lists
// paginate with the same shape as the rest of the front. Finite personal
// lists, no infinite scroll — worth linking into at ?page=3.
$readerTotalPages = (int) ($pagination['totalPages'] ?? 1);

if ($readerTotalPages > 1) {
    $readerCurrent = (int) ($pagination['page'] ?? 1);
    $readerBase = (string) ($pagination['basePath'] ?? '/saved');

    $readerWindow = 2;
    $readerPages = [1, $readerTotalPages];
    for ($p = $readerCurrent - $readerWindow; $p <= $readerCurrent + $readerWindow; $p++) {
        if ($p >= 1 && $p <= $readerTotalPages) {
            $readerPages[] = $p;
        }
    }
    $readerPages = array_unique($readerPages);
    sort($readerPages);

    // A filtered list keeps its filter across pages, so the separator depends
    // on whether the base path already carries a query.
    $readerPageJoin = str_contains($readerBase, '?') ? '&' : '?';
    $readerPageUrl = static fn (int $p): string => lurl($readerBase).($p > 1 ? $readerPageJoin.'page='.$p : '');
    ?>
<nav aria-label="<?= e($t('reader.paginationAria')) ?>">
    <ul class="lx-pagination">
        <?php if ($readerCurrent > 1) { ?>
        <li>
            <a data-panel-link href="<?= e($readerPageUrl($readerCurrent - 1)) ?>" class="lx-pagination-step" rel="prev">
                {% cmp="lx-icon" name="chevron-left" %}
                <span class="lx-visually-hidden"><?= e($t('reader.paginationPrev') ?: 'Previous page') ?></span>
            </a>
        </li>
        <?php } ?>

        <?php $readerPrevious = 0; ?>
        <?php foreach ($readerPages as $p) { ?>
            <?php if ($p - $readerPrevious > 1) { ?>
            <li><span class="lx-pagination-gap" aria-hidden="true">&hellip;</span></li>
            <?php } ?>
            <li>
                <a data-panel-link href="<?= e($readerPageUrl($p)) ?>"
                   <?= $p === $readerCurrent ? 'aria-current="page"' : '' ?>>
                    <?= (int) $p ?>
                </a>
            </li>
            <?php $readerPrevious = $p; ?>
        <?php } ?>

        <?php if ($readerCurrent < $readerTotalPages) { ?>
        <li>
            <a data-panel-link href="<?= e($readerPageUrl($readerCurrent + 1)) ?>" class="lx-pagination-step" rel="next">
                <span class="lx-visually-hidden"><?= e($t('reader.paginationNext') ?: 'Next page') ?></span>
                {% cmp="lx-icon" name="chevron-right" %}
            </a>
        </li>
        <?php } ?>
    </ul>
</nav>
<?php } ?>
