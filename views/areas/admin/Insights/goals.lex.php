{% extends "back.lex.php" %}

{% block title %}Goals · Insights{% endblock %}
{% block subtitle %}What readers did besides reading across every blog, how often per visit, and where those readers came from.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}

  {% include "partials/dashboard/insights/pages/_goals.lex.php" %}

<?php
$aboutParagraphs = ['Goals are counted when they happen, and kept after the views are gone. Rates per visit start on the first day visits were counted.', 'Signed up counts every new account that came after reading, on a blog or on the platform\'s own pages. The Sign-ups page has the details.'];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
