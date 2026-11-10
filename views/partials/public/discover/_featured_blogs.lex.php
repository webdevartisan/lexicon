{% if (!empty($featuredBlogs)): %}
<div class="lx-discover-featured">
    <h2 class="lx-discover-featured-title">{{ t('discover.featuredTitle') }}</h2>
    <ul class="lx-featured-blogs-list">
        {% foreach ($featuredBlogs as $featuredBlog): %}
        <?php
        $featuredBlogUrl = lurl('/blog/'.rawurlencode($featuredBlog['blog_slug']));
        $featuredBanner = trim((string) ($featuredBlog['banner_path'] ?? ''));
        if ($featuredBanner !== '' && !preg_match('#^https?://#i', $featuredBanner) && $featuredBanner[0] !== '/') {
            $featuredBanner = '/'.$featuredBanner;
        }
        ?>
        <li class="lx-featured-blog">
            <a href="<?= e($featuredBlogUrl) ?>" class="lx-featured-blog-media" data-image-loader tabindex="-1" aria-hidden="true">
                {% if featuredBanner %}
                    <img src="<?= e($featuredBanner) ?>" alt="" loading="lazy" />
                {% else %}
                    <span><?= e(mb_strtoupper(mb_substr(trim((string) $featuredBlog['blog_name']), 0, 1))) ?></span>
                {% endif %}
            </a>
            <h3><a href="<?= e($featuredBlogUrl) ?>">{{ featuredBlog.blog_name }}</a></h3>
            <p class="meta"><?= (int) ($featuredBlog['postcount'] ?? 0) ?> {{ t('discover.postsLabel') }}</p>
            {% if featuredBlog.description %}
                <p class="lx-featured-blog-desc"><?= e(truncate((string) $featuredBlog['description'], 140)) ?></p>
            {% endif %}
        </li>
        {% endforeach; %}
    </ul>
</div>
{% endif %}
