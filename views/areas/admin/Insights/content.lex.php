{% extends "back.lex.php" %}

{% block title %}Content · Insights{% endblock %}
{% block subtitle %}<?= e($scope === 'site'
    ? 'Which posts and pages were read across every blog, and what readers searched for on Discover.'
    : 'Which of the platform\'s own pages were read, and what readers searched for on Discover.') ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}

  <?php if ($scope === 'site') { ?>
  {% include "partials/dashboard/insights/_top_posts.lex.php" %}

  {% include "partials/dashboard/insights/_opportunities.lex.php" %}

  {% include "partials/dashboard/insights/_age_curve.lex.php" %}
  <?php } ?>

  <?php $dimensions = ['page', 'category', 'tag']; ?>
  {% include "partials/dashboard/insights/_breakdown_list.lex.php" %}

  <?php if ($searchTerms !== null) {
      $breakdownHeadings = ['search' => 'Searched on Discover', 'search_empty' => 'Searches that found nothing'];
      $breakdownCounts = ['searches' => 'Searches', 'visitors' => $t('analytics.breakdowns.visitors')];
      $exportable = false; ?>
  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <?php foreach (['search' => $searchTerms, 'search_empty' => $emptySearches] as $dimension => $terms) {
        $rows = array_map(static fn (array $row): array => ['value' => $row['value'], 'searches' => $row['searches'], 'visitors' => $row['visitors']], $terms);
        $breakdownEmpty = $dimension === 'search'
            ? 'No search was made by at least 3 different visitors in this range.'
            : 'Every search made by at least 3 different visitors found something.'; ?>
    {% include "partials/dashboard/insights/_breakdown.lex.php" %}
    <?php } ?>
  </div>
  <?php
      $breakdownHeadings = [];
      $breakdownCounts = null;
      $breakdownEmpty = null;
  } ?>

<?php
$aboutParagraphs = [
    'Posts are ranked by views in the range, with each blog\'s own numbers in that blog\'s timezone. Each opens the post\'s page in that blog\'s Insights.',
    'Discover searches are kept with the views they came with, so they show for the last '.$rawRetentionDays.' days, and only once at least 3 different visitors made the same search.',
];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
