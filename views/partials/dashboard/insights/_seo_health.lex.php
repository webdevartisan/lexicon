<?php
/**
 * How many posts are offered to search engines, and what the checks found on
 * them. Each post opens in the editor, where every field checked can be changed.
 */
$health = $report['health'];
$checkCounts = array_map('count', $health['checks']);
$offeredShare = $health['published'] > 0 ? $health['indexable'] / $health['published'] : null;
$checkDetail = static function (string $check, mixed $detail) use ($t, $present): ?string {
    return match ($check) {
        'title_long' => $t('analytics.seo.health.details.characters', ['count' => $present->number((int) $detail)]),
        'title_duplicate' => $t('analytics.seo.health.details.sharedBy', ['count' => $present->number((int) $detail)]),
        'image_alt_missing' => $t('analytics.seo.health.details.images', ['count' => $present->number((int) $detail)]),
        'keyword_missing' => $t('analytics.seo.health.details.notIn', ['places' => implode(', ', array_map(
            static fn (string $place): string => $t('analytics.seo.health.places.'.$place),
            explode(',', (string) $detail)
        ))]),
        default => null,
    };
};
$editHref = static fn (array $row): string => lurl('/dashboard/post/'.(int) $row['id'].'/edit');
?>
<div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
  <section class="card mb-0" aria-labelledby="insights-indexable">
    <div class="card-body">
      <h2 id="insights-indexable" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.seo.health.indexable')) ?></h2>
      <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-800 dark:text-zink-50">
        <?= e($t('analytics.seo.health.ofPublished', ['indexable' => $present->number($health['indexable']), 'published' => $present->number($health['published'])])) ?>
      </p>
      <?php if ($offeredShare !== null) { ?>
      <div class="mt-2 h-2 rounded-full overflow-hidden bg-slate-100 dark:bg-zink-600" aria-hidden="true">
        <div class="h-full bg-custom-500" style="width: <?= round($offeredShare * 100, 1) ?>%"></div>
      </div>
      <?php } ?>
      <p class="mt-2 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.seo.health.indexableHint')) ?></p>
      <?php foreach ($health['blog'] as $blogIssue) { ?>
      <p class="mt-3 px-3 py-2 text-xs rounded-md border border-yellow-200 bg-yellow-50 text-yellow-800 dark:bg-yellow-500/10 dark:border-yellow-500/30 dark:text-yellow-300" role="status"><?= e($t('analytics.seo.health.blogIssues.'.$blogIssue)) ?></p>
      <?php } ?>
    </div>
  </section>

  <section class="card mb-0 xl:col-span-2" aria-labelledby="insights-seo-health">
    <div class="card-body">
      <h2 id="insights-seo-health" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.seo.health.title')) ?></h2>
      <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.seo.health.hint', ['length' => $present->number(\App\Services\Analytics\SeoHealth::TITLE_LENGTH)])) ?></p>
      <ul class="flex flex-col divide-y divide-slate-100 dark:divide-zink-600 text-sm">
        <?php foreach (\App\Services\Analytics\SeoHealth::CHECKS as $check) {
            $found = $health['checks'][$check]; ?>
        <li class="py-1">
          <?php if ($found === []) { ?>
          <p class="flex items-center justify-between gap-3 py-1.5">
            <span class="text-slate-700 dark:text-zink-100"><?= e($t('analytics.seo.health.checks.'.$check)) ?></span>
            <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
              {% cache 'lucide:check:insights-seo' ttl=31536000 %}<i data-lucide="check" class="size-3.5" aria-hidden="true"></i>{% endcache %}
              <?= e($t('analytics.seo.health.none')) ?>
            </span>
          </p>
          <?php } else { ?>
          <details>
            <summary class="flex items-center justify-between gap-3 py-1.5 cursor-pointer list-none [&::-webkit-details-marker]:hidden">
              <span class="text-slate-700 dark:text-zink-100"><?= e($t('analytics.seo.health.checks.'.$check)) ?></span>
              <span class="px-2 py-0.5 text-xs font-medium rounded-full tabular-nums bg-yellow-100 text-yellow-800 dark:bg-yellow-500/20 dark:text-yellow-300"><?= e($present->number(count($found))) ?></span>
            </summary>
            <ul class="mb-2 ltr:pl-3 rtl:pr-3 flex flex-col gap-1 text-xs">
              <?php foreach (array_slice($found, 0, 20) as $finding) {
                  $detailText = $checkDetail($check, $finding['detail']); ?>
              <li class="flex items-baseline justify-between gap-3">
                <a href="<?= e($editHref($finding)) ?>" class="min-w-0 truncate text-slate-600 hover:text-custom-500 dark:text-zink-200" dir="auto"><?= e($finding['title']) ?></a>
                <?php if ($detailText !== null) { ?>
                <span class="shrink-0 text-slate-500 dark:text-zink-300"><?= e($detailText) ?></span>
                <?php } ?>
              </li>
              <?php } ?>
              <?php if (count($found) > 20) { ?>
              <li class="text-slate-500 dark:text-zink-300"><?= e($t('analytics.seo.health.more', ['count' => $present->number(count($found) - 20)])) ?></li>
              <?php } ?>
            </ul>
          </details>
          <?php } ?>
        </li>
        <?php } ?>
      </ul>

      <?php if ($health['notOffered'] !== []) { ?>
      <details class="mt-3">
        <summary class="cursor-pointer text-xs font-medium text-slate-600 dark:text-zink-200">
          <?= e($t('analytics.seo.health.notOffered', ['count' => $present->number(count($health['notOffered']))])) ?>
        </summary>
        <ul class="mt-2 ltr:pl-3 rtl:pr-3 flex flex-col gap-1 text-xs">
          <?php foreach (array_slice($health['notOffered'], 0, 20) as $hidden) { ?>
          <li class="flex items-baseline justify-between gap-3">
            <a href="<?= e($editHref($hidden)) ?>" class="min-w-0 truncate text-slate-600 hover:text-custom-500 dark:text-zink-200" dir="auto"><?= e($hidden['title']) ?></a>
            <span class="shrink-0 text-slate-500 dark:text-zink-300"><?= e($t('analytics.seo.health.reasons.'.$hidden['detail'])) ?></span>
          </li>
          <?php } ?>
        </ul>
      </details>
      <?php } ?>
    </div>
  </section>
</div>
