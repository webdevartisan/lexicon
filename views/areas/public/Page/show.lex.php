{% extends "front.lex.php" %}

{% block title %}{{ page.title }} | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
{% if page.meta_description %}
<meta name="description" content="{{ page.meta_description }}" />
{% endif %}
{% endblock %}

{% block body %}
<section>
    <header class="main">
        <h1>{{ page.title }}</h1>
    </header>

    <div class="page-content">
        {{ page.content|raw }}
    </div>

    {% if (!empty($backToGuides)): %}
    <ul class="actions">
        <li><a href="/getting-started" class="button">{{ t('pages.backToGuides') }}</a></li>
    </ul>
    {% endif %}
</section>
{% endblock %}
