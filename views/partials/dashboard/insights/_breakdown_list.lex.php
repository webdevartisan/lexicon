<?php
/**
 * One card per breakdown in $dimensions that the page's report has, in that order.
 */
?>
  <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($dimensions as $dimension) {
        if (!array_key_exists($dimension, $breakdowns)) {
            continue;
        }
        $rows = $breakdowns[$dimension];
        $breakdownCounts = null;
        $breakdownEmpty = null;
        $exportable = true; ?>
    {% include "partials/dashboard/insights/_breakdown.lex.php" %}
    <?php } ?>
  </div>
