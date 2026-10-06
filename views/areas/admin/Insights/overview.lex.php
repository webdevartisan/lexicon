{% extends "back.lex.php" %}

{% block title %}Insights{% endblock %}
{% block subtitle %}<?= e($scope === 'site'
    ? 'Reading across the whole website at a glance: how much, where readers come from, and what they did.'
    : 'The platform\'s own pages at a glance: the home page, Discover, the guides, profiles, sign-in and sign-up.') ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}
<?php
$isSite = $scope === 'site';
$cards = $isSite ? [
    ['views', $t('analytics.metrics.views'), 'Pages opened anywhere on the site: its own pages and every blog. Reloading the same page within 30 minutes counts once.', 'number', true],
    ['visitors', $t('analytics.metrics.visitors'), 'Different people each day, counted once however many pages and blogs they read, then added up over the days.', 'number', true],
    ['visits', $t('analytics.metrics.visits'), 'One reader reading one or more pages anywhere on the site with less than 30 minutes between them.', 'number', true],
    ['active_blogs', 'Blogs read', 'Blogs that had at least one view in this period.', 'number', true],
    ['engaged_visit_rate', $t('analytics.metrics.engagedVisits'), 'Share of visits with two or more pages, 10 seconds or more of reading, or a like, comment, save, share or sign-up.', 'percent', true],
    ['read_ratio', $t('analytics.metrics.readRatio'), 'Share of views where the reader stayed 30 seconds or longer.', 'percent', true],
] : [
    ['views', $t('analytics.metrics.views'), 'Pages of the platform itself opened by readers. Reloading the same page within 30 minutes counts once.', 'number', true],
    ['visitors', $t('analytics.metrics.visitors'), 'Different people each day on the platform\'s own pages, then added up over the days.', 'number', true],
    ['visits', $t('analytics.metrics.visits'), 'Visits that began on one of the platform\'s own pages.', 'number', true],
    ['engaged_visit_rate', $t('analytics.metrics.engagedVisits'), 'Share of those visits with two or more pages, 10 seconds or more of reading, or a sign-up.', 'percent', true],
    ['read_ratio', $t('analytics.metrics.readRatio'), 'Share of views where the reader stayed 30 seconds or longer.', 'percent', true],
    ['top_source', $t('analytics.metrics.topSource'), 'The site or channel that sent the most readers.', 'source', true],
];
$goalKeys = ['subscribe', 'comment', 'like', 'save', 'share', 'signup'];
?>
  {% include "partials/dashboard/insights/_metric_grid.lex.php" %}

  {% include "partials/dashboard/insights/_chart.lex.php" %}

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <?php if ($isSite) {
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

  <?php if ($isSite) {
      $goalsMore = $insightsLink('goals'); ?>
  {% include "partials/dashboard/insights/_goals.lex.php" %}
  <?php } ?>

<?php
$aboutParagraphs = $isSite ? [
    'Every public page counts: the platform\'s own pages and every blog. Crawlers, link previews, browsers that ask not to be tracked and administrators are left out, and a blog\'s own team is left out of that blog unless its owner chose otherwise.',
    'The cards, the chart and the lists use UTC days and count a visitor once across the whole site, however many blogs they read. The Blogs page shows each blog\'s own numbers, in that blog\'s timezone, the same as its owner sees them.',
] : [
    'The home page, Discover, the guides, the about and legal pages, contact, sign-in, sign-up and profiles, which are counted together as one page.',
    'Days are UTC.',
];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
