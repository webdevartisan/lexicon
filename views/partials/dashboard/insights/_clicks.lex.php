<?php
/**
 * Shares by network, links to other sites, and downloads: short lists with no export.
 * Goes inside the page's grid.
 */
$breakdownHeadings = [
    'network' => $t('analytics.clicks.share'),
    'outbound' => $t('analytics.clicks.outbound'),
    'download' => $t('analytics.clicks.download'),
];
foreach (['network' => $report['shares'] ?? [], 'outbound' => $report['outbound'], 'download' => $report['downloads']] as $dimension => $rows) {
    $rows = array_map(static fn (array $row): array => ['value' => $row['value'], 'clicks' => $row['clicks']], $rows);
    $breakdownCounts = ['clicks' => $t($dimension === 'network' ? 'analytics.clicks.shares' : 'analytics.clicks.clicks')];
    $breakdownEmpty = $t($dimension === 'network' ? 'analytics.clicks.noShares' : 'analytics.clicks.empty');
    $exportable = false; ?>
    {% include "partials/dashboard/insights/_breakdown.lex.php" %}
<?php }
$breakdownHeadings = [];
$breakdownCounts = null;
$breakdownEmpty = null;
?>
