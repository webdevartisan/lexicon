{% extends "back.lex.php" %}

{% block title %}{{ t('blog.create.pageTitle') }}{% endblock %}
{% block subtitle %}{{ t('blog.create.pageSubtitle') }}{% endblock %}

{% block body %}
{% set nameLabel = t('blog.form.fields.name.label') %}
{% set namePlaceholder = t('blog.form.fields.name.placeholder') %}
{% set slugLabel = t('blog.form.fields.slug.label') %}
{% set slugUnderlabel = t('blog.form.fields.slug.underlabel') %}
{% set descLabel = t('blog.form.fields.description.label') %}
{% set descPlaceholder = t('blog.form.fields.description.placeholder') %}
{% set createBtnLabel = t('blog.form.actions.create') %}
{% set backBtnLabel = t('blog.form.actions.back') %}

<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <div class="max-w-2xl mx-auto">

    {% if error|notempty %}
    <div class="mb-4">
      <div class="flex items-start gap-3 p-3 text-sm rounded-md bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/30 dark:text-red-100 dark:border-red-700">
        <div class="mt-0.5"><i class="fas fa-exclamation-circle text-sm"></i></div>
        <p class="font-semibold">{{ error }}</p>
      </div>
    </div>
    {% endif %}

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

    <form id="blog-create-form" method="post" action="/dashboard/blog/create">
      {{ csrf_field() }}

      <section class="bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600">
        <div class="p-4 border-b border-slate-200 dark:border-zink-600">
          <h2 class="text-sm font-semibold text-slate-900 dark:text-zink-100">{{ t('blog.create.essentials.title') }}</h2>
          <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
            {{ t('blog.create.essentials.description') }}
          </p>
        </div>

        <div class="p-4 space-y-4 md:p-5">
          {% cmp="input" type="text" name="name" label="{$nameLabel}" placeholder="{$namePlaceholder}" required="true" %}

          <?php $baseUrl = base_url().'/blog/'; ?>
          {% cmp="input" type="text" name="slug" label="{$slugLabel}" prefix="{$baseUrl}" underlabel="{$slugUnderlabel}" required="true" %}

          {% cmp="input" type="textarea" name="description" label="{$descLabel}" rows="4" placeholder="{$descPlaceholder}" %}
        </div>

        <div class="flex items-center justify-between gap-3 p-4 border-t border-slate-200 dark:border-zink-600 md:p-5">
          <p class="text-[11px] text-slate-500 dark:text-zink-300">
            {{ t('blog.create.afterCreateHint') }}
          </p>
          <div class="flex gap-2 shrink-0">
            {% cmp="btn" href="/dashboard/blog" variant="slate" icon="step-back" label="{$backBtnLabel}" %}
            {% cmp="btn" type="submit" variant="blue" icon="plus" label="{$createBtnLabel}" %}
          </div>
        </div>
      </section>
    </form>
  </div>
</div>
{% endblock %}

{% block scripts %}
<script nonce="<?= csp_nonce() ?>">
  document.addEventListener("DOMContentLoaded", function () {
    const NameInput = document.getElementById('name');
    const SlugInput = document.getElementById('slug');

    if (NameInput && SlugInput) {
      NameInput.addEventListener('input', function () {
        // generate a URL-friendly slug from the blog name
        let slug = NameInput.value
          .toLowerCase()               // Convert to lowercase
          .trim()                      // Remove leading/trailing spaces
          .replace(/[^\w\s-]/g, '')    // Remove non-word characters except spaces/dashes
          .replace(/\s+/g, '-')        // Replace spaces with dashes
          .replace(/-+/g, '-');        // Collapse multiple dashes
        SlugInput.value = slug;
      });
    }
  });
</script>
{% endblock %}
