<?php
$recentSource = static function (array $row) use ($present): string {
    if ($row['source'] === null) {
        return $present->label('channel', $row['channel']);
    }

    return $row['channel'] === 'lexicon' ? $present->label('lexicon', $row['source']) : $row['source'];
};
?>
<details class="relative" data-recent>
  <summary class="list-none [&::-webkit-details-marker]:hidden inline-flex items-center gap-1.5 cursor-pointer rounded hover:text-custom-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500">
    <span class="inline-block size-2 rounded-full <?= $rightNow > 0 ? 'bg-green-500' : 'bg-slate-400' ?>" aria-hidden="true"></span>
    <?= e($t('traffic.rightNow', ['count' => $present->number($rightNow)])) ?>
    {% cache 'lucide:chevron-down:traffic-recent' ttl=31536000 %}<i data-lucide="chevron-down" class="size-3.5" aria-hidden="true"></i>{% endcache %}
  </summary>
  <div class="absolute z-[1002] mt-2 ltr:left-0 rtl:right-0 w-72 max-w-[calc(100vw-2rem)] p-3 rounded-md border shadow-lg border-slate-200 bg-white text-slate-600 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200">
    <p class="mb-2 text-xs"><?= e($rightNowHint) ?></p>
    <?php if ($recent['pages'] === []) { ?>
    <p class="text-xs text-slate-500 dark:text-zink-300"><?= e($t('traffic.recent.empty')) ?></p>
    <?php } else { ?>
      <?php foreach (['pages' => $t('traffic.recent.pages'), 'sources' => $t('traffic.recent.sources')] as $part => $partTitle) { ?>
    <h3 class="mt-2 mb-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-zink-300"><?= e($partTitle) ?></h3>
    <ul class="flex flex-col gap-1 text-xs">
        <?php foreach ($recent[$part] as $item) { ?>
      <li class="flex justify-between gap-3">
        <span class="truncate" dir="auto"><?= e($part === 'pages' ? $item['value'] : $recentSource($item)) ?></span>
        <span class="tabular-nums text-slate-500 dark:text-zink-300"><?= e($present->number($item['views'])) ?></span>
      </li>
        <?php } ?>
    </ul>
      <?php } ?>
    <?php } ?>
  </div>
</details>
