{% extends "back.lex.php" %}

{% block title %}{{ t('setup.start.title') }}{% endblock %}

{% block head %}
<link rel="stylesheet" href="<?= e(versioned_asset('/cp-assets/css/blog-start.css')) ?>">
{% endblock %}

{% block body %}
<?php
$fieldErrors = errors();
$description = (string) (old('description') ?: $blog['description'] ?? '');
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
  <div class="mx-auto max-w-xl">
    {% include "partials/dashboard/_start_steps.lex.php" %}

    <form method="post" action="<?= e(lurl('/dashboard/blog/'.(int) $blog['id'].'/start/about')) ?>" class="card start-step" data-start-step>
      <?= csrf_field() ?>
      <div class="card-body p-5 md:p-6">
        <label for="description" class="block text-xl font-semibold text-slate-900 dark:text-zink-50"><?= e($t('setup.start.about.question')) ?></label>
        <input id="description" name="description" type="text" maxlength="160" autocomplete="off" autofocus
          value="<?= e($description) ?>" aria-describedby="description-hint"
          class="form-input mt-3 w-full border-slate-200 text-lg focus:border-custom-500 focus:outline-none dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100 dark:focus:border-custom-800 <?= isset($fieldErrors['description']) ? '!border-red-500' : '' ?>">
        <p id="description-hint" class="mt-2 text-sm text-slate-500 dark:text-zink-300"><?= e($t('setup.start.about.hint')) ?></p>
        <?php foreach ($fieldErrors['description'] ?? [] as $message) { ?>
        <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= e($message) ?></p>
        <?php } ?>
      </div>
      <div class="flex items-center justify-end gap-2 border-t border-slate-200 p-4 dark:border-zink-600 md:px-6">
        <button type="submit" name="intent" value="skip" formnovalidate class="rounded-md px-4 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-100 focus:outline-none focus:ring focus:ring-slate-100 dark:text-zink-200 dark:hover:bg-zink-600 max-sm:flex-1">
          <?= e($t('setup.start.skip')) ?>
        </button>
        <button type="submit" name="intent" value="save" class="inline-flex items-center justify-center rounded-md border border-custom-500 bg-custom-500 px-5 py-2 text-sm font-medium text-white transition-colors hover:border-custom-600 hover:bg-custom-600 focus:ring focus:ring-custom-100 dark:focus:ring-custom-500/20 max-sm:flex-1">
          <?= e($t('setup.start.continue')) ?>
        </button>
      </div>
    </form>
  </div>
</div>
{% endblock %}
