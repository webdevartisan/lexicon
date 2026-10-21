{% extends "error.lex.php" %}

{% block status %}404{% endblock %}
{% block title %}Page not found{% endblock %}
{% block tone %}neutral{% endblock %}
{% block heading %}This page moved, or never existed{% endblock %}
{% block message %}The address may have a typo, or the author took the page down. Nothing is wrong on your side.{% endblock %}

{% block scripts %}
<?php if (app(\App\Services\Analytics\AnalyticsSettings::class)->enabled()) { ?>
<script src="<?= e(versioned_asset('/assets/js/insights.js')) ?>" data-not-found defer></script>
<?php } ?>
{% endblock %}
