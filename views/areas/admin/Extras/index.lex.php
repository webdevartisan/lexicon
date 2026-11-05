{% extends "back.lex.php" %}

{% block title %}Extras{% endblock %}
{% block subtitle %}<span class="hidden sm:inline">Optional features installed on this site. Switch one off here and it is off everywhere.</span>{% endblock %}
{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <?php if ($extras === []) { ?>
    {% cmp="empty-state" icon="puzzle" title="No extras are installed." %}
  <?php } else { ?>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
      <?php foreach ($extras as $extra) { ?>
        <section class="card">
          <div class="card-body space-y-3">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h2 class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($extra['name']) ?></h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zink-300"><?= e($extra['description']) ?></p>
              </div>
              <div class="shrink-0 flex items-center gap-2">
                <span class="px-2 py-0.5 text-xs font-medium rounded-full border bg-slate-100 text-slate-600 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-500"><?= e($extra['scope']) ?></span>
                <?php if ($extra['status'] === 'On') { ?>
                  <span class="px-2 py-0.5 text-xs font-medium rounded-full border bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:text-green-300 dark:border-green-800">On</span>
                <?php } elseif ($extra['status'] === 'Paused') { ?>
                  <span class="px-2 py-0.5 text-xs font-medium rounded-full border bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/40 dark:text-amber-200 dark:border-amber-800">Paused</span>
                <?php } else { ?>
                  <span class="px-2 py-0.5 text-xs font-medium rounded-full border bg-slate-100 text-slate-600 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-500">Off</span>
                <?php } ?>
              </div>
            </div>

            <p class="text-xs text-slate-500 dark:text-zink-300"><?= e($extra['note']) ?></p>

            <?php if ($extra['reason'] !== '') { ?>
              <p class="text-xs text-amber-700 dark:text-amber-200">Paused after repeated errors: <?= e($extra['reason']) ?></p>
            <?php } ?>

            <form method="post" action="<?= e(lurl('/admin/extras/toggle')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="module" value="<?= e($extra['key']) ?>">
              <input type="hidden" name="enabled" value="<?= $extra['on'] ? '0' : '1' ?>">
              <?php if ($extra['on']) { ?>
                {% cmp="btn" type="submit" variant="slate" icon="power" label="Switch off" %}
              <?php } else { ?>
                {% cmp="btn" type="submit" variant="blue" icon="power" label="Switch on" %}
              <?php } ?>
            </form>
          </div>
        </section>
      <?php } ?>
    </div>
  <?php } ?>
</div>
{% endblock %}
