{% extends "front.lex.php" %}

{% block title %}{{ t('pages.gettingStartedTitle') }} | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="description" content="{{ t('pages.gettingStartedIntro') }}" />
{% endblock %}

{% block body %}
<section>
    <header class="main">
        <h1>{{ t('pages.gettingStartedTitle') }}</h1>
    </header>
    <p>{{ t('pages.gettingStartedIntro') }}</p>

    <?php $guideImages = ['pic07.jpg', 'pic08.jpg', 'pic09.jpg']; ?>
    <div class="posts">
        {% foreach ($guides as $i => $guide): %}
        <?php $guideUrl = '/getting-started/'.rawurlencode($guide['slug']); ?>
        <article>
            <a href="<?= e($guideUrl) ?>" class="image">
                <img src="/images/<?= e($guideImages[$i % 3]) ?>" alt="" loading="lazy" />
            </a>
            <h3><a href="<?= e($guideUrl) ?>">{{ guide.title }}</a></h3>
            {% if guide.meta_description %}
            <p>{{ guide.meta_description }}</p>
            {% endif %}
            <ul class="actions">
                <li><a href="<?= e($guideUrl) ?>" class="button">{{ t('pages.readGuide') }}</a></li>
            </ul>
        </article>
        {% endforeach; %}
    </div>
</section>
{% endblock %}
