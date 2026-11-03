{% if (!empty($featuredPosts)): %}
<div class="lx-feature-slider" aria-roledescription="carousel" aria-label="{{ t('discover.featuredPosts') }}"
     data-slider data-slider-dot-label="{{ t('discover.sliderDot') }}">
    <div class="lx-feature-rail" data-slider-rail>
        {% foreach ($featuredPosts as $featuredPost): %}
        <?php
        $featuredUrl = lurl('/blog/'.rawurlencode($featuredPost['blog_slug']).'/'.rawurlencode($featuredPost['slug']));
        $featuredBlogUrl = lurl('/blog/'.rawurlencode($featuredPost['blog_slug']));
        $featuredTitle = (string) ($featuredPost['title'] ?? '');
        $featuredExcerpt = post_excerpt($featuredPost);
        ?>
        <article class="lx-feature" data-slider-slide>
            <a href="<?= e($featuredUrl) ?>" class="lx-feature-media" data-image-loader tabindex="-1" aria-hidden="true">
                {% if featuredPost.featured_image %}
                    <img src="{{ featuredPost.featured_image }}" alt="" loading="lazy" />
                {% else %}
                    <span class="lx-feature-plate"><?= e(mb_strtoupper(mb_substr(trim($featuredTitle), 0, 1))) ?></span>
                {% endif %}
            </a>
            <div class="lx-feature-body">
                <p class="lx-discover-eyebrow">{{ t('discover.featuredPosts') }}</p>
                <h2 class="lx-feature-title"><a href="<?= e($featuredUrl) ?>"><?= e($featuredTitle) ?></a></h2>
                {% if featuredExcerpt %}
                    <p class="lx-feature-excerpt"><?= e($featuredExcerpt) ?></p>
                {% endif %}
                <p class="lx-feature-meta">
                    {{ t('discover.fromBlog') }} <a href="<?= e($featuredBlogUrl) ?>">{{ featuredPost.blog_name }}</a>
                    {% if featuredPost.published_at %}
                        <span aria-hidden="true">&middot;</span>
                        <time datetime="<?= e(iso_datetime($featuredPost['published_at'])) ?>"><?= e(local_datetime($featuredPost['published_at'], 'M j, Y', site_timezone())) ?></time>
                    {% endif %}
                </p>
                <a href="<?= e($featuredUrl) ?>" class="lx-btn lx-btn-gilt lx-feature-cta">{{ t('showcase.readPost') }}</a>
            </div>
        </article>
        {% endforeach; %}
    </div>

    {% if (count($featuredPosts) > 1): %}
        <div class="lx-feature-controls">
            <button type="button" class="lx-feature-nav" data-slider-prev aria-label="{{ t('discover.sliderPrev') }}">
                {% cmp="lx-icon" name="chevron-left" %}
            </button>
            <div class="lx-feature-dots" data-slider-dots role="group" aria-label="{{ t('discover.sliderDots') }}"></div>
            <button type="button" class="lx-feature-nav" data-slider-next aria-label="{{ t('discover.sliderNext') }}">
                {% cmp="lx-icon" name="chevron-right" %}
            </button>
        </div>
    {% endif %}
</div>
{% endif %}
