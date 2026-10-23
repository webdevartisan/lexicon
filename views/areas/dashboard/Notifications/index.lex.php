{% extends "back.lex.php" %}

{% block title %}<?= e($t('reader.notificationsTitle')) ?>{% endblock %}
{% block subtitle %}<span class="hidden sm:inline"><?= e($t('notifications.subtitle')) ?></span>{% endblock %}

{% block body %}
<?php $pageSkin = 'cp'; ?>
<div class="container-fluid group-data-contentboxed:max-w-boxed mx-auto">
    <section class="p-5 bg-white border border-slate-200 rounded-lg shadow-sm dark:bg-zink-700 dark:border-zink-600 text-slate-700 dark:text-zink-100">
        {% include "partials/notifications/_page.lex.php" %}
    </section>
</div>
{% endblock %}
