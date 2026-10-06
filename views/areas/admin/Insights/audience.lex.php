{% extends "back.lex.php" %}

{% block title %}Audience · Insights{% endblock %}
{% block subtitle %}<?= e($scope === 'site' ? 'Who reads across the site: where they are, their language and device, and when they read.' : 'Who opens the platform\'s own pages: where they are, their language and device, and when.') ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}

  {% include "partials/dashboard/insights/pages/_audience.lex.php" %}

<?php
$aboutParagraphs = ['Countries come from the reader\'s network address, looked up and then forgotten. Languages are the language of the page read.', 'Days and hours are UTC.'];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
