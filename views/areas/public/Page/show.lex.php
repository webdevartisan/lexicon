{% extends "front.lex.php" %}

{% block title %}{{ page.title }} | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
{% if page.meta_description %}
<meta name="description" content="{{ page.meta_description }}" />
{% endif %}
{% endblock %}

{% block body %}
<section aria-labelledby="page-heading">
    <header class="lx-section-head">
        <h1 id="page-heading">{{ page.title }}</h1>
    </header>

    <div class="lx-page-content">
        {{ page.content|raw }}
    </div>

    {% if (!empty($backToGuides)): %}
    <div class="lx-form-actions">
        <a href="/getting-started" class="lx-btn">{{ t('pages.backToGuides') }}</a>
    </div>
    {% endif %}
</section>
{% endblock %}
