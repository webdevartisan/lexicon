{% extends "back.lex.php" %}

{% block title %}{{ blog.blog_name }} · <?= e($t('archive.page.title')) ?>{% endblock %}
{% block subtitle %}<?= e($t('archive.page.subtitle')) ?>{% endblock %}
{% block body %}
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
  <?php if (!empty($importError)) { ?>
    <div class="mb-4 px-4 py-3 text-sm rounded-md border border-red-200 bg-red-50 text-red-700 dark:bg-red-900/20 dark:border-red-800 dark:text-red-300" role="alert">
      <?= e($importError) ?>
    </div>
  <?php } ?>

  <?php if (!empty($result)) { ?>
    {% include "partials/dashboard/archive/_result.lex.php" %}
  <?php } elseif (!empty($plan)) { ?>
    {% include "partials/dashboard/archive/_preview.lex.php" %}
  <?php } else { ?>
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-x-5">
      {% include "partials/dashboard/archive/_export.lex.php" %}
      {% include "partials/dashboard/archive/_import.lex.php" %}
    </div>
  <?php } ?>
</div>
{% endblock %}

{% block scripts %}
<script src="/cp-assets/js/blog-archive.js" nonce="<?= csp_nonce() ?>" defer></script>
{% endblock %}
