<?php
/**
 * Posts that stand out from the scope's own: read by many of the few who find
 * them, or opened often and left early. The thresholds used are printed with them.
 */
$opportunities = $report['opportunities'];
$opportunityLists = [
    'overlooked' => [
        'title' => $t('analytics.opportunities.overlooked'),
        'rule' => $opportunities['quietUpTo'] === null ? null : $t('analytics.opportunities.overlookedRule', [
            'views' => $present->number($opportunities['quietUpTo']),
            'points' => $present->number((int) round(\App\Services\Analytics\ContentOpportunities::GAP * 100)),
            'average' => (string) $present->percent($opportunities['average']),
        ]),
    ],
    'underread' => [
        'title' => $t('analytics.opportunities.underread'),
        'rule' => $opportunities['busyFrom'] === null ? null : $t('analytics.opportunities.underreadRule', [
            'views' => $present->number($opportunities['busyFrom']),
            'points' => $present->number((int) round(\App\Services\Analytics\ContentOpportunities::GAP * 100)),
            'average' => (string) $present->percent($opportunities['average']),
        ]),
    ],
];
?>
<section class="card mb-0" aria-labelledby="insights-opportunities">
  <div class="card-body">
    <h2 id="insights-opportunities" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.opportunities.title')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.opportunities.hint', ['views' => $present->number(\App\Services\Analytics\ContentOpportunities::MIN_VIEWS)])) ?></p>
    <?php if ($opportunities['busyFrom'] === null) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.opportunities.notEnough')) ?></p>
    <?php } else { ?>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
      <?php foreach ($opportunityLists as $kind => $list) { ?>
      <div>
        <h3 class="text-sm font-medium text-slate-800 dark:text-zink-100"><?= e($list['title']) ?></h3>
        <p class="mt-0.5 mb-2 text-xs text-slate-500 dark:text-zink-300"><?= e($list['rule']) ?></p>
        <?php if ($opportunities[$kind] === []) { ?>
        <p class="text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.opportunities.none')) ?></p>
        <?php } else { ?>
        <ul class="flex flex-col divide-y divide-slate-100 dark:divide-zink-600 text-sm">
          <?php foreach ($opportunities[$kind] as $standout) { ?>
          <li class="flex items-baseline justify-between gap-3 py-1.5">
            <?php if ($standout['title'] === null) { ?>
            <span class="min-w-0 truncate text-slate-400 dark:text-zink-400"><?= e($t('analytics.topPosts.deleted')) ?></span>
            <?php } else { ?>
            <a href="<?= e($postInsightsHref($standout)) ?>" class="min-w-0 truncate text-slate-700 hover:text-custom-500 dark:text-zink-100" dir="auto"><?= e((string) $standout['title']) ?></a>
            <?php } ?>
            <span class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-zink-300">
              <?= e($t('analytics.opportunities.numbers', ['views' => $present->number((int) $standout['views']), 'rate' => (string) $present->percent($standout['read_rate'])])) ?>
            </span>
          </li>
          <?php } ?>
        </ul>
        <?php } ?>
      </div>
      <?php } ?>
    </div>
    <?php } ?>
  </div>
</section>
