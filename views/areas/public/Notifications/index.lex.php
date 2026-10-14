{% extends "front.lex.php" %}

{% block title %}<?= e($t('reader.notificationsTitle')) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
// A reader surface like /saved and /replies: the shared header gives it the
// same title, and the list itself is the inbox body every skin shares.
$surface = 'notifications';
$pageSkin = 'front';
?>
<section class="lx-reader lx-notifications">
    {% include "partials/public/_reader_header.lex.php" %}
    {% include "partials/notifications/_page.lex.php" %}
</section>
{% endblock %}

{% block scripts %}
{% include "partials/_confirm_modal_js.lex.php" %}
{% endblock %}
