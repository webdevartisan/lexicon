{% extends "back.lex.php" %}

{% block title %}{{ t('blog.settings.pageTitle') }}{% endblock %}
{% block subtitle %}<span class="hidden sm:inline">{{ t('blog.settings.pageSubtitle') }}</span>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
{% endblock %}

{% block body %}
{% set nameLabel = t('blog.form.fields.name.label') %}
{% set namePlaceholder = t('blog.form.fields.name.placeholder') %}
{% set descLabel = t('blog.form.fields.description.label') %}
{% set descPlaceholder = t('blog.form.fields.description.placeholder') %}
{% set metaTitleLabel = t('blog.form.fields.metaTitle.label') %}
{% set metaTitlePlaceholder = t('blog.form.fields.metaTitle.placeholder') %}
{% set metaDescLabel = t('blog.form.fields.metaDescription.label') %}
{% set metaDescPlaceholder = t('blog.form.fields.metaDescription.placeholder') %}
{% set localeLabel = t('blog.form.fields.locale.label') %}
{% set translationsLabel = t('blog.form.fields.translationsEnabled.label') %}
{% set translationsHelp = t('blog.form.fields.translationsEnabled.help') %}
{% set commentsLabel = t('blog.form.fields.allowComments.label') %}
{% set commentsHelp = t('blog.form.fields.allowComments.help') %}
{% set instantLabel = t('blog.form.fields.instantComments.label') %}
{% set instantHelp = t('blog.form.fields.instantComments.help') %}
{% set repliesLabel = t('blog.form.fields.allowReplies.label') %}
{% set repliesHelp = t('blog.form.fields.allowReplies.help') %}
{% set workflowLabel = t('blog.form.fields.workflowEnabled.label') %}
{% set workflowHelp = t('blog.form.fields.workflowEnabled.help') %}
{% set indexingLabel = t('blog.form.fields.allowIndexing.label') %}
<?php
$translationsOn = !empty($settings['translations_enabled']);
$commentsOn = !empty($settings['comments_enabled']);
$instantOn = !empty($settings['comments_auto_publish']);
$repliesOn = !empty($settings['replies_auto_publish']);
$workflowOn = !empty($settings['workflow_enabled']);
$indexingOn = !empty($settings['indexable']);
// Only Identity starts open, but a rejected field must not hide inside a folded section
$localizationOpen = !empty($errors['locale']);
$seoOpen = !empty($errors['meta_title']) || !empty($errors['meta_description']);
?>

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

  <form
    id="blog-settings-form"
    method="post"
    action="/dashboard/blogs/{{ blog.id }}/update"
    class="max-lg:pb-20">
    <input type="hidden" name="_method" value="PUT">
    {{ csrf_field() }}

    {% include "partials/dashboard/_action_bar.lex.php" %}

    <div class="bg-white border border-slate-200 rounded-lg shadow-sm divide-y divide-slate-200 dark:bg-zink-700 dark:border-zink-600 dark:divide-zink-600 mb-6">

      <?php ob_start(); ?>
      <?php $value = $blog['blog_name'] ?? ''; ?>
      {% cmp="input" type="text" name="name" label="{$nameLabel}" value="{$value}" placeholder="{$namePlaceholder}" required="true" %}

      <?php $value = $blog['description'] ?? ''; ?>
      {% cmp="input" type="textarea" name="description" label="{$descLabel}" value="{$value}" rows="4" placeholder="{$descPlaceholder}" %}
      <?php $body = ob_get_clean(); ?>
      {% cmp="settings-section" id="general" titleKey="blog.form.sections.identity.title" descriptionKey="blog.form.sections.identity.description" body="{$body}" open="true" %}

      <?php ob_start(); ?>
      <?php $value = $current_locale ?? ''; ?>
      {% cmp="select" name="locale" options="{$locales}" label="{$localeLabel}" selectedKey="{$value}" %}

      {% cmp="setting-toggle" name="translations_enabled" label="{$translationsLabel}" help="{$translationsHelp}" checked="{$translationsOn}" %}
      <?php $body = ob_get_clean(); ?>
      {% cmp="settings-section" id="localization" titleKey="blog.form.sections.localization.title" descriptionKey="blog.form.sections.localization.description" body="{$body}" open="{$localizationOpen}" %}

      <?php ob_start(); ?>
      <div class="peer">
        {% cmp="setting-toggle" name="allow_comments" label="{$commentsLabel}" help="{$commentsHelp}" checked="{$commentsOn}" %}
      </div>

      <div class="hidden space-y-4 ltr:pl-6 rtl:pr-6 peer-has-[:checked]:block">
        {% cmp="setting-toggle" name="comments_auto_publish" label="{$instantLabel}" help="{$instantHelp}" checked="{$instantOn}" %}
        {% cmp="setting-toggle" name="replies_auto_publish" label="{$repliesLabel}" help="{$repliesHelp}" checked="{$repliesOn}" %}
      </div>
      <?php $body = ob_get_clean(); ?>
      {% cmp="settings-section" id="discussion" titleKey="blog.form.sections.discussion.title" descriptionKey="blog.form.sections.discussion.description" body="{$body}" %}

      <?php ob_start(); ?>
      {% cmp="setting-toggle" name="workflow_enabled" label="{$workflowLabel}" help="{$workflowHelp}" checked="{$workflowOn}" %}
      <?php $body = ob_get_clean(); ?>
      {% cmp="settings-section" id="workflow" titleKey="blog.form.sections.workflow.title" descriptionKey="blog.form.sections.workflow.description" body="{$body}" %}

      <?php ob_start(); ?>
      <?php $value = $settings['meta_title'] ?? ''; ?>
      {% cmp="input" type="text" name="meta_title" label="{$metaTitleLabel}" value="{$value}" placeholder="{$metaTitlePlaceholder}" %}

      <?php $value = $settings['meta_description'] ?? ''; ?>
      {% cmp="input" type="textarea" name="meta_description" label="{$metaDescLabel}" value="{$value}" rows="3" placeholder="{$metaDescPlaceholder}" %}

      {% cmp="setting-toggle" name="allow_indexing" label="{$indexingLabel}" checked="{$indexingOn}" %}
      <?php $body = ob_get_clean(); ?>
      {% cmp="settings-section" id="seo" titleKey="blog.form.sections.seo.title" descriptionKey="blog.form.sections.seo.description" body="{$body}" open="{$seoOpen}" %}
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border border-red-200 rounded-lg dark:border-red-800">
      <div class="min-w-0">
        <h2 class="text-sm font-semibold text-red-600 dark:text-red-400">{{ t('blog.dangerZone.title') }}</h2>
        <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">{{ t('blog.dangerZone.description') }}</p>
      </div>
      <button
        data-modal-target="deleteBlogModal"
        data-blog-id="<?= (int) $blog['id'] ?>"
        type="button"
        class="btn shrink-0 text-red-600 bg-white border-red-300 hover:text-red-700 hover:bg-red-50 hover:border-red-400 dark:bg-transparent dark:text-red-400 dark:border-red-700 dark:hover:bg-red-900/20">
        <i data-lucide="trash-2" class="inline-block size-4 mr-1"></i>
        {{ t('blog.dangerZone.deleteButton') }}
      </button>
    </div>
  </form>

  {% cmp="delete-blog-modal" blog="{$blog}" stats="{$stats}" %}
</div>
{% endblock %}

{% block scripts %}
<script src="<?= e(versioned_asset('/cp-assets/js/modal.js')) ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/delete-blog-modal.js')) ?>"></script>
<script nonce="<?= csp_nonce() ?>">
  // A link such as settings#discussion must unfold the section it points at
  function openLinkedSection() {
    var target = document.getElementById(location.hash.slice(1));
    if (target && target.tagName === 'DETAILS') {
      target.open = true;
      target.scrollIntoView();
    }
  }
  document.addEventListener('DOMContentLoaded', openLinkedSection);
  window.addEventListener('hashchange', openLinkedSection);
</script>
{% endblock %}
