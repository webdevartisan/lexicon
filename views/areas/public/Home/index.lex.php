{% extends "front.lex.php" %}

{% block title %}<?= e(site_content('meta.title')) ?>{% endblock %}

{% block meta %}
<meta name="description" content="<?= e(site_content('meta.description')) ?>" />
<meta property="og:title" content="<?= e(site_content('meta.title')) ?>" />
<meta property="og:description" content="<?= e(site_content('meta.description')) ?>" />
<meta property="og:type" content="website" />
{% endblock %}

{% block body %}
<!-- Banner -->
<section id="banner">
    <div class="content">
        <header>
            <h1><?= e(site_content('banner.title')) ?><br /></h1>
            <p><?= e(site_content('banner.subtitle')) ?></p>
        </header>
        <p><?= e(site_content('banner.body')) ?></p>
        <ul class="actions">
            {% if (!auth()->check()): %}
                <li><a href="/register" class="button big primary"><?= e(site_content('banner.cta')) ?></a></li>
                <li><a href="/blogs" class="button big">{{ t('navigation.exploreBlogs') }}</a></li>
            {% else %}
                <li><a href="/dashboard" class="button big primary"><?= e(site_content('banner.ctaDashboard')) ?></a></li>
            {% endif %}
        </ul>
    </div>
    <span class="image" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 720 540" role="img">
        <title><?= e(site_content('banner.svgTitle')) ?></title>
        <defs>
        <linearGradient id="bg" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stop-color="#F7F7F8"/>
            <stop offset="100%" stop-color="#EFEFF2"/>
        </linearGradient>
        <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="#151515" flood-opacity="0.08"/>
        </filter>
        </defs>

        <rect width="720" height="540" rx="24" fill="url(#bg)"/>
        <!-- Editor window -->
        <g filter="url(#softShadow)" transform="translate(48,48)">
        <rect width="624" height="444" rx="16" fill="#FFFFFF"/>
        <!-- Title bar -->
        <rect x="0" y="0" width="624" height="48" rx="16" fill="#FBFBFC"/>
        <circle cx="24" cy="24" r="6" fill="#FF6B6B"/>
        <circle cx="44" cy="24" r="6" fill="#F5C451"/>
        <circle cx="64" cy="24" r="6" fill="#53D86A"/>
        <!-- Title -->
        <rect x="96" y="16" width="180" height="16" rx="8" fill="#E7E8EC"/>
        <!-- Toolbar -->
        <rect x="24" y="72" width="80" height="12" rx="6" fill="#EDEEF1"/>
        <rect x="112" y="72" width="56" height="12" rx="6" fill="#EDEEF1"/>
        <rect x="176" y="72" width="56" height="12" rx="6" fill="#EDEEF1"/>
        <!-- Body lines -->
        <rect x="24" y="108" width="456" height="12" rx="6" fill="#F0F1F4"/>
        <rect x="24" y="132" width="504" height="12" rx="6" fill="#F0F1F4"/>
        <rect x="24" y="156" width="384" height="12" rx="6" fill="#F0F1F4"/>
        <rect x="24" y="192" width="520" height="12" rx="6" fill="#F0F1F4"/>
        <rect x="24" y="216" width="428" height="12" rx="6" fill="#F0F1F4"/>
        <rect x="24" y="240" width="494" height="12" rx="6" fill="#F0F1F4"/>
        <!-- CTA pill inside the editor as a motif -->
        <rect x="24" y="288" width="148" height="36" rx="18" fill="#f56a6a"/>
        <rect x="40" y="300" width="88" height="12" rx="6" fill="#FFFFFF" opacity="0.95"/>
        </g>

        <!-- Publish sparkle motif -->
        <g transform="translate(540,120)">
        <circle cx="0" cy="0" r="26" fill="#f56a6a" opacity="0.12"/>
        <path d="M0 -18 L4 -6 L18 -6 L7 2 L11 16 L0 8 L-11 16 L-7 2 L-18 -6 L-4 -6 Z"
                fill="#f56a6a"/>
        </g>

        <!-- Cursor accent -->
        <g transform="translate(580,220)">
        <path d="M0 0 L36 20 L20 24 L28 44 L18 48 L10 28 L0 36 Z" fill="#151515"/>
        <circle cx="28" cy="12" r="6" fill="#f56a6a"/>
        </g>
    </svg>
    </span>
</section>

<!-- Stats strip -->
{% if ((int) ($stats['posts'] ?? 0) > 0): %}
<section class="stats-strip" aria-label="Platform statistics">
    <div class="stat">
        <span class="stat-number"><?= number_format((int) $stats['posts']) ?></span>
        <span class="stat-label">{{ t('stats.posts') }}</span>
    </div>
    <div class="stat">
        <span class="stat-number"><?= number_format((int) $stats['blogs']) ?></span>
        <span class="stat-label">{{ t('stats.blogs') }}</span>
    </div>
    <div class="stat">
        <span class="stat-number"><?= number_format((int) $stats['writers']) ?></span>
        <span class="stat-label">{{ t('stats.writers') }}</span>
    </div>
</section>
{% endif %}

<!-- How it works -->
<section>
    <header class="major">
        <h2><?= e(site_content('how.title')) ?></h2>
    </header>
    <div class="steps">
        <div class="step">
            <span class="step-number">1</span>
            <h3><?= e(site_content('how.step1.title')) ?></h3>
            <p><?= e(site_content('how.step1.body')) ?></p>
        </div>
        <div class="step">
            <span class="step-number">2</span>
            <h3><?= e(site_content('how.step2.title')) ?></h3>
            <p><?= e(site_content('how.step2.body')) ?></p>
        </div>
        <div class="step">
            <span class="step-number">3</span>
            <h3><?= e(site_content('how.step3.title')) ?></h3>
            <p><?= e(site_content('how.step3.body')) ?></p>
        </div>
    </div>
</section>

<!-- Features -->
<section>
    <header class="major">
        <h2><?= e(site_content('features.title')) ?></h2>
    </header>
    <div class="features">
        <article>
            <span class="icon solid fa fa-feather-alt"></span>
            <div class="content">
                <h3><?= e(site_content('features.items.writing.title')) ?></h3>
                <p><?= e(site_content('features.items.writing.body')) ?></p>
            </div>
        </article>
        <article>
            <span class="icon solid fa fa-users"></span>
            <div class="content">
                <h3><?= e(site_content('features.items.team.title')) ?></h3>
                <p><?= e(site_content('features.items.team.body')) ?></p>
            </div>
        </article>
        <article>
            <span class="icon solid fa fa-download"></span>
            <div class="content">
                <h3><?= e(site_content('features.items.ownership.title')) ?></h3>
                <p><?= e(site_content('features.items.ownership.body')) ?></p>
            </div>
        </article>
        <article>
            <span class="icon solid fa fa-book-reader"></span>
            <div class="content">
                <h3><?= e(site_content('features.items.readers.title')) ?></h3>
                <p><?= e(site_content('features.items.readers.body')) ?></p>
            </div>
        </article>
    </div>
</section>

<!-- Featured writing: admin picks only. With no picks, the guides keep the
     section presentable instead of promoting random user content. -->
{% if (!empty($showcase)): %}
<section>
    <header class="major">
        <h2><?= e(site_content('showcase.title')) ?></h2>
        <p><?= e(site_content('showcase.subtitle')) ?></p>
    </header>
    <div class="posts">
        {% foreach ($showcase as $post): %}
        <?php
            $postUrl = '/blog/'.rawurlencode($post['blog_slug']).'/'.rawurlencode($post['slug']);
$excerpt = $post['excerpt'] ?: truncate(strip_tags($post['content'] ?? ''), 160);
?>
        <article>
            {% if post.featured_image %}
            <a href="<?= e($postUrl) ?>" class="image">
                <img src="{{ post.featured_image }}" alt="{{ post.title }}" loading="lazy" />
            </a>
            {% endif %}
            <h3><a href="<?= e($postUrl) ?>">{{ post.title }}</a></h3>
            <p class="meta">
                {{ post.blog_name }}
                {% if post.published_at %}
                &middot;
                <time datetime="{{ post.published_at }}"><?= e(date('M j, Y', strtotime($post['published_at']))) ?></time>
                {% endif %}
            </p>
            <p>{{ excerpt }}</p>
            <ul class="actions">
                <li><a href="<?= e($postUrl) ?>" class="button">{{ t('showcase.readPost') }}</a></li>
            </ul>
        </article>
        {% endforeach; %}
    </div>
</section>
{% else %}
<section>
    <header class="major">
        <h2><?= e(site_content('showcase.emptyTitle')) ?></h2>
    </header>
    <div class="posts">
        <article>
            <a href="/getting-started/start-your-first-blog" class="image"><img src="/images/pic07.jpg" alt="" loading="lazy" /></a>
            <h3><a href="/getting-started/start-your-first-blog"><?= e(site_content('sidebar.gettingStarted.items.tip1')) ?></a></h3>
            <ul class="actions">
                <li><a href="/getting-started/start-your-first-blog" class="button">{{ t('pages.readGuide') }}</a></li>
            </ul>
        </article>
        <article>
            <a href="/getting-started/write-posts-people-read" class="image"><img src="/images/pic08.jpg" alt="" loading="lazy" /></a>
            <h3><a href="/getting-started/write-posts-people-read"><?= e(site_content('sidebar.gettingStarted.items.tip2')) ?></a></h3>
            <ul class="actions">
                <li><a href="/getting-started/write-posts-people-read" class="button">{{ t('pages.readGuide') }}</a></li>
            </ul>
        </article>
        <article>
            <a href="/getting-started/blog-with-your-team" class="image"><img src="/images/pic09.jpg" alt="" loading="lazy" /></a>
            <h3><a href="/getting-started/blog-with-your-team"><?= e(site_content('sidebar.gettingStarted.items.tip3')) ?></a></h3>
            <ul class="actions">
                <li><a href="/getting-started/blog-with-your-team" class="button">{{ t('pages.readGuide') }}</a></li>
            </ul>
        </article>
    </div>
</section>
{% endif %}

<!-- FAQ -->
<section id="faq">
    <header class="major">
        <h2><?= e(site_content('faq.title')) ?></h2>
    </header>
    <?php $faqKeys = ['q1', 'q2', 'q3', 'q4', 'q5', 'q6']; ?>
    <div class="faq-list">
        {% foreach ($faqKeys as $fk): %}
        <details class="faq-item">
            <summary><?= e(site_content('faq.items.'.$fk.'.q')) ?></summary>
            <p><?= e(site_content('faq.items.'.$fk.'.a')) ?></p>
        </details>
        {% endforeach; %}
    </div>
</section>
{% endblock %}
