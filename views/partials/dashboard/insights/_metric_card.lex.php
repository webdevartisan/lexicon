<?php
$changeIcon = $changeIcons[$cardChange['direction'] ?? 'flat'] ?? 'minus';
$changeClass = $changeTones[$cardChange['tone'] ?? 'neutral'];
?>
<div class="card mb-0">
  <div class="card-body">
    <div class="flex items-start justify-between gap-2">
      <h2 class="text-sm font-medium text-slate-500 dark:text-zink-300"><?= e($cardLabel) ?></h2>
      <button type="button" class="shrink-0 -m-1 p-1 rounded text-slate-400 hover:text-custom-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 dark:text-zink-400"
              data-tooltip data-tooltip-content="<?= e($cardHint) ?>" data-tooltip-placement="top"
              aria-label="<?= e($t('analytics.metrics.about', ['metric' => $cardLabel])) ?>">
        {% cache 'lucide:help-circle:traffic-card' ttl=31536000 %}<i data-lucide="help-circle" class="size-4" aria-hidden="true"></i>{% endcache %}
      </button>
    </div>
    <div class="flex flex-wrap items-center gap-2 mt-2">
      <p class="text-2xl font-semibold text-slate-800 dark:text-zink-50 truncate" dir="auto"><?= e($cardValue ?? $none) ?></p>
      <?php if ($cardChange !== null && $cardChange['direction'] !== 'none') { ?>
      <span class="inline-flex items-center gap-1 px-1.5 py-0.5 text-xs font-medium rounded cursor-help <?= $changeClass ?>" tabindex="0"
            data-tooltip data-tooltip-content="<?= e($cardChange['label']) ?>" data-tooltip-placement="bottom">
        {% cache 'lucide:traffic-change:' . $changeIcon ttl=31536000 %}<i data-lucide="<?= e($changeIcon) ?>" class="size-3.5" aria-hidden="true"></i>{% endcache %}
        <span aria-hidden="true"><?= e($cardChange['short']) ?></span>
        <span class="sr-only"><?= e($cardChange['label']) ?></span>
      </span>
      <?php } ?>
    </div>
  </div>
</div>
