{% extends "back.lex.php" %}

{% block title %}{{ t('setup.start.title') }}{% endblock %}

{% block head %}
<link rel="stylesheet" href="<?= e(versioned_asset('/cp-assets/css/blog-start.css')) ?>">
{% endblock %}

{% block body %}
<div class="container-fluid group-data-[contentboxed]:max-w-boxed mx-auto">
  <div class="mx-auto max-w-3xl">
    {% include "partials/dashboard/_start_steps.lex.php" %}

    <form method="post" action="<?= e(lurl('/dashboard/blog/'.(int) $blog['id'].'/start/look')) ?>" class="card start-step" data-start-step>
      <?= csrf_field() ?>
      <fieldset class="card-body p-5 md:p-6">
        <legend class="float-left w-full text-xl font-semibold text-slate-900 dark:text-zink-50"><?= e($t('setup.start.look.question')) ?></legend>
        <p class="clear-left pt-1 text-sm text-slate-500 dark:text-zink-300"><?= e($t('setup.start.look.hint')) ?></p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <?php foreach ($themes as $theme) {
              $themeKey = (string) $theme['key'];
              $inputId = 'theme-'.$themeKey;
              ?>
          <label for="<?= e($inputId) ?>" class="group relative block cursor-pointer" data-theme-card="<?= e($themeKey) ?>">
            <input type="radio" name="theme" id="<?= e($inputId) ?>" value="<?= e($themeKey) ?>" class="peer sr-only"<?= $themeKey === $currentTheme ? ' checked' : '' ?>>
            <span class="block overflow-hidden rounded-lg border-2 border-slate-200 transition-colors group-hover:border-slate-300 peer-checked:!border-custom-500 peer-focus-visible:ring-4 peer-focus-visible:ring-custom-100 dark:border-zink-500 dark:group-hover:border-zink-400 dark:peer-focus-visible:ring-custom-500/20">
              <?php if (!empty($theme['screenshot'])) { ?>
              <img src="<?= e($theme['screenshot']) ?>" alt="" loading="lazy" width="640" height="400" class="block aspect-[16/10] w-full bg-slate-100 object-cover dark:bg-zink-600">
              <?php } else { ?>
              <span class="block aspect-[16/10] w-full bg-slate-100 dark:bg-zink-600"></span>
              <?php } ?>
              <span class="block p-3">
                <span class="block text-sm font-semibold text-slate-900 dark:text-zink-50" dir="auto"><?= e($theme['name']) ?></span>
                <span class="mt-0.5 block text-xs text-slate-500 dark:text-zink-300" dir="auto"><?= e($theme['description']) ?></span>
              </span>
            </span>
            <span class="absolute end-2 top-2 hidden size-6 items-center justify-center rounded-full bg-custom-500 text-white shadow peer-checked:inline-flex" aria-hidden="true">
              <svg viewBox="0 0 20 20" fill="currentColor" class="size-4"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 111.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd"/></svg>
            </span>
          </label>
          <?php } ?>
        </div>
      </fieldset>
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
