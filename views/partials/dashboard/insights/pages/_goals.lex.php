<?php
$goalKeys = $scope === 'author' ? ['comment', 'like', 'save', 'share'] : ['subscribe', 'comment', 'like', 'save', 'share', 'signup'];
$sourceGoals = array_values(array_diff($goalKeys, ['signup']));
$goalsBy = ['channel' => $report['goalsByChannel'], 'source' => $report['goalsBySource']];
?>
  {% include "partials/dashboard/insights/_goals.lex.php" %}

  <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
    <?php foreach ($goalsBy as $dimension => $goalRows) { ?>
    <section class="card mb-0" aria-labelledby="insights-goals-by-<?= e($dimension) ?>">
      <div class="card-body">
        <h2 id="insights-goals-by-<?= e($dimension) ?>" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.goals.by.'.$dimension)) ?></h2>
        <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.goals.byHint')) ?></p>
        <?php if ($goalRows === []) { ?>
        <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.goals.byEmpty')) ?></p>
        <?php } else { ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
              <tr class="border-b border-slate-200 dark:border-zink-500">
                <th scope="col" class="px-3 py-2 font-semibold"><?= e($present->heading($dimension)) ?></th>
                <?php foreach ($sourceGoals as $goal) { ?>
                <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.goals.'.$goal)) ?></th>
                <?php } ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($goalRows as $value => $reached) { ?>
              <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
                <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right text-slate-700 dark:text-zink-100" dir="auto"><?= e($present->label($dimension, (string) $value)) ?></th>
                <?php foreach ($sourceGoals as $goal) { ?>
                <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($reached[$goal] ?? 0)) ?></td>
                <?php } ?>
              </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
        <?php } ?>
      </div>
    </section>
    <?php } ?>

    {% include "partials/dashboard/insights/_clicks.lex.php" %}
  </div>
