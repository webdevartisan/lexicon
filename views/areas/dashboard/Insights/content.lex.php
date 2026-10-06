{% extends "back.lex.php" %}

{% block title %}<?= e((string) $blog['blog_name']) ?> · <?= e($t('navigation.insightsPages.content')) ?>{% endblock %}
{% block subtitle %}<?= e($t('analytics.pages.content')) ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}

  {% include "partials/dashboard/insights/_top_posts.lex.php" %}

  {% include "partials/dashboard/insights/_opportunities.lex.php" %}

  {% include "partials/dashboard/insights/_age_curve.lex.php" %}

  <?php $dimensions = ['page', 'category', 'tag']; ?>
  {% include "partials/dashboard/insights/_breakdown_list.lex.php" %}

{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
