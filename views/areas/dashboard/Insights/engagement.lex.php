{% extends "back.lex.php" %}

{% block title %}<?= e((string) $blog['blog_name']) ?> · <?= e($t('navigation.insightsPages.engagement')) ?>{% endblock %}
{% block subtitle %}<?= e($t('analytics.pages.engagement')) ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}

  {% include "partials/dashboard/insights/pages/_engagement.lex.php" %}

{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
