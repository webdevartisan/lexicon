{% extends "back.lex.php" %}

{% block title %}Acquisition · Insights{% endblock %}
{% block subtitle %}<?= e($scope === 'site' ? 'How readers found the site: channels, sites, campaigns, and the pages they arrived at and left from.' : 'How readers found the platform\'s own pages, and where they arrived and left.') ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}

  {% include "partials/dashboard/insights/pages/_acquisition.lex.php" %}

<?php
$aboutParagraphs = ['Each view keeps how its reader arrived. Landing pages are where readers started a visit from outside; exit pages are where they last were.', 'Days are UTC.'];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
