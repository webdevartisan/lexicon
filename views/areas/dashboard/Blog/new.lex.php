{% extends "back.lex.php" %}

{% block title %}{{ t('setup.start.title') }}{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/blog-start.css?v=<?= (int) filemtime(ROOT_PATH.'/public/cp-assets/css/blog-start.css') ?>">
{% endblock %}

{% block body %}
<?php
$step = 1;
$fieldErrors = errors();
$addressBase = base_url().'/blog/';
$inputClass = 'form-input w-full border-slate-200 focus:border-custom-500 focus:outline-none dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100 dark:focus:border-custom-800';
?>
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
  <div class="mx-auto max-w-xl">
    {% include "partials/dashboard/_start_steps.lex.php" %}

    <?php if (!empty($error)) { ?>
    <p class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-700 dark:bg-red-900/30 dark:text-red-100" role="alert"><?= e($error) ?></p>
    <?php } ?>

    <form id="blog-create-form" method="post" action="<?= e(lurl('/dashboard/blog/create')) ?>" class="card start-step" data-start-step>
      <?= csrf_field() ?>
      <div class="card-body space-y-5 p-5 md:p-6">
        <div>
          <label for="name" class="block text-xl font-semibold text-slate-900 dark:text-zink-50"><?= e($t('setup.start.name.question')) ?></label>
          <input id="name" name="name" type="text" required minlength="2" maxlength="50" autocomplete="off" autofocus
            value="<?= e((string) old('name')) ?>" aria-describedby="name-hint"
            class="<?= $inputClass ?> mt-3 text-lg <?= isset($fieldErrors['name']) ? '!border-red-500' : '' ?>">
          <p id="name-hint" class="mt-2 text-sm text-slate-500 dark:text-zink-300"><?= e($t('setup.start.name.hint')) ?></p>
          <?php foreach ($fieldErrors['name'] ?? [] as $message) { ?>
          <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= e($message) ?></p>
          <?php } ?>
        </div>

        <div class="rounded-md bg-slate-50 px-3 py-2.5 dark:bg-zink-600/50">
          <p class="text-sm text-slate-600 dark:text-zink-200" data-address hidden>
            <?= e($t('setup.start.address.label')) ?>
            <span dir="ltr" class="break-all"><?= e($addressBase) ?><strong class="font-semibold text-slate-900 dark:text-zink-50" data-address-slug></strong></span>
          </p>
          <details class="mt-1" data-address-change<?= isset($fieldErrors['slug']) ? ' open' : '' ?>>
            <summary class="cursor-pointer text-sm font-medium text-custom-500 underline-offset-2 hover:underline focus:underline focus:decoration-2 focus:outline-none"><?= e($t('setup.start.address.change')) ?></summary>
            <div class="mt-2">
              <label for="slug" class="sr-only"><?= e($t('setup.start.address.field')) ?></label>
              <div class="flex" dir="ltr">
                <span class="inline-flex items-center rounded-l-md border border-r-0 border-slate-200 bg-white px-2 text-xs text-slate-500 dark:border-zink-500 dark:bg-zink-700 dark:text-zink-300"><?= e($addressBase) ?></span>
                <input id="slug" name="slug" type="text" maxlength="50" autocomplete="off" spellcheck="false"
                  value="<?= e((string) old('slug')) ?>"
                  class="<?= $inputClass ?> min-w-0 flex-1 rounded-l-none text-sm <?= isset($fieldErrors['slug']) ? '!border-red-500' : '' ?>">
              </div>
              <?php foreach ($fieldErrors['slug'] ?? [] as $message) { ?>
              <p id="slug_error" class="mt-1 text-sm text-red-600 dark:text-red-400"><?= e($message) ?></p>
              <?php } ?>
              <p class="mt-1 text-xs text-slate-500 dark:text-zink-300"><?= e($t('setup.start.address.fixed')) ?></p>
            </div>
          </details>
        </div>
      </div>
      <div class="flex justify-end border-t border-slate-200 p-4 dark:border-zink-600 md:px-6">
        <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-custom-500 bg-custom-500 px-5 py-2 text-sm font-medium text-white transition-colors hover:border-custom-600 hover:bg-custom-600 focus:ring focus:ring-custom-100 dark:focus:ring-custom-500/20 max-sm:w-full max-sm:justify-center">
          <?= e($t('setup.start.continue')) ?>
        </button>
      </div>
    </form>
  </div>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/slug-field.js?v=<?= (int) filemtime(ROOT_PATH.'/public/cp-assets/js/slug-field.js') ?>"></script>
<script src="/cp-assets/js/blog-start.js?v=<?= (int) filemtime(ROOT_PATH.'/public/cp-assets/js/blog-start.js') ?>" data-slug-check="<?= e(lurl('/dashboard/slug-check')) ?>?type=blog"></script>
{% endblock %}
