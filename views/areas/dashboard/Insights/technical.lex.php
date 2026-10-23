{% extends "back.lex.php" %}

{% block title %}<?= e($t('navigation.insightsPages.technical')) ?>{% endblock %}
{% block subtitle %}<span class="hidden sm:inline"><?= e($t('analytics.pages.technical')) ?></span>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/modal.css">
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}
<?php $missingHint = $t('analytics.missing.hint'); ?>

  {% include "partials/dashboard/insights/_speed.lex.php" %}

  {% include "partials/dashboard/insights/_missing.lex.php" %}

{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
