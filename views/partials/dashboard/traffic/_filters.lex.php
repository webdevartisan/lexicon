<?php if ($filters !== []) { ?>
<div class="flex flex-wrap items-center gap-2 px-4 py-3 text-sm border rounded-md border-sky-200 bg-sky-50 dark:bg-sky-500/10 dark:border-sky-500/30" role="status">
  <span class="font-medium text-slate-700 dark:text-zink-100"><?= e($t('traffic.filters.showing')) ?></span>
  <?php foreach ($filters as $filterDimension => $filterValue) {
      $chip = $present->heading($filterDimension).': '.$present->label($filterDimension, $filterValue, $filterNames[$filterDimension] ?? null); ?>
  <a href="<?= e($filterWithout($filterDimension)) ?>"
     class="inline-flex items-center gap-1 px-2 py-0.5 text-xs rounded-full border bg-white border-sky-200 text-slate-700 hover:border-custom-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100"
     aria-label="<?= e($t('traffic.filters.remove', ['item' => $chip])) ?>">
    <span dir="auto"><?= e($chip) ?></span>
    {% cache 'lucide:x:traffic-filter' ttl=31536000 %}<i data-lucide="x" class="size-3" aria-hidden="true"></i>{% endcache %}
  </a>
  <?php } ?>
  <a href="<?= e($filterWithout(null)) ?>" class="text-xs underline hover:no-underline text-custom-500"><?= e($t('traffic.filters.clear')) ?></a>
  <span class="w-full text-xs text-slate-500 dark:text-zink-300"><?= e($t('traffic.filters.note')) ?></span>
</div>
<?php } ?>
