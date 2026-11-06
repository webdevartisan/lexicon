{% extends "back.lex.php" %}

{% block title %}<?= e($t('journey-map::guide.title')) ?>{% endblock %}
{% block subtitle %}<span class="hidden sm:inline"><?= e($t('journey-map::guide.subtitle')) ?></span>{% endblock %}
{% block body %}
<?php
$g = static fn (string $key): string => e($t('journey-map::guide.'.$key));
$muted = 'text-sm text-slate-500 dark:text-zink-300';
$heading = 'text-15 font-semibold text-slate-800 dark:text-zink-50';
?>
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
    <section class="card">
      <div class="card-body space-y-3">
        <h2 class="<?= $heading ?>"><?= $g('howHeading') ?></h2>
        <ol class="list-decimal ps-5 space-y-2 text-sm text-slate-700 dark:text-zink-200">
          <?php foreach (['step1', 'step2', 'step3', 'step4', 'step5'] as $step) { ?>
            <li><?= $g($step) ?></li>
          <?php } ?>
        </ol>
        <p class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-zink-500 dark:bg-zink-600 <?= $muted ?>">
          <?= $g('privacy') ?>
        </p>
      </div>
    </section>

    <section class="card">
      <div class="card-body space-y-3">
        <h2 class="<?= $heading ?>"><?= $g('readersHeading') ?></h2>
        <ul class="list-disc ps-5 space-y-2 text-sm text-slate-700 dark:text-zink-200">
          <?php foreach (['readers1', 'readers2', 'readers3'] as $line) { ?>
            <li><?= $g($line) ?></li>
          <?php } ?>
        </ul>
        <p>
          <a class="text-sm font-medium text-custom-500 underline underline-offset-2 hover:decoration-2"
            href="<?= e(lurl($journeyUrl)) ?>"><?= $g('openJourney') ?></a>
        </p>
      </div>
    </section>
  </div>

  <section class="card mt-5">
    <div class="card-body space-y-3">
      <h2 class="<?= $heading ?>"><?= $g('stopsHeading') ?></h2>
      <?php if ($stops === []) { ?>
        <p class="<?= $muted ?>"><?= $g('noStops') ?></p>
      <?php } else { ?>
        <div class="overflow-x-auto">
          <table class="w-full text-sm text-start">
            <thead class="<?= $muted ?>">
              <tr>
                <th class="py-2 pe-3 text-start font-medium"><?= $g('order') ?></th>
                <th class="py-2 pe-3 text-start font-medium"><?= $g('post') ?></th>
                <th class="py-2 pe-3 text-start font-medium"><?= $g('place') ?></th>
                <th class="py-2 text-end font-medium"><span class="sr-only"><?= $g('edit') ?></span></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-zink-500">
              <?php foreach ($stops as $stop) { ?>
                <tr>
                  <td class="py-2 pe-3 <?= $muted ?>">
                    <?= $stop['number'] === null ? $g('byDate') : (int) $stop['number'] ?>
                  </td>
                  <td class="py-2 pe-3 text-slate-800 dark:text-zink-100">
                    <?= e($stop['title']) ?>
                    <?php if (!$stop['public']) { ?>
                      <span class="ms-1 text-xs <?= $muted ?>">(<?= $g('hidden') ?>)</span>
                    <?php } ?>
                  </td>
                  <td class="py-2 pe-3 text-slate-700 dark:text-zink-200">
                    <?= e($stop['place']) ?>
                    <?php if ($stop['precision'] === 'area') { ?>
                      <span class="text-xs <?= $muted ?>">(<?= $g('approximate') ?>)</span>
                    <?php } ?>
                  </td>
                  <td class="py-2 text-end">
                    <a class="text-custom-500 underline underline-offset-2 hover:decoration-2"
                      href="<?= e(lurl($stop['edit'])) ?>"><?= $g('edit') ?></a>
                  </td>
                </tr>
              <?php } ?>
            </tbody>
          </table>
        </div>
      <?php } ?>
    </div>
  </section>
</div>
{% endblock %}
