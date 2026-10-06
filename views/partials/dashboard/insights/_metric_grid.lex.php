<?php
/**
 * A row of headline cards. Each entry of $cards is [metric key, label key, hint key, format],
 * or with a fifth true when label and hint are already text (the control panel's English).
 * The source format shows the top source instead of a number.
 */
?>
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
    <?php foreach ($cards as $card) {
        [$key, $labelKey, $hintKey, $format] = $card;
        $metric = $metrics[$key] ?? null;
        $cardLabel = ($card[4] ?? false) ? $labelKey : $t($labelKey);
        $cardHint = ($card[4] ?? false) ? $hintKey : $t($hintKey);
        $cardValue = $format === 'source' ? $present->topSource($breakdowns) : $present->metric($format, $metric['value'] ?? null);
        $cardChange = $metric === null ? null : $present->change(
            $metric['change'],
            (string) $present->metric($format, $metric['previous']),
            $previousPeriod,
            in_array($key, $lowerIsBetter, true)
        ); ?>
    {% include "partials/dashboard/insights/_metric_card.lex.php" %}
    <?php } ?>
  </div>
