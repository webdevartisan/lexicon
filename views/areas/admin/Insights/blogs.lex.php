{% extends "back.lex.php" %}

{% block title %}Blogs · Insights{% endblock %}
{% block subtitle %}Which blogs were read, which are rising, and how each one did.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}
<?php
$statusBadge = [
    'draft' => 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zink-600 dark:text-zink-100 dark:border-zink-500',
    'archived' => 'bg-slate-800 text-slate-100 border-slate-900 dark:bg-zink-900 dark:text-zink-100 dark:border-zink-600',
    'suspended' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:border-red-800',
];
$risingCount = 0;
foreach ($report['topBlogs'] as $row) {
    $risingCount += \App\Services\Analytics\AnalyticsReportService::isRising((int) $row['views'], (int) $row['previous_views']) ? 1 : 0;
}
$metrics = ['blogs_read' => ['value' => $report['blogsRead'], 'change' => null, 'previous' => null], 'rising' => ['value' => $risingCount, 'change' => null, 'previous' => null]];
$cards = [
    ['blogs_read', 'Blogs read', 'Blogs that had at least one view in this period.', 'number', true],
    ['rising', 'Rising', 'Blogs in the list below with at least twice the views of the period before.', 'number', true],
];
?>
  {% include "partials/dashboard/insights/_metric_grid.lex.php" %}

  {% include "partials/dashboard/insights/_chart.lex.php" %}

  <section class="card mb-0" aria-labelledby="insights-top-blogs">
    <div class="card-body">
      <div class="flex items-center justify-between gap-2 mb-3">
        <h2 id="insights-top-blogs" class="text-15 font-semibold text-slate-800 dark:text-zink-50">Top blogs</h2>
        <?php if ($report['topBlogs'] !== []) { ?>
        <a href="<?= e(lurl($basePath.'/export').'?'.http_build_query($range->query() + ['dimension' => 'blogs'])) ?>"
           class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-custom-500 dark:text-zink-300"
           data-tooltip data-tooltip-content="Every blog with views in this period, as a CSV file." data-tooltip-placement="top">
          {% cache 'lucide:download:traffic-export' ttl=31536000 %}<i data-lucide="download" class="size-3.5" aria-hidden="true"></i>{% endcache %}
          <?= e($downloadLabel) ?>
        </a>
        <?php } ?>
      </div>
      <?php if ($report['topBlogs'] === []) { ?>
      <p class="py-6 text-center text-sm text-slate-500 dark:text-zink-300">No blog had views in this period.</p>
      <?php } else { ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold">Blog</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.views')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><span class="sr-only">Change in views</span></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.visitors')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.readRatio')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.avgRead')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.engagedVisits')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['topBlogs'] as $row) {
                $views = (int) $row['views'];
                $engaged = (int) $row['engaged_views'];
                $status = (string) ($row['status'] ?? '');
                $rising = \App\Services\Analytics\AnalyticsReportService::isRising($views, (int) $row['previous_views']);
                $change = $present->change($row['change'], $present->number((int) $row['previous_views']), $previousPeriod);
                $changeIcon = $changeIcons[$change['direction']] ?? 'minus'; ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0 hover:bg-slate-50 dark:hover:bg-zink-600/50">
              <td class="px-3 py-2">
                <?php if ($row['blog_name'] === null) { ?>
                <span class="text-slate-400 dark:text-zink-400">Deleted blog</span>
                <?php } else { ?>
                <span class="inline-flex flex-wrap items-center gap-2">
                  <a href="<?= e(lurl('/dashboard/blog/'.(int) $row['blog_id'].'/insights').'?'.http_build_query($range->query())) ?>"
                     class="text-slate-800 hover:text-custom-500 dark:text-zink-100" dir="auto"
                     title="Open this blog's Insights"><?= e((string) $row['blog_name']) ?></a>
                  <?php if ($rising) { ?>
                  <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full border bg-green-100 text-green-700 border-green-200 dark:bg-green-500/20 dark:text-green-300 dark:border-green-500/30">Rising</span>
                  <?php } ?>
                  <?php if (isset($statusBadge[$status])) { ?>
                  <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full border <?= $statusBadge[$status] ?>"><?= e(ucfirst($status)) ?></span>
                  <?php } ?>
                </span>
                <?php } ?>
              </td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($views)) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left">
                <?php if ($change['direction'] === 'none') { ?>
                <span class="text-xs text-slate-400 dark:text-zink-400 cursor-help" tabindex="0"
                      data-tooltip data-tooltip-content="No views in the previous period to compare with." data-tooltip-placement="bottom">New</span>
                <?php } else { ?>
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-medium rounded cursor-help <?= $changeTones[$change['tone']] ?>" tabindex="0"
                      data-tooltip data-tooltip-content="<?= e($change['label']) ?>" data-tooltip-placement="bottom">
                  {% cache 'lucide:traffic-change:' . $changeIcon ttl=31536000 %}<i data-lucide="<?= e($changeIcon) ?>" class="size-3.5" aria-hidden="true"></i>{% endcache %}
                  <span aria-hidden="true"><?= e($change['short']) ?></span>
                  <span class="sr-only"><?= e($change['label']) ?></span>
                </span>
                <?php } ?>
              </td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number((int) $row['visitors'])) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent($views > 0 ? (int) $row['read_views'] / $views : null) ?? $none) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->duration($engaged > 0 ? (int) $row['engaged_seconds'] / $engaged : null) ?? $none) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent((int) $row['visits'] > 0 ? (int) $row['engaged_visits'] / (int) $row['visits'] : null) ?? $none) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php } ?>
    </div>
  </section>

<?php
$aboutParagraphs = [
    'Each blog\'s row shows its own numbers, in that blog\'s timezone, the same as its owner sees them. The chart and Blogs read use UTC days.',
    'A blog is rising when it had at least twice the views of the period before, and enough views for that to mean something.',
];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
