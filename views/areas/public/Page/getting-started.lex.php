{% extends "front.lex.php" %}

{% block title %}{{ t('pages.gettingStartedTitle') }} | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<?= platform_page_meta($t('pages.gettingStartedTitle'), $t('pages.gettingStartedIntro'), $head['canonicalUrl']) ?>
{% endblock %}

{% block body %}
<section aria-labelledby="guides-heading">
    <header class="lx-section-head">
        <h1 id="guides-heading">{{ t('pages.gettingStartedTitle') }}</h1>
        <p>{{ t('pages.gettingStartedIntro') }}</p>
    </header>

    <div class="lx-gallery">
        {% foreach ($guides as $guide): %}
        <?php
        $guideUrl = '/getting-started/'.rawurlencode($guide['slug']);
        $guideThumb = $guide['thumbnail_path'] ?? '';
        ?>
        <article class="lx-gallery-card">
            <?php if ($guideThumb !== '' && $guideThumb !== null) { ?>
            <a href="<?= e($guideUrl) ?>" class="lx-gallery-media" tabindex="-1" aria-hidden="true">
                <img src="<?= e($guideThumb) ?>" alt="" loading="lazy" />
            </a>
            <?php } ?>
            <div class="lx-gallery-body">
                <h3><a href="<?= e($guideUrl) ?>">{{ guide.title }}</a></h3>
                {% if guide.meta_description %}
                <p class="lx-gallery-excerpt">{{ guide.meta_description }}</p>
                {% endif %}
            </div>
        </article>
        {% endforeach; %}
    </div>
</section>
{% endblock %}
