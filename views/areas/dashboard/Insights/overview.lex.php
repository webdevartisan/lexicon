{% extends "back.lex.php" %}

{% block title %}<?= e((string) $blog['blog_name']) ?> · <?= e($t('navigation.insightsPages.overview')) ?>{% endblock %}
{% block subtitle %}<?= e($t('analytics.pages.overview')) ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}
<?php
$cards = [
    ['views', 'analytics.metrics.views', 'analytics.metrics.viewsHint', 'number'],
    ['visitors', 'analytics.metrics.visitors', $scope === 'author' ? 'analytics.metrics.visitorsPerPostHint' : 'analytics.metrics.visitorsHint', 'number'],
    ['visits', 'analytics.metrics.visits', 'analytics.metrics.visitsHint', 'number'],
    ['engaged_visit_rate', 'analytics.metrics.engagedVisits', 'analytics.metrics.engagedVisitsHint', 'percent'],
    ['read_ratio', 'analytics.metrics.readRatio', 'analytics.metrics.readRatioHint', 'percent'],
    ['top_source', 'analytics.metrics.topSource', 'analytics.metrics.topSourceHint', 'source'],
];
$goalKeys = $scope === 'author' ? ['comment', 'like', 'save', 'share'] : ['subscribe', 'comment', 'like', 'save', 'share', 'signup'];
$summary = $present->summary($metrics, $breakdowns, $previousPeriod);
?>
  <?php if ($summary !== null) { ?>
  <p class="text-15 text-slate-700 dark:text-zink-100"><?= e($summary) ?></p>
  <?php } ?>
  {% include "partials/dashboard/insights/_metric_grid.lex.php" %}

  {% include "partials/dashboard/insights/_chart.lex.php" %}

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <?php if ($scope === 'blog' || $scope === 'author') {
        $topPostsMore = $insightsLink('content'); ?>
    {% include "partials/dashboard/insights/_top_posts.lex.php" %}
    <?php } ?>
    <?php
    $dimension = 'source';
    $rows = $breakdowns['source'] ?? [];
    $breakdownMore = $insightsLink('acquisition');
    $breakdownCounts = null;
    $breakdownEmpty = null; ?>
    {% include "partials/dashboard/insights/_breakdown.lex.php" %}
    <?php $breakdownMore = null; ?>
  </div>

  <?php $goalsMore = $insightsLink('goals'); ?>
  {% include "partials/dashboard/insights/_goals.lex.php" %}

{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
