{% extends "back.lex.php" %}

{% block title %}{{ t('post.translations.pageTitle') }} · {{ post.title }}{% endblock %}
{% block subtitle %}{{ t('post.translations.tabsHint') }}{% endblock %}

{% block head %}
<script src="/vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script nonce="<?= csp_nonce() ?>">window.editorBlogId = <?= (int) ($post['blog_id'] ?? 0) ?>;</script>
<script src="/assets/js/initeditor.js" referrerpolicy="origin"></script>
{% endblock %}

{% block body %}
<?php
$activeLocale = $locale;
$translationsEnabled = true;
$localeNames = [
    'en' => 'English', 'fr' => 'Français', 'de' => 'Deutsch', 'el' => 'Ελληνικά', 'ar' => 'العربية',
];
$localeName = $localeNames[$locale] ?? strtoupper($locale);
$isRtlLocale = $locale === 'ar';

$titleValue = old('title') ?? $translation['title'] ?? '';
$contentValue = old('content') ?? $translation['content'] ?? '';
$excerptValue = old('excerpt') ?? $translation['excerpt'] ?? '';
$formErrors = errors();
?>
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

  {% include "partials/dashboard/post/_locale_tabs.lex.php" %}

  <form method="post" action="<?= lurl('/dashboard/post/'.(int) $post['id'].'/translations/'.e($locale)) ?>" class="space-y-3">
    {{ csrf_field() }}

    <div class="grid gap-6 lg:grid-cols-[1fr_auto] max-lg:pb-20">
      <main>
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
          <div class="p-4 border-b border-slate-200 dark:border-zink-600 flex items-center justify-between gap-3">
            <div>
              <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">
                <?= e(str_replace('{locale}', $localeName, $t('post.translations.editIn'))) ?>
              </h2>
              <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
                {{ t('post.translations.original') }}: <em><?= e($post['title'] ?? '') ?></em>
              </p>
            </div>
            <?php if (empty($translation)) { ?>
            <span class="shrink-0 inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full border bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800">
              {{ t('post.translations.notTranslated') }}
            </span>
            <?php } ?>
          </div>

          <div class="p-4 space-y-6 md:p-5" <?= $isRtlLocale ? 'dir="rtl"' : '' ?>>
            <div>
              <label for="title" class="inline-block mb-2 text-base font-medium">{{ t('post.translations.titleLabel') }}</label>
              <input
                type="text"
                id="title"
                name="title"
                value="<?= e($titleValue) ?>"
                placeholder="<?= e($post['title'] ?? '') ?>"
                required
                class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400 dark:placeholder:text-zink-200">
              <?php foreach (($formErrors['title'] ?? []) as $msg) { ?>
                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $msg ?></p>
              <?php } ?>
            </div>

            <div>
              <label for="content" class="block mb-1 text-xs font-medium tracking-wide uppercase text-slate-500 dark:text-zink-300">
                {{ t('post.translations.contentLabel') }}
              </label>
              <textarea
                id="content"
                name="content"
                rows="14"
                class="block w-full px-3 py-2 text-sm border rounded-md outline-none border-slate-300/80 text-slate-900 placeholder:text-slate-400 bg-white focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100 js-post-editor"><?= e($contentValue) ?></textarea>
              <?php foreach (($formErrors['content'] ?? []) as $msg) { ?>
                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $msg ?></p>
              <?php } ?>
            </div>

            <div>
              <label for="excerpt" class="inline-block mb-2 text-base font-medium">{{ t('post.translations.excerptLabel') }}</label>
              <textarea
                id="excerpt"
                name="excerpt"
                rows="4"
                placeholder="<?= e($post['excerpt'] ?? '') ?>"
                class="form-input border-slate-200 dark:border-zink-500 focus:outline-none focus:border-custom-500 dark:text-zink-100 dark:bg-zink-700 placeholder:text-slate-400 dark:placeholder:text-zink-200"><?= e($excerptValue) ?></textarea>
              <?php foreach (($formErrors['excerpt'] ?? []) as $msg) { ?>
                <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= $msg ?></p>
              <?php } ?>
            </div>
          </div>
        </section>
      </main>

      <aside class="lg:sticky lg:top-4 space-y-4 shrink-0 w-full lg:w-64">
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 max-lg:fixed max-lg:bottom-0 max-lg:inset-x-0 max-lg:z-40 max-lg:rounded-none max-lg:border-x-0 max-lg:border-b-0 max-lg:shadow-[0_-2px_10px_rgba(0,0,0,0.08)]">
          <div class="p-4 space-y-4 max-lg:p-3">
            <div class="flex w-full gap-2">
              <?php $saveLabel = $t('post.translations.save'); ?>
              <?php $backLabel = $t('post.translations.backToPost'); ?>
              <?php $backHref = lurl('/dashboard/post/'.(int) $post['id'].'/edit'); ?>
              {% cmp="btn" type="submit" variant="blue" icon="save" label="{$saveLabel}" addClass="flex-1 " %}
              {% cmp="btn" href="{$backHref}" variant="slate" icon="step-back" label="{$backLabel}" %}
            </div>
          </div>
        </section>

        <?php if (!empty($translation)) { ?>
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
          <div class="p-4">
            <button type="submit"
              form="deleteTranslation"
              data-confirm="<?= e($t('post.translations.deleteConfirm')) ?>"
              class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs rounded border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">
              <i data-lucide="trash-2" class="size-3.5"></i>
              {{ t('post.translations.delete') }}
            </button>
          </div>
        </section>
        <?php } ?>
      </aside>
    </div>
  </form>

  <?php if (!empty($translation)) { ?>
  <form method="post" action="<?= lurl('/dashboard/post/'.(int) $post['id'].'/translations/'.e($locale).'/delete') ?>" id="deleteTranslation">
    {{ csrf_field() }}
  </form>
  <?php } ?>
</div>
{% endblock %}
