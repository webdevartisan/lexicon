<section class="card mb-0" aria-labelledby="traffic-scroll">
  <div class="card-body">
    <h2 id="traffic-scroll" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('traffic.scroll.title')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('traffic.scroll.hint')) ?></p>
    <?php if ($metrics['scroll_25']['value'] === null) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('traffic.breakdowns.empty')) ?></p>
    <?php } else { ?>
    <dl class="flex flex-col gap-2 text-sm">
      <?php foreach ([25, 50, 75, 100] as $step) {
          $share = (float) $metrics['scroll_'.$step]['value']; ?>
      <div class="grid grid-cols-[8rem_1fr_3rem] items-center gap-3">
        <dt class="text-slate-600 dark:text-zink-200"><?= e($t('traffic.scroll.step'.$step)) ?></dt>
        <dd class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-zink-600" aria-hidden="true">
          <span class="block h-full rounded-full bg-custom-500" style="width: <?= max(1, (int) round($share * 100)) ?>%"></span>
        </dd>
        <dd class="ltr:text-right rtl:text-left tabular-nums text-slate-700 dark:text-zink-100"><?= e((string) $present->percent($share)) ?></dd>
      </div>
      <?php } ?>
    </dl>
    <?php } ?>
  </div>
</section>
