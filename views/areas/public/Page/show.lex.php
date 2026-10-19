{% extends "front.lex.php" %}

{% block title %}{{ page.title }} | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<?= platform_page_meta($page['title'], (string) ($page['meta_description'] ?? ''), $head['canonicalUrl']) ?>
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
        <a href="<?= e(lurl('/getting-started')) ?>" class="lx-btn">{{ t('pages.backToGuides') }}</a>
    </div>
    {% endif %}
</section>
{% endblock %}
