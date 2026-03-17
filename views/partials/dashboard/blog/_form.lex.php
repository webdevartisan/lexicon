<?php
$blogStatus = (!empty($blog['status']) ? $blog['status'] : 'draft');
$isDraft = $blogStatus === 'draft';
$isPublished = $blogStatus === 'published';
$isArchived = $blogStatus === 'archived';
?>

{% set nameLabel = t('blog.form.fields.name.label') %}
{% set slugLabel = t('blog.form.fields.slug.label') %}
{% set descLabel = t('blog.form.fields.description.label') %}
{% set metaTitleLabel = t('blog.form.fields.metaTitle.label') %}
{% set metaDescLabel = t('blog.form.fields.metaDescription.label') %}
{% set themeLabel = t('blog.form.fields.theme.label') %}
{% set localeLabel = t('blog.form.fields.locale.label') %}
{% set timezoneLabel = t('blog.form.fields.timezone.label') %}
{% set namePlaceholder = t('blog.form.fields.name.placeholder') %}
{% set slugUnderlabel = t('blog.form.fields.slug.underlabel') %}
{% set descPlaceholder = t('blog.form.fields.description.placeholder') %}
{% set metaTitlePlaceholder = t('blog.form.fields.metaTitle.placeholder') %}
{% set metaDescPlaceholder = t('blog.form.fields.metaDescription.placeholder') %}
{% set updateBtnLabel = t('blog.form.actions.update') %}
{% set saveDraftBtnLabel = t('blog.form.actions.saveDraft') %}
{% set backBtnLabel = t('blog.form.actions.back') %}
{% set bannerLabel = t('blog.form.fields.banner.label') %}
{% set logoLabel = t('blog.form.fields.logo.label') %}
{% set faviconLabel = t('blog.form.fields.favicon.label') %}

<!-- Errors -->
{% if errors|notempty %}
<div class="mb-4">
  <div class="flex items-start gap-3 p-3 text-sm rounded-md bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/30 dark:text-red-100 dark:border-red-700">
    <div class="mt-0.5">
      <i class="fas fa-exclamation-circle text-sm"></i>
    </div>
    <div>
      <p class="font-semibold">{{ t('blog.form.errors.title') }}</p>
      <ul class="mt-2 space-y-1 list-disc list-inside">
        {% foreach ($errors as $field => $msgs): %}
          {% foreach ($msgs as $msg): %}
            <li>{{ msg }}</li>
          {% endforeach %}
        {% endforeach %}
      </ul>
    </div>
  </div>
</div>
{% endif %}

<input type="hidden" name="author_id" value="<?= e($currentUser['id'] ?? $post['author_id'] ?? '') ?>">
{{ csrf_field() }}

<!-- Form card -->
<div class="grid gap-6 lg:grid-cols-[1fr_auto]">
  <main>
      <!-- Identity -->
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.identity.title') }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            {{ t('blog.form.sections.identity.description') }}
          </p>
        </div>
        <div class="p-4 space-y-4 md:p-5">
          <!-- Name -->
          <?php $value = $blog['blog_name'] ?? ''; ?>
          {% cmp="input" type="text" name="name" label="{$nameLabel}" value="{$value}" placeholder="{$namePlaceholder}" required="true" %}

          <!-- Slug -->
          {% if ($isDraft): %}
          <?php $value = $blog['blog_slug'] ?? ''; ?>
          <?php $baseUrl = base_url().'/blog/' ?? '/'; ?>
          {% cmp="input" type="text" name="slug" label="{$slugLabel}" value="{$value}" prefix="{$baseUrl}" underlabel="{$slugUnderlabel}" %}
          {% endif %}

          <!-- Description -->
          <?php $value = $blog['description'] ?? ''; ?>
          {% cmp="input" type="textarea" name="description" label="{$descLabel}" value="{$value}" rows="4" placeholder="{$descPlaceholder}" %}

          <!-- Visibility -->
          <div>
            <label class="block mb-1 text-xs font-medium tracking-wide uppercase text-slate-500300">
              {{ t('blog.form.fields.visibility.label') }}
            </label>
            <div class="grid gap-3 md:grid-cols-3">
              <label class="flex items-start gap-2 p-3 text-xs border rounded-md cursor-pointer border-slate-200 hover:border-custom-400 hover:bg-custom-50/40 dark:border-zink-600 dark:hover:border-custom-400 dark:hover:bg-zink-800">
                <input
                  type="radio"
                  name="status"
                  value="draft"
                  class="mt-1 text-custom-500 border-slate-300 rounded dark:border-zink-600"
                  {% if isDraft %}
                  checked
                  {% endif %}>
                <span>
                  <span class="block font-medium text-slate-900 dark:text-zink-100">{{ t('blog.form.fields.visibility.options.draft.title') }}</span>
                  <span class="text-[11px] text-slate-500 dark:text-zink-300">{{ t('blog.form.fields.visibility.options.draft.description') }}</span>
                </span>
              </label>

              <label class="flex items-start gap-2 p-3 text-xs border rounded-md cursor-pointer border-slate-200 hover:border-custom-400 hover:bg-custom-50/40 dark:border-zink-600 dark:hover:border-custom-400 dark:hover:bg-zink-800">
                <input
                  type="radio"
                  name="status"
                  value="published"
                  class="mt-1 text-custom-500 border-slate-300 rounded dark:border-zink-600"
                  {% if isPublished %}
                  checked
                  {% endif %}>
                <span>
                  <span class="block font-medium text-slate-900 dark:text-zink-100">{{ t('blog.form.fields.visibility.options.published.title') }}</span>
                  <span class="text-[11px] text-slate-500 dark:text-zink-300">{{ t('blog.form.fields.visibility.options.published.description') }}</span>
                </span>
              </label>

              <label class="flex items-start gap-2 p-3 text-xs border rounded-md cursor-pointer border-slate-200 hover:border-custom-400 hover:bg-custom-50/40 dark:border-zink-600 dark:hover:border-custom-400 dark:hover:bg-zink-800">
                <input
                  type="radio"
                  name="status"
                  value="archived"
                  class="mt-1 text-custom-500 border-slate-300 rounded dark:border-zink-600"
                  {% if isArchived %}
                  checked
                  {% endif %}>
                <span>
                  <span class="block font-medium text-slate-900 dark:text-zink-100">{{ t('blog.form.fields.visibility.options.archived.title') }}</span>
                  <span class="text-[11px] text-slate-500 dark:text-zink-300">{{ t('blog.form.fields.visibility.options.archived.description') }}</span>
                </span>
              </label>
            </div>
          </div>
        </div>
      </section>

      <!-- Theme & Branding -->
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.theme.title') }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            {{ t('blog.form.sections.theme.description') }}
          </p>
        </div>

        <div class="p-4 md:p-5">
          <?php $value = $settings['theme'] ?? ''; ?>
          {% cmp="select" name="theme" options="{$themes}" label="{$themeLabel}" selectedKey="{$value}" %}
        </div>
      </section>

      <!-- Localization -->
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.localization.title') }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            {{ t('blog.form.sections.localization.description') }}
          </p>
        </div>
        <div class="p-4 md:p-5">
          <div class="grid gap-4 md:grid-cols-2">
            <div>
              <?php $value = $current_locale ?? ''; ?>
              {% cmp="select" name="locale" options="{$locales}" label="{$localeLabel}" selectedKey="{$value}" %}
            </div>

            <div>
              <?php $value = $settings['timezone'] ?? ''; ?>
              {% cmp="select" name="timezone" groups="{$timezones}" label="{$timezoneLabel}" selectedKey="{$value}" %}
            </div>
          </div>
        </div>
      </section>

      <!-- SEO -->
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.seo.title') }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            {{ t('blog.form.sections.seo.description') }}
          </p>
        </div>
        <div class="p-4 space-y-4 md:p-5">
          <!-- Meta title -->
          {% cmp="input" type="text" name="meta_title" label="{$metaTitleLabel}" placeholder="{$metaTitlePlaceholder}" %}

          <!-- Meta description -->
          {% cmp="input" type="textarea" name="meta_description" label="{$metaDescLabel}" rows="3" placeholder="{$metaDescPlaceholder}" %}

          <div class="flex items-center gap-2">
            <input
              id="allow_indexing"
              name="allow_indexing"
              type="checkbox"
              value="1"
              class="w-4 h-4 border rounded text-custom-500 border-slate-300 dark:border-zink-600"
              {% if settings.indexable|notempty %}checked{% endif %}>
            <label for="allow_indexing" class="text-xs text-slate-700 dark:text-zink-100">
              {{ t('blog.form.fields.allowIndexing.label') }}
            </label>
          </div>

          <div class="pt-3 border-t border-dashed border-slate-200 dark:border-zink-600">
            <div class="flex items-start gap-2">
              <input
                id="allow_comments"
                name="allow_comments"
                type="checkbox"
                value="1"
                class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600"
                {% if settings.comments_enabled|notempty %}checked{% endif %}>
              <div>
                <label for="allow_comments" class="text-xs font-medium text-slate-800 dark:text-zink-100">
                  {{ t('blog.form.fields.allowComments.label') }}
                </label>
                <p class="mt-1 text-[11px] text-slate-500 dark:text-zink-300">
                  {{ t('blog.form.fields.allowComments.help') }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>
  </main>
  
  <aside class="sticky top-4 space-y-4 shrink-0 w-full sm:w-64">
      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
          <div class="p-4 space-y-4">
              <!-- Primary Action Buttons -->
              <div class="flex w-full gap-2">
              <?php $label = $isPublished ? $updateBtnLabel : $saveDraftBtnLabel ?>
              {% cmp="btn" type="submit" variant="blue" icon="save" label="{$label}" addClass="flex-1 " %}
              {% cmp="btn" href="/dashboard" variant="slate" icon="step-back" label="{$backBtnLabel}" %}
              </div>
          </div>
      </section>

      {% cmp="dropzone2" label="{$bannerLabel}" name="banner" resource="{$settings}" %}
      {% cmp="dropzone2" label="{$logoLabel}" name="logo" resource="{$settings}" %}
      {% cmp="dropzone2" label="{$faviconLabel}" name="favicon" resource="{$settings}" %}

      <div class="p-4 text-xs bg-slate-50 border border-dashed border-slate-200 rounded-lg dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100">
          <h3 class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sidebar.tips.title') }}</h3>
          <p class="text-[11px] text-slate-600 dark:text-zink-300">
              {{ t('blog.form.sidebar.tips.content') }}
          </p>
      </div>
  </aside>
</div>
