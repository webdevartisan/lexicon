{% extends "back.lex.php" %}

{% block title %}{{ t('blog.settings.pageTitle') }}{% endblock %}
{% block subtitle %}{{ t('blog.settings.pageSubtitle') }}{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/choices.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
{% endblock %}

{% block body %}
<?php
$blogStatus = (!empty($blog['status']) ? $blog['status'] : 'draft');
$isDraft = $blogStatus === 'draft';
$isPublished = $blogStatus === 'published';
$isArchived = $blogStatus === 'archived';
?>

{% set nameLabel = t('blog.form.fields.name.label') %}
{% set namePlaceholder = t('blog.form.fields.name.placeholder') %}
{% set slugLabel = t('blog.form.fields.slug.label') %}
{% set slugUnderlabel = t('blog.form.fields.slug.underlabel') %}
{% set descLabel = t('blog.form.fields.description.label') %}
{% set descPlaceholder = t('blog.form.fields.description.placeholder') %}
{% set metaTitleLabel = t('blog.form.fields.metaTitle.label') %}
{% set metaTitlePlaceholder = t('blog.form.fields.metaTitle.placeholder') %}
{% set metaDescLabel = t('blog.form.fields.metaDescription.label') %}
{% set metaDescPlaceholder = t('blog.form.fields.metaDescription.placeholder') %}
{% set localeLabel = t('blog.form.fields.locale.label') %}
{% set timezoneLabel = t('blog.form.fields.timezone.label') %}
{% set updateBtnLabel = t('blog.form.actions.update') %}
{% set saveDraftBtnLabel = t('blog.form.actions.saveDraft') %}
{% set backBtnLabel = t('blog.form.actions.back') %}

<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">

  {% if errors|notempty %}
  <div class="mb-4">
    <div class="flex items-start gap-3 p-3 text-sm rounded-md bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/30 dark:text-red-100 dark:border-red-700">
      <div class="mt-0.5"><i class="fas fa-exclamation-circle text-sm"></i></div>
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

  <!-- Section tabs -->
  <div class="flex flex-wrap gap-1 mb-6 border-b border-slate-200 dark:border-zink-600" role="tablist" aria-label="{{ t('blog.settings.pageTitle') }}">
    <button type="button" data-settings-tab="general" role="tab" class="px-4 py-2.5 -mb-px text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-custom-500 dark:text-zink-300">
      {{ t('blog.settings.tabs.general') }}
    </button>
    <button type="button" data-settings-tab="seo" role="tab" class="px-4 py-2.5 -mb-px text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-custom-500 dark:text-zink-300">
      {{ t('blog.settings.tabs.seo') }}
    </button>
    <button type="button" data-settings-tab="discussion" role="tab" class="px-4 py-2.5 -mb-px text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-custom-500 dark:text-zink-300">
      {{ t('blog.settings.tabs.discussion') }}
    </button>
  </div>

  <form
    id="blog-settings-form"
    method="post"
    action="/dashboard/blogs/{{ blog.id }}/update">
    <input type="hidden" name="_method" value="PUT">
    <input type="hidden" name="active_section" id="active_section" value="general">
    {{ csrf_field() }}

    <div class="grid gap-6 lg:grid-cols-[1fr_auto] max-lg:pb-20">
      <main>

        <!-- ============ General ============ -->
        <div data-settings-panel="general">
          <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
            <div class="p-4 border-b border-slate-200 dark:border-zink-600">
              <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.identity.title') }}</h2>
              <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">{{ t('blog.form.sections.identity.description') }}</p>
            </div>
            <div class="p-4 space-y-4 md:p-5">
              <?php $value = $blog['blog_name'] ?? ''; ?>
              {% cmp="input" type="text" name="name" label="{$nameLabel}" value="{$value}" placeholder="{$namePlaceholder}" required="true" %}

              <?php $value = $blog['blog_slug'] ?? ''; ?>
              <?php $baseUrl = base_url().'/blog/'; ?>
              {% cmp="input" type="text" name="slug" label="{$slugLabel}" value="{$value}" prefix="{$baseUrl}" underlabel="{$slugUnderlabel}" disabled="true" %}

              <?php $value = $blog['description'] ?? ''; ?>
              {% cmp="input" type="textarea" name="description" label="{$descLabel}" value="{$value}" rows="4" placeholder="{$descPlaceholder}" %}

              <!-- Visibility -->
              <div>
                <label class="block mb-1 text-xs font-medium tracking-wide uppercase text-slate-500300">
                  {{ t('blog.form.fields.visibility.label') }}
                </label>
                <div class="grid gap-3 md:grid-cols-3">
                  <label class="flex items-start gap-2 p-3 text-xs border rounded-md cursor-pointer border-slate-200 hover:border-custom-400 hover:bg-custom-50/40 dark:border-zink-600 dark:hover:border-custom-400 dark:hover:bg-zink-800">
                    <input type="radio" name="status" value="draft" class="mt-1 text-custom-500 border-slate-300 rounded dark:border-zink-600" {% if isDraft %}checked{% endif %}>
                    <span>
                      <span class="block font-medium text-slate-900 dark:text-zink-100">{{ t('blog.form.fields.visibility.options.draft.title') }}</span>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">{{ t('blog.form.fields.visibility.options.draft.description') }}</span>
                    </span>
                  </label>
                  <label class="flex items-start gap-2 p-3 text-xs border rounded-md cursor-pointer border-slate-200 hover:border-custom-400 hover:bg-custom-50/40 dark:border-zink-600 dark:hover:border-custom-400 dark:hover:bg-zink-800">
                    <input type="radio" name="status" value="published" class="mt-1 text-custom-500 border-slate-300 rounded dark:border-zink-600" {% if isPublished %}checked{% endif %}>
                    <span>
                      <span class="block font-medium text-slate-900 dark:text-zink-100">{{ t('blog.form.fields.visibility.options.published.title') }}</span>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">{{ t('blog.form.fields.visibility.options.published.description') }}</span>
                    </span>
                  </label>
                  <label class="flex items-start gap-2 p-3 text-xs border rounded-md cursor-pointer border-slate-200 hover:border-custom-400 hover:bg-custom-50/40 dark:border-zink-600 dark:hover:border-custom-400 dark:hover:bg-zink-800">
                    <input type="radio" name="status" value="archived" class="mt-1 text-custom-500 border-slate-300 rounded dark:border-zink-600" {% if isArchived %}checked{% endif %}>
                    <span>
                      <span class="block font-medium text-slate-900 dark:text-zink-100">{{ t('blog.form.fields.visibility.options.archived.title') }}</span>
                      <span class="text-[11px] text-slate-500 dark:text-zink-300">{{ t('blog.form.fields.visibility.options.archived.description') }}</span>
                    </span>
                  </label>
                </div>
              </div>
            </div>
          </section>

          <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
            <div class="p-4 border-b border-slate-200 dark:border-zink-600">
              <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.localization.title') }}</h2>
              <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">{{ t('blog.form.sections.localization.description') }}</p>
            </div>
            <div class="p-4 space-y-4 md:p-5">
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

              <div class="pt-3 border-t border-dashed border-slate-200 dark:border-zink-600">
                <div class="flex items-start gap-2">
                  <input id="translations_enabled" name="translations_enabled" type="checkbox" value="1"
                    class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600"
                    {% if settings.translations_enabled|notempty %}checked{% endif %}>
                  <div>
                    <label for="translations_enabled" class="text-xs font-medium text-slate-800 dark:text-zink-100">
                      {{ t('blog.form.fields.translationsEnabled.label') }}
                    </label>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-zink-300">
                      {{ t('blog.form.fields.translationsEnabled.help') }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <!-- Danger Zone -->
          <div class="card border-red-200 dark:border-red-800">
            <div class="card-body bg-red-50 dark:bg-red-900/10">
              <div class="flex items-start gap-3">
                <div class="flex items-center justify-center size-10 bg-red-100 dark:bg-red-500/20 rounded-md shrink-0">
                  <i data-lucide="alert-triangle" class="size-5 text-red-500"></i>
                </div>
                <div class="grow">
                  <h6 class="mb-2 text-15 text-red-600 dark:text-red-400">{{ t('blog.dangerZone.title') }}</h6>
                  <p class="text-slate-500 dark:text-zink-300 mb-3">
                    {{ t('blog.dangerZone.description') }}
                  </p>
                  <button
                    data-modal-target="deleteBlogModal"
                    data-blog-id="<?= $blog['id'] ?>"
                    type="button"
                    class="text-white btn bg-red-500 border-red-500 hover:text-white hover:bg-red-600 hover:border-red-600 focus:text-white focus:bg-red-600 focus:border-red-600 focus:ring focus:ring-red-100 active:text-white active:bg-red-600 active:border-red-600 active:ring active:ring-red-100 dark:ring-red-400/20">
                    <i data-lucide="trash-2" class="inline-block size-4 mr-1"></i>
                    {{ t('blog.dangerZone.deleteButton') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ============ SEO ============ -->
        <div data-settings-panel="seo" class="hidden">
          <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
            <div class="p-4 border-b border-slate-200 dark:border-zink-600">
              <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.seo.title') }}</h2>
              <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">{{ t('blog.form.sections.seo.description') }}</p>
            </div>
            <div class="p-4 space-y-4 md:p-5">
              <?php $value = $settings['meta_title'] ?? ''; ?>
              {% cmp="input" type="text" name="meta_title" label="{$metaTitleLabel}" value="{$value}" placeholder="{$metaTitlePlaceholder}" %}

              <?php $value = $settings['meta_description'] ?? ''; ?>
              {% cmp="input" type="textarea" name="meta_description" label="{$metaDescLabel}" value="{$value}" rows="3" placeholder="{$metaDescPlaceholder}" %}

              <div class="flex items-center gap-2">
                <input id="allow_indexing" name="allow_indexing" type="checkbox" value="1"
                  class="w-4 h-4 border rounded text-custom-500 border-slate-300 dark:border-zink-600"
                  {% if settings.indexable|notempty %}checked{% endif %}>
                <label for="allow_indexing" class="text-xs text-slate-700 dark:text-zink-100">
                  {{ t('blog.form.fields.allowIndexing.label') }}
                </label>
              </div>
            </div>
          </section>
        </div>

        <!-- ============ Discussion & Workflow ============ -->
        <div data-settings-panel="discussion" class="hidden">
          <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
            <div class="p-4 border-b border-slate-200 dark:border-zink-600">
              <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.discussion.title') }}</h2>
              <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">{{ t('blog.form.sections.discussion.description') }}</p>
            </div>
            <div class="p-4 space-y-4 md:p-5">
              <div>
                <div class="flex items-start gap-2">
                  <input id="allow_comments" name="allow_comments" type="checkbox" value="1"
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

              <div class="pt-3 border-t border-dashed border-slate-200 dark:border-zink-600">
                <div class="flex items-start gap-2">
                  <input id="comments_auto_publish" name="comments_auto_publish" type="checkbox" value="1"
                    class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600"
                    {% if settings.comments_auto_publish|notempty %}checked{% endif %}>
                  <div>
                    <label for="comments_auto_publish" class="text-xs font-medium text-slate-800 dark:text-zink-100">
                      {{ t('blog.form.fields.instantComments.label') }}
                    </label>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-zink-300">
                      {{ t('blog.form.fields.instantComments.help') }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="pt-3 border-t border-dashed border-slate-200 dark:border-zink-600">
                <div class="flex items-start gap-2">
                  <input id="replies_auto_publish" name="replies_auto_publish" type="checkbox" value="1"
                    class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600"
                    {% if settings.replies_auto_publish|notempty %}checked{% endif %}>
                  <div>
                    <label for="replies_auto_publish" class="text-xs font-medium text-slate-800 dark:text-zink-100">
                      {{ t('blog.form.fields.allowReplies.label') }}
                    </label>
                    <p class="mt-1 text-[11px] text-slate-500 dark:text-zink-300">
                      {{ t('blog.form.fields.allowReplies.help') }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 mb-6">
            <div class="p-4 border-b border-slate-200 dark:border-zink-600">
              <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sections.workflow.title') }}</h2>
              <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">{{ t('blog.form.sections.workflow.description') }}</p>
            </div>
            <div class="p-4 md:p-5">
              <div class="flex items-start gap-2">
                <input id="workflow_enabled" name="workflow_enabled" type="checkbox" value="1"
                  class="w-4 h-4 mt-0.5 border rounded text-custom-500 border-slate-300 dark:border-zink-600"
                  {% if settings.workflow_enabled|notempty %}checked{% endif %}>
                <div>
                  <label for="workflow_enabled" class="text-xs font-medium text-slate-800 dark:text-zink-100">
                    {{ t('blog.form.fields.workflowEnabled.label') }}
                  </label>
                  <p class="mt-1 text-[11px] text-slate-500 dark:text-zink-300">
                    {{ t('blog.form.fields.workflowEnabled.help') }}
                  </p>
                </div>
              </div>
            </div>
          </section>
        </div>
      </main>

      <aside class="lg:sticky lg:top-4 space-y-4 shrink-0 w-full lg:w-64">
        <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 max-lg:fixed max-lg:bottom-0 max-lg:inset-x-0 max-lg:z-40 max-lg:rounded-none max-lg:border-x-0 max-lg:border-b-0 max-lg:shadow-[0_-2px_10px_rgba(0,0,0,0.08)]">
          <div class="p-4 space-y-4 max-lg:p-3">
            <div class="flex w-full gap-2">
              <?php $label = $isPublished ? $updateBtnLabel : $saveDraftBtnLabel ?>
              {% cmp="btn" type="submit" variant="blue" icon="save" label="{$label}" addClass="flex-1 " %}
              {% cmp="btn" href="/dashboard" variant="slate" icon="step-back" label="{$backBtnLabel}" %}
            </div>
          </div>
        </section>

        <div class="p-4 text-xs bg-slate-50 border border-dashed border-slate-200 rounded-lg dark:bg-zink-800 dark:border-zink-600 dark:text-zink-100">
          <h3 class="mb-1 text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.form.sidebar.tips.title') }}</h3>
          <p class="text-[11px] text-slate-600 dark:text-zink-300">
            {{ t('blog.form.sidebar.tips.content') }}
          </p>
        </div>
      </aside>
    </div>
  </form>

  {% cmp="deleteBlogModal" blog="{$blog}" stats="{$stats}" %}
</div>
{% endblock %}

{% block scripts %}
<script src='/cp-assets/libs/choices.js/public/assets/scripts/choices.min.js'></script>
<script src="/cp-assets/js/timezone.init.js"></script>
<script src="/cp-assets/js/choices.init.js"></script>
<script src="/cp-assets/js/modal.js"></script>
<script src="/cp-assets/js/delete-blog-modal.js"></script>
<script>
  document.addEventListener("DOMContentLoaded", function () {
    var sections = ['general', 'seo', 'discussion'];
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-settings-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-settings-panel]'));
    var activeInput = document.getElementById('active_section');
    var activeClasses = ['border-custom-500', 'text-custom-500'];

    function activate(name, updateHash) {
      if (sections.indexOf(name) === -1) name = 'general';

      tabs.forEach(function (tab) {
        var isActive = tab.getAttribute('data-settings-tab') === name;
        activeClasses.forEach(function (cls) { tab.classList.toggle(cls, isActive); });
        tab.classList.toggle('border-transparent', !isActive);
        tab.classList.toggle('text-slate-500', !isActive);
        tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });

      panels.forEach(function (panel) {
        panel.classList.toggle('hidden', panel.getAttribute('data-settings-panel') !== name);
      });

      if (activeInput) activeInput.value = name;
      if (updateHash) history.replaceState(null, '', '#' + name);
    }

    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        activate(tab.getAttribute('data-settings-tab'), true);
      });
    });

    activate((location.hash || '').replace('#', ''), false);
  });
</script>
{% endblock %}
