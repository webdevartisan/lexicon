{% extends "back.lex.php" %}

{% block title %}Engagement · Insights{% endblock %}
{% block subtitle %}<?= e($scope === 'site' ? 'How much of a visit readers spent reading across the site, and how far they got.' : 'How readers of the platform\'s own pages read them, and how far they got.') ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}

  {% include "partials/dashboard/insights/pages/_engagement.lex.php" %}

<?php
$aboutParagraphs = ['A visit is one reader with less than 30 minutes between pages, wherever on the site they were. It is engaged with two or more pages, 10 or more seconds of reading, or a like, comment, save, share or sign-up.', 'Days are UTC.'];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
