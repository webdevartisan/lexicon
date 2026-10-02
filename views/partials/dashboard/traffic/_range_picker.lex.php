<?php
$rangeText = $t('traffic.range.span', ['from' => $present->date($range->fromDate()), 'to' => $present->date($range->toDate())]);
$applyLabel = $t('traffic.range.apply');
$fromLabel = $t('traffic.range.from');
$toLabel = $t('traffic.range.to');
$fromValue = $range->fromDate();
$toValue = $range->toDate();
?>
<details class="relative" data-range-menu>
  <summary class="list-none [&::-webkit-details-marker]:hidden inline-flex items-center gap-2 px-3 py-2 text-sm rounded-md border cursor-pointer select-none border-slate-200 bg-white text-slate-700 hover:border-custom-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100">
    {% cache 'lucide:calendar-range:traffic-range' ttl=31536000 %}<i data-lucide="calendar-range" class="size-4 text-slate-500 dark:text-zink-300" aria-hidden="true"></i>{% endcache %}
    <span class="sr-only"><?= e($t('traffic.range.label')) ?>:</span>
    <span><?= e($rangeText) ?></span>
    {% cache 'lucide:chevron-down:traffic-range' ttl=31536000 %}<i data-lucide="chevron-down" class="size-4 text-slate-400" aria-hidden="true"></i>{% endcache %}
  </summary>

  <div class="absolute z-[1002] mt-2 ltr:right-0 rtl:left-0 flex flex-col sm:flex-row w-max max-w-[calc(100vw-2rem)] rounded-md border shadow-lg border-slate-200 bg-white dark:bg-zink-700 dark:border-zink-500">
    <nav aria-label="<?= e($t('traffic.range.label')) ?>" class="flex flex-wrap sm:flex-col gap-1 p-2 border-b sm:border-b-0 ltr:sm:border-r rtl:sm:border-l border-slate-200 dark:border-zink-500 sm:min-w-[10rem]">
      <?php foreach (array_keys(\App\ValueObjects\TrafficRange::PRESETS) as $preset) {
          $active = $range->preset === $preset; ?>
      <a href="<?= e($pageUrl(['range' => $preset])) ?>"<?= $active ? ' aria-current="page"' : '' ?>
         class="px-3 py-1.5 text-sm rounded-md <?= $active
             ? 'bg-custom-500 text-white'
             : 'text-slate-600 hover:bg-slate-100 dark:text-zink-200 dark:hover:bg-zink-600' ?>">
        <?= e($t('traffic.range.'.$preset)) ?>
      </a>
      <?php } ?>
    </nav>

    <form method="get" action="<?= e(lurl($pagePath)) ?>" class="flex flex-col gap-3 p-3" data-range-form
          data-max-days="<?= \App\ValueObjects\TrafficRange::MAX_DAYS ?>" data-max-date="<?= e($today) ?>">
      <input type="hidden" name="range" value="custom">
      <p class="text-xs font-medium text-slate-500 dark:text-zink-300"><?= e($t('traffic.range.custom')) ?></p>
      <div class="hidden" data-range-calendar></div>
      <div class="grid grid-cols-2 gap-2" data-range-fields>
        {% cmp="input" type="date" name="from" label="{$fromLabel}" value="{$fromValue}" max="{$today}" required="true" %}
        {% cmp="input" type="date" name="to" label="{$toLabel}" value="{$toValue}" max="{$today}" required="true" %}
      </div>
      <p class="hidden text-xs text-red-600 dark:text-red-400" data-range-too-long role="alert">
        <?= e($t('traffic.range.tooLong', ['days' => \App\ValueObjects\TrafficRange::MAX_DAYS])) ?>
      </p>
      <div class="flex justify-end">
        {% cmp="btn" type="submit" variant="blue" label="{$applyLabel}" %}
      </div>
    </form>
  </div>
</details>
