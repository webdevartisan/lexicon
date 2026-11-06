{% extends "back.lex.php" %}

{% block title %}Extras{% endblock %}
{% block subtitle %}<span class="hidden sm:inline">Optional features you can switch on for this blog.</span>{% endblock %}
{% block body %}
<?php $extrasPath = '/dashboard/blog/'.(int) $blog['id'].'/extras'; ?>
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <?php if ($extras === []) { ?>
    {% cmp="empty-state" icon="puzzle" title="No extras are available yet." %}
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
              <?php if ($extra['on']) { ?>
                <span class="shrink-0 px-2 py-0.5 text-xs font-medium rounded-full border bg-green-100 text-green-700 border-green-200 dark:bg-green-900/40 dark:text-green-300 dark:border-green-800">On</span>
              <?php } else { ?>
                <span class="shrink-0 px-2 py-0.5 text-xs font-medium rounded-full border bg-slate-100 text-slate-600 border-slate-200 dark:bg-zink-600 dark:text-zink-200 dark:border-zink-500">Off</span>
              <?php } ?>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <form method="post" action="<?= e(lurl($extrasPath.'/toggle')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="module" value="<?= e($extra['key']) ?>">
                <input type="hidden" name="enabled" value="<?= $extra['on'] ? '0' : '1' ?>">
                <?php if ($extra['on']) { ?>
                  {% cmp="btn" type="submit" variant="slate" icon="power" label="Switch off" %}
                <?php } else { ?>
                  {% cmp="btn" type="submit" variant="blue" icon="power" label="Switch on" %}
                <?php } ?>
              </form>

              <?php if ($extra['page'] !== null) { ?>
                <?php $pagePath = $extra['page']; ?>
                {% cmp="btn" href="{$pagePath}" variant="slate" icon="arrow-right" label="Open" %}
              <?php } ?>

              <?php if ($extra['hasData']) { ?>
                <form method="post" action="<?= e(lurl($extrasPath.'/purge')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="module" value="<?= e($extra['key']) ?>">
                  <?php
                    $purgeLabel = 'Delete '.$extra['name'].' data';
                    $purgeAttrs = ['data-confirm' => 'Delete everything '.$extra['name'].' stored for this blog? This cannot be undone.'];
                  ?>
                  {% cmp="btn" type="submit" variant="red" icon="trash-2" label="{$purgeLabel}" attrs="{$purgeAttrs}" %}
                </form>
              <?php } ?>
            </div>

            <?php if (!$extra['on'] && $extra['hasData']) { ?>
              <p class="text-xs text-slate-500 dark:text-zink-300">It is off, and its data is kept in case you switch it back on.</p>
            <?php } ?>
          </div>
        </section>
      <?php } ?>
    </div>
  <?php } ?>
</div>
{% endblock %}
