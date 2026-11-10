{% extends "back.lex.php" %}

{% block title %}<?= e($t(!empty($plan) ? 'archive.preview.title' : 'archive.page.title')) ?>{% endblock %}
{% block subtitle %}<span class="hidden sm:inline"><?= e($t(!empty($plan) ? 'archive.preview.subtitle' : 'archive.page.subtitle')) ?></span>{% endblock %}
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
    <div class="card max-w-3xl">
      <div class="card-body">
        {% include "partials/dashboard/archive/_export.lex.php" %}
        {% include "partials/dashboard/archive/_import.lex.php" %}
      </div>
    </div>
  <?php } ?>
</div>
{% endblock %}

{% block scripts %}
<script src="<?= e(versioned_asset('/cp-assets/js/tooltip.js')) ?>" nonce="<?= csp_nonce() ?>"></script>
<script src="<?= e(versioned_asset('/cp-assets/js/blog-archive.js')) ?>" nonce="<?= csp_nonce() ?>" defer></script>
{% endblock %}
