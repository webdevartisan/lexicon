{% extends "back.lex.php" %}

{% block title %}{{ t('post.translations.pageTitle') }} · {{ post.title }}{% endblock %}

{% block head %}
<script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script nonce="<?= csp_nonce() ?>">window.editorBlogId = <?= (int) ($post['blog_id'] ?? 0) ?>;</script>
<script src="<?= e(versioned_asset('/assets/js/editor-media.js')) ?>"></script>
<script src="<?= e(versioned_asset('/assets/js/editor-colours.js')) ?>"></script>
<script src="<?= e(versioned_asset('/assets/js/initeditor.js')) ?>" referrerpolicy="origin"></script>
{% endblock %}

{% block body %}
<?php
$activeLocale = $locale;
$translationsEnabled = true;

$titleValue = old('title') ?? $translation['title'] ?? '';
$contentValue = old('content') ?? $translation['content'] ?? '';
$excerptValue = old('excerpt') ?? $translation['excerpt'] ?? '';
$formErrors = errors();
$fieldError = 'mt-1 text-sm text-red-600 dark:text-red-400';
?>
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

  <form method="post" action="<?= lurl('/dashboard/post/'.(int) $post['id'].'/translations/'.e($locale)) ?>" class="max-lg:pb-24">
    {{ csrf_field() }}

    <div class="mb-4 flex items-center gap-2 lg:sticky lg:top-header lg:z-30 lg:rounded-lg lg:border lg:border-slate-200 lg:bg-white/95 lg:px-4 lg:py-2.5 lg:shadow-sm lg:backdrop-blur lg:dark:border-zink-600 lg:dark:bg-zink-700/95">
      <a href="<?= e(lurl('/dashboard/post/'.(int) $post['id'].'/edit')) ?>"
         class="inline-flex shrink-0 items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-slate-50 max-lg:size-11 max-lg:justify-center max-lg:px-0 dark:border-zink-500 dark:text-zink-200 dark:hover:bg-zink-600"
         title="<?= e($t('post.translations.backToPost')) ?>">
        <i data-lucide="arrow-left" class="size-4" aria-hidden="true"></i>
        <span class="max-lg:sr-only">{{ t('post.translations.backToPost') }}</span>
      </a>
      <?php if (empty($translation)) { ?>
      <span class="inline-flex shrink-0 items-center rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-medium text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
        {{ t('post.translations.notTranslated') }}
      </span>
      <?php } ?>
      <div class="grow"></div>
      {% include "partials/dashboard/post/_locale_menu.lex.php" %}
      <div class="flex items-center gap-2 max-lg:fixed max-lg:inset-x-0 max-lg:bottom-0 max-lg:z-40 max-lg:border-t max-lg:border-slate-200 max-lg:bg-white/95 max-lg:px-4 max-lg:pt-2.5 max-lg:pb-[calc(0.625rem+env(safe-area-inset-bottom))] max-lg:shadow-[0_-2px_10px_rgba(0,0,0,0.08)] max-lg:backdrop-blur max-lg:dark:border-zink-600 max-lg:dark:bg-zink-700/95">
        <?php if (!empty($translation)) { ?>
        <button type="submit" form="deleteTranslation"
          data-confirm="<?= e($t('post.translations.deleteConfirm')) ?>"
          title="<?= e($t('post.translations.delete')) ?>"
          class="inline-flex size-11 shrink-0 items-center justify-center rounded-md border border-red-200 text-red-600 transition-colors hover:bg-red-50 lg:size-9 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">
          <i data-lucide="trash-2" class="size-4" aria-hidden="true"></i>
          <span class="sr-only">{{ t('post.translations.delete') }}</span>
        </button>
        <?php } ?>
        <?php $saveLabel = $t('post.translations.save'); ?>
        {% cmp="btn" type="submit" variant="blue" icon="save" label="{$saveLabel}" addClass="max-lg:h-11 max-lg:grow" %}
      </div>
    </div>

    <main data-editor class="mx-auto w-full max-w-4xl">
      <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zink-600 dark:bg-zink-700">
        <div class="space-y-3 p-4 md:p-6" <?= $isRtlLocale ? 'dir="rtl"' : '' ?>>
          <p class="text-xs text-slate-500 dark:text-zink-300" dir="auto">
            <?= e(str_replace('{locale}', $localeName, $t('post.translations.editIn'))) ?>
          </p>

          <div>
            <textarea id="title" name="title" rows="1" maxlength="100" required data-title-field
              aria-label="<?= e($t('post.translations.titleLabel')) ?>"
              placeholder="<?= e($post['title'] ?? '') ?>"
              class="block w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-2xl font-semibold leading-tight text-slate-900 placeholder:text-slate-300 focus:outline-none focus:ring-0 md:text-3xl dark:text-zink-50 dark:placeholder:text-zink-400"><?= e($titleValue) ?></textarea>
            <?php foreach (($formErrors['title'] ?? []) as $msg) { ?>
            <p class="<?= $fieldError ?>"><?= e($msg) ?></p>
            <?php } ?>
          </div>

          <div>
            <textarea id="content" name="content" rows="14"
              aria-label="<?= e($t('post.translations.contentLabel')) ?>"
              class="js-post-editor block w-full rounded-md border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 outline-none dark:border-zink-600 dark:bg-zink-800 dark:text-zink-100"><?= e($contentValue) ?></textarea>
            <?php foreach (($formErrors['content'] ?? []) as $msg) { ?>
            <p class="<?= $fieldError ?>"><?= e($msg) ?></p>
            <?php } ?>
          </div>

          <details <?= ($excerptValue !== '' || !empty($formErrors['excerpt'])) ? 'open' : '' ?>>
            <summary class="cursor-pointer text-sm font-medium text-slate-600 dark:text-zink-200">{{ t('post.translations.excerptLabel') }}</summary>
            <textarea id="excerpt" name="excerpt" rows="4" maxlength="300"
              aria-label="<?= e($t('post.translations.excerptLabel')) ?>"
              placeholder="<?= e($post['excerpt'] ?? '') ?>"
              class="form-input mt-2 border-slate-200 placeholder:text-slate-400 focus:border-custom-500 focus:outline-none dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100 dark:placeholder:text-zink-200"><?= e($excerptValue) ?></textarea>
            <?php foreach (($formErrors['excerpt'] ?? []) as $msg) { ?>
            <p class="<?= $fieldError ?>"><?= e($msg) ?></p>
            <?php } ?>
          </details>
        </div>
      </section>
    </main>
  </form>

  <?php if (!empty($translation)) { ?>
  <form method="post" action="<?= lurl('/dashboard/post/'.(int) $post['id'].'/translations/'.e($locale).'/delete') ?>" id="deleteTranslation">
    {{ csrf_field() }}
  </form>
  <?php } ?>
</div>
{% endblock %}

{% block scripts %}
<script src="<?= e(versioned_asset('/cp-assets/js/action-bar.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/title-field.js')) ?>"></script>
{% endblock %}
