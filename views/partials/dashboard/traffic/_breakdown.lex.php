<?php
// The Sign-ups page passes its own heading and a single count column. The first column sizes the bar.
$countColumns = $breakdownCounts ?? ['views' => $t('traffic.breakdowns.views'), 'visitors' => $t('traffic.breakdowns.visitors')];
$barColumn = (string) array_key_first($countColumns);
$maxCount = max(1, ...array_map(static fn (array $row): int => $row[$barColumn], $rows ?: [[$barColumn => 1]]));
$exportQuery = $range->query() + ['dimension' => $dimension];
if ($scope === 'post') {
    $exportQuery['post'] = (int) $post['id'];
}
$headingId = 'traffic-'.str_replace('_', '-', $dimension);
?>
<section class="card mb-0" aria-labelledby="<?= e($headingId) ?>">
  <div class="card-body">
    <div class="flex items-center justify-between gap-2 mb-3">
      <h2 id="<?= e($headingId) ?>" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($breakdownHeadings[$dimension] ?? $present->heading($dimension)) ?></h2>
      <?php if ($rows !== []) { ?>
      <a href="<?= e(lurl($basePath.'/export').'?'.http_build_query($exportQuery)) ?>"
         class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-custom-500 dark:text-zink-300">
        {% cache 'lucide:download:traffic-export' ttl=31536000 %}<i data-lucide="download" class="size-3.5" aria-hidden="true"></i>{% endcache %}
        <?= e($t('traffic.breakdowns.export')) ?>
      </a>
      <?php } ?>
    </div>

    <?php if ($rows === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300">
      <?= e($dimension === 'country' && !$countriesAvailable
          ? $t('traffic.breakdowns.countriesUnavailable')
          : $t('traffic.breakdowns.empty')) ?>
    </p>
    <?php } else { ?>
    <table class="w-full text-sm">
      <thead class="sr-only">
        <tr>
          <th scope="col"><?= e($t('traffic.breakdowns.item')) ?></th>
          <?php foreach ($countColumns as $columnLabel) { ?>
          <th scope="col"><?= e($columnLabel) ?></th>
          <?php } ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row) {
            $width = max(2, (int) round($row[$barColumn] / $maxCount * 100));
            $label = $present->label($dimension, $row['value'], $row['name'] ?? null); ?>
        <tr class="group">
          <th scope="row" class="py-1 ltr:pr-3 rtl:pl-3 font-normal ltr:text-left rtl:text-right">
            <div class="relative px-2 py-1 overflow-hidden rounded">
              <span class="absolute inset-y-0 ltr:left-0 rtl:right-0 rounded bg-custom-500/15 transition-colors group-hover:bg-custom-500/25" style="width: <?= $width ?>%" aria-hidden="true"></span>
              <span class="relative block truncate text-slate-700 dark:text-zink-100" dir="auto" title="<?= e($label) ?>">
                <?= e($label) ?>
              </span>
            </div>
          </th>
          <?php foreach (array_keys($countColumns) as $column) {
              $padding = $column === array_key_last($countColumns) ? 'ltr:pl-2 rtl:pr-2' : 'px-2';
              $tone = $column === $barColumn ? 'text-slate-700 dark:text-zink-100' : 'text-slate-500 dark:text-zink-300'; ?>
          <td class="py-1 <?= $padding ?> w-16 ltr:text-right rtl:text-left tabular-nums <?= $tone ?>"><?= e($present->number($row[$column])) ?></td>
          <?php } ?>
        </tr>
        <?php } ?>
      </tbody>
    </table>
    <div class="flex justify-end gap-4 mt-2 text-[11px] uppercase tracking-wide text-slate-400 dark:text-zink-400" aria-hidden="true">
      <?php foreach ($countColumns as $columnLabel) { ?>
      <span><?= e($columnLabel) ?></span>
      <?php } ?>
    </div>
    <?php } ?>

    <?php if ($dimension === 'country' && $countriesAvailable) { ?>
    <p class="mt-3 text-xs text-slate-400 dark:text-zink-400">
      <?= e($t('traffic.breakdowns.otherCountriesHint')) ?>
      <a href="https://db-ip.com" target="_blank" rel="noopener" class="underline hover:text-custom-500"><?= e($t('traffic.breakdowns.countriesCredit')) ?></a>
    </p>
    <?php } ?>
  </div>
</section>
