<?php
$discoverBlogsUrl = lurl('/discover?'.http_build_query(array_filter(['tab' => 'blogs', 'q' => $searchQuery])));
$discoverPostsUrl = lurl('/discover?'.http_build_query(array_filter(['tab' => 'posts', 'q' => $searchQuery])));
$discoverSearchAction = lurl('/discover');
$discoverClearUrl = lurl('/discover?'.http_build_query(['tab' => $tab]));
?>

{% if (!empty($featuredCreators)): %}
    <section class="lx-bleed lx-slider" aria-label="{{ t('discover.featuredTitle') }}" data-slider>
        <div class="lx-slider-rail" data-slider-rail>
            {% foreach ($featuredCreators as $creatorBlog): %}
            <?php
            $creatorUrl = lurl('/blog/'.rawurlencode($creatorBlog['blog_slug']));
            $creatorInitial = mb_strtoupper(mb_substr(trim((string) ($creatorBlog['blog_name'] ?? '?')), 0, 1));
            $creatorPosts = (int) ($creatorBlog['postcount'] ?? 0);
            $creatorWriters = (int) ($creatorBlog['authorcount'] ?? 1);
            $creatorBanner = trim((string) ($creatorBlog['banner_path'] ?? ''));
            if ($creatorBanner !== '' && !preg_match('#^https?://#i', $creatorBanner) && $creatorBanner[0] !== '/') {
                $creatorBanner = '/'.$creatorBanner;
            }
            ?>
            <article class="lx-slide" data-slider-slide>
                <div class="lx-slide-card">
                    <a href="<?= e($creatorUrl) ?>" class="lx-slide-media" tabindex="-1" aria-hidden="true">
                        {% if creatorBanner %}
                            <img src="<?= e($creatorBanner) ?>" alt="" loading="lazy" />
                        {% else %}
                            <span class="lx-slide-media-fallback" aria-hidden="true"><?= e($creatorInitial) ?></span>
                        {% endif %}
                    </a>
                    <div class="lx-slide-body">
                        <p class="lx-slide-eyebrow">{{ t('discover.featuredTitle') }}</p>
                        <h2 class="lx-slide-title"><a href="<?= e($creatorUrl) ?>">{{ creatorBlog.blog_name }}</a></h2>
                        <p class="lx-slide-meta">
                            <?= $creatorPosts ?> {{ t('discover.postsLabel') }}
                            {% if ($creatorWriters > 1): %}
                                &middot; <?= $creatorWriters ?> {{ t('discover.writersLabel') }}
                            {% endif %}
                        </p>
                        {% if creatorBlog.description %}
                            <p class="lx-slide-desc"><?= e(truncate((string) $creatorBlog['description'], 220)) ?></p>
                        {% endif %}
                        <a href="<?= e($creatorUrl) ?>" class="lx-btn lx-btn-primary lx-slide-cta">{{ t('discover.visitBlog') }}</a>
                    </div>
                </div>
            </article>
            {% endforeach; %}
        </div>
        <button type="button" class="lx-slider-nav lx-slider-prev" data-slider-prev aria-label="{{ t('discover.sliderPrev') }}">
            <span class="fa-solid fa-chevron-left" aria-hidden="true"></span>
        </button>
        <button type="button" class="lx-slider-nav lx-slider-next" data-slider-next aria-label="{{ t('discover.sliderNext') }}">
            <span class="fa-solid fa-chevron-right" aria-hidden="true"></span>
        </button>
        <div class="lx-slider-dots" data-slider-dots aria-label="{{ t('discover.sliderDots') }}"></div>
    </section>
{% endif %}

<header class="lx-section-head lx-explore-head">
    <h1 id="discover-heading">{{ t('discover.heroTitle') }}</h1>
</header>

<div class="lx-explore-toolbar" aria-labelledby="discover-heading">
    <div class="lx-explore-tabs" role="tablist">
        <a href="<?= e($discoverBlogsUrl) ?>" role="tab"
           class="lx-explore-tab <?= $tab === 'blogs' ? 'active' : '' ?>"
           aria-selected="<?= $tab === 'blogs' ? 'true' : 'false' ?>">{{ t('discover.tabBlogs') }}</a>
        <a href="<?= e($discoverPostsUrl) ?>" role="tab"
           class="lx-explore-tab <?= $tab === 'posts' ? 'active' : '' ?>"
           aria-selected="<?= $tab === 'posts' ? 'true' : 'false' ?>">{{ t('discover.tabPosts') }}</a>
    </div>

    <form method="get" action="<?= e($discoverSearchAction) ?>" class="lx-explore-search" role="search" aria-label="{{ t('discover.searchButton') }}">
        <input type="hidden" name="tab" value="{{ tab }}" />
        <label class="lx-visually-hidden" for="discover-q">{{ t('discover.searchButton') }}</label>
        <div class="lx-search-field<?= $searchQuery !== '' ? ' has-value' : ''; ?>">
            <input
                type="search"
                name="q"
                id="discover-q"
                value="{{ searchQuery }}"
                placeholder="<?= e($tab === 'posts' ? $t('discover.searchPlaceholderPosts') : $t('discover.searchPlaceholderBlogs')) ?>"
            />
            {% if ($searchQuery !== ''): %}
                <a href="<?= e($discoverClearUrl) ?>"
                   class="lx-search-field-clear"
                   aria-label="{{ t('discover.searchClear') }}">
                    <span class="fa-solid fa-xmark" aria-hidden="true"></span>
                </a>
            {% endif %}
            <button type="submit" class="lx-search-field-submit" aria-label="{{ t('discover.searchButton') }}">
                <span class="fa-solid fa-magnifying-glass" aria-hidden="true"></span>
            </button>
        </div>
    </form>
</div>

{% if (!empty($searchQuery)): %}
    <p class="lx-search-meta" role="status">
        <strong><?= (int) ($pagination['total'] ?? 0) ?></strong>
        {{ t('discover.resultsFor') }}
        <strong>"{{ searchQuery }}"</strong>
    </p>
{% endif %}
