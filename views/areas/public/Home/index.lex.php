{% extends "front.lex.php" %}

{% block title %}{{ t('meta.title') }}{% endblock %}

{% block body %}
<!-- Banner -->
<section id="banner">
    <div class="content">
        <header>
            <h1>{{ t('banner.title') }}<br /></h1>
            <p>{{ t('banner.subtitle') }}</p>
        </header>
        <p>{{ t('banner.body') }}</p>
        <ul class="actions">
            <li><a href="/login" class="button big">{{ t('banner.cta') }}</a></li>
        </ul>
    </div>
    <span class="image" aria-hidden="true">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 720 540" role="img">
        <title>{{ t('banner.svgTitle') }}</title>
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

<!-- Section -->
<section>
    <header class="major">
        <h2>Why Start Your Blog Here?</h2>
    </header>
    <div class="features">
        <article>
            <span class="icon fa fa-gem"></span>
            <div class="content">
                <h3>Designed for Creators</h3>
                <p>BlogHub is built from scratch with simplicity and flexibility in mind — no clutter, no distractions, just you and your ideas.</p>
            </div>
        </article>
        <article>
            <span class="icon solid fa fa-paper-plane"></span>
            <div class="content">
                <h3>Instant Setup</h3>
                <p>Create your blog in minutes. No technical skills required. Just pick a theme, start writing, and hit publish.</p>
            </div>
        </article>
        <article>
            <span class="icon solid fa fa-rocket"></span>
            <div class="content">
                <h3>Built to Grow With You</h3>
                <p>Whether you're writing for fun or building a brand, BlogHub gives you the tools to evolve — from posts to pages to your own domain.</p>
            </div>
        </article>
        <article>
            <span class="icon solid fa fa-signal"></span>
            <div class="content">
                <h3>Be an Early Voice</h3>
                <p>You're not joining a crowd — you're helping shape a new platform. Your feedback matters, and your blog will stand out from day one.</p>
            </div>
        </article>
    </div>
</section>

<!-- Section -->
<section>
    <header class="major">
        <h2>See What You Can Create</h2>
    </header>
    <div class="posts">
        <article>
            <a href="#" class="image"><img src="/images/pic01.jpg" alt="Person typing on a laptop with coffee beside them" /></a>
            <h3>How I Wrote My First Blog Post</h3>
            <p>A fictional creator shares their journey from blank page to published post — and how easy it was with BlogHub.</p>
            <ul class="actions">
                <li><a href="#" class="button">More</a></li>
            </ul>
        </article>
        <article>
            <a href="#" class="image"><img src="/images/pic02.jpg" alt="Screenshot of a clean blog layout with white space" /></a>
            <h3>The Power of a Clean Theme</h3>
            <p>See how a minimalist layout can make your words shine. A sample post using one of BlogHub’s starter themes.</p>
            <ul class="actions">
                <li><a href="#" class="button">More</a></li>
            </ul>
        </article>
        <article>
            <a href="#" class="image"><img src="/images/pic03.jpg" alt="Open road with mountains in the background and a journal on a dashboard" /></a>
            <h3>A Day in the Life of a Travel Blogger</h3>
            <p>A sample travel blog entry showing how storytelling and visuals come together beautifully on BlogHub.</p>
            <ul class="actions">
                <li><a href="#" class="button">More</a></li>
            </ul>
        </article>
        <article>
            <a href="#" class="image"><img src="/images/pic04.jpg" alt="Illustration of a blog setup wizard on a laptop screen" /></a>
            <h3>How to Create a Blog on BlogHub</h3>
            <p>A step-by-step guide to setting up your blog, choosing a theme, and publishing your first post.</p>
            <ul class="actions">
                <li><a href="#" class="button">More</a></li>
            </ul>
        </article>
        <article>
            <a href="#" class="image"><img src="/images/pic05.jpg" alt="Notebook with brainstorming sketches and a pen" /></a>
            <h3>What Should I Blog About?</h3>
            <p>Stuck on ideas? Here are 5 blog formats that work for any niche — from personal stories to tutorials.</p>
            <ul class="actions">
                <li><a href="#" class="button">More</a></li>
            </ul>
        </article>
        <article>
            <a href="#" class="image"><img src="/images/pic06.jpg" alt="Whiteboard with sticky notes and feature ideas" /></a>
            <h3>What’s Coming to BlogHub</h3>
            <p>A transparent look at our roadmap — and how early users can help shape the future of the platform.</p>
            <ul class="actions">
                <li><a href="#" class="button">More</a></li>
            </ul>
        </article>
    </div>
</section>
{% endblock %}