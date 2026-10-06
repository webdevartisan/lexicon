<?php
$cards = [
    ['visitors', 'analytics.metrics.visitors', $scope === 'author' ? 'analytics.metrics.visitorsPerPostHint' : 'analytics.metrics.visitorsHint', 'number'],
    ['returning_share', 'analytics.metrics.returning', $filters === [] ? 'analytics.metrics.returningHint' : 'analytics.filters.noReturning', 'percent'],
];
$hourlyHint = $isAdmin ? 'Views by day of the week and hour, in UTC. Darker squares had more views.' : $t('analytics.hourly.hint');
$dimensions = ['country', 'locale', 'device', 'browser', 'os'];
?>
  {% include "partials/dashboard/insights/_metric_grid.lex.php" %}

  {% include "partials/dashboard/insights/_breakdown_list.lex.php" %}

  {% include "partials/dashboard/insights/_hourly.lex.php" %}
