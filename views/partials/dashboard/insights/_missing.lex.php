<section class="card mb-0" aria-labelledby="traffic-missing">
  <div class="card-body">
    <h2 id="traffic-missing" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.missing.title')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($missingHint) ?></p>
    <?php if ($report['missing'] === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.missing.empty')) ?></p>
    <?php } else { ?>
    <ul class="flex flex-col divide-y divide-slate-100 dark:divide-zink-600 text-sm">
      <?php foreach ($report['missing'] as $missingPage) {
          $from = array_map(
              static fn (array $referrer): string => ($referrer['host'] === '' ? $t('analytics.missing.direct') : $referrer['host'])
                  .' ('.$present->number($referrer['views']).')',
              array_slice($missingPage['referrers'], 0, 3)
          ); ?>
      <li class="flex items-start justify-between gap-3 py-2">
        <div class="min-w-0">
          <code class="block truncate text-slate-700 dark:text-zink-100" dir="ltr"><?= e($missingPage['path']) ?></code>
          <span class="block text-xs text-slate-500 dark:text-zink-300" dir="auto"><?= e($t('analytics.missing.from', ['sites' => implode(', ', $from)])) ?></span>
        </div>
        <span class="shrink-0 tabular-nums text-slate-700 dark:text-zink-100"><?= e($present->number($missingPage['views'])) ?></span>
      </li>
      <?php } ?>
    </ul>
    <?php } ?>
  </div>
</section>
