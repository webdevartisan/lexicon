<?php
$chartSeries = $report['searchSeries'];
$chartTitle = $t('analytics.seo.overTime');
?>
  {% include "partials/dashboard/insights/_chart.lex.php" %}
<?php
$chartSeries = null;
$chartTitle = null;
?>

  {% include "partials/dashboard/insights/_search_compare.lex.php" %}

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <?php
    $breakdownHeadings = ['source' => $t('analytics.seo.engines')];
    $dimension = 'source';
    $rows = $report['searchEngines'];
    $breakdownCounts = null;
    $breakdownEmpty = $t('analytics.seo.noSearch');
    $exportable = false; ?>
    {% include "partials/dashboard/insights/_breakdown.lex.php" %}
    <?php
    $breakdownHeadings = [];
    $dimension = 'search_entry';
    $rows = $breakdowns['search_entry'] ?? null;
    $exportable = true;
    if ($rows !== null) { ?>
    {% include "partials/dashboard/insights/_breakdown.lex.php" %}
    <?php }
    $breakdownEmpty = null; ?>
  </div>

  <?php if (($report['health'] ?? null) !== null) { ?>
  {% include "partials/dashboard/insights/_seo_health.lex.php" %}
  <?php } ?>
