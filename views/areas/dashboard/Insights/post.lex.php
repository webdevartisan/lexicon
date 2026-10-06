{% extends "back.lex.php" %}

{% block title %}<?= e((string) ($post['title'] ?? '')) ?> · <?= e($t('navigation.insights')) ?>{% endblock %}
{% block subtitle %}<?= e($t('analytics.postSubtitle')) ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}
<?php
$cards = [
    ['views', 'analytics.metrics.views', 'analytics.metrics.viewsHint', 'number'],
    ['visitors', 'analytics.metrics.visitors', 'analytics.metrics.visitorsHint', 'number'],
    ['avg_read_seconds', 'analytics.metrics.avgRead', 'analytics.metrics.avgReadHint', 'duration'],
    ['read_ratio', 'analytics.metrics.readRatio', 'analytics.metrics.readRatioHint', 'percent'],
    ['read_to_end_ratio', 'analytics.metrics.readToEnd', 'analytics.metrics.readToEndHint', 'percent'],
    ['top_source', 'analytics.metrics.topSource', 'analytics.metrics.topSourceHint', 'source'],
];
$goalKeys = ['comment', 'like', 'save', 'share'];
$hourlyHint = $t('analytics.hourly.hint');
?>
  {% include "partials/dashboard/insights/_metric_grid.lex.php" %}

  {% include "partials/dashboard/insights/_chart.lex.php" %}

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    {% include "partials/dashboard/insights/_performance.lex.php" %}
    {% include "partials/dashboard/insights/_languages.lex.php" %}
  </div>

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    {% include "partials/dashboard/insights/_goals.lex.php" %}
    {% include "partials/dashboard/insights/_scroll.lex.php" %}
  </div>

  {% include "partials/dashboard/insights/_hourly.lex.php" %}

  <?php $dimensions = ['source', 'lexicon', 'channel', 'next', 'related', 'country', 'device', 'browser', 'os', 'utm_campaign', 'utm_source', 'utm_medium']; ?>
  {% include "partials/dashboard/insights/_breakdown_list.lex.php" %}

  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-4">
    {% include "partials/dashboard/insights/_reactions.lex.php" %}
    {% include "partials/dashboard/insights/_clicks.lex.php" %}
  </div>

{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
