<?php
$cardUrl = lurl('/blog/'.rawurlencode((string) ($post['blog_slug'] ?? '')).'/'.rawurlencode((string) ($post['slug'] ?? '')));
$cardTitle = trim((string) ($post['title'] ?? ''));
$cardBlog = trim((string) ($post['blog_name'] ?? ''));
$cardImage = (string) ($post['featured_image'] ?? '');
// Without a picture the text is the card, so it gets room for a longer excerpt
$cardExcerpt = post_excerpt($post, $cardImage === '' ? 320 : 180);
$cardCategory = trim((string) ($post['category_name'] ?? ''));
$cardMinutes = reading_time($post['content'] ?? '');
?>
<article class="lx-post-card<?= $cardImage === '' ? ' is-textonly' : '' ?>">
    {% if cardImage %}
        <div class="lx-post-card-media" data-image-loader>
            <img src="{{ cardImage }}" alt="" loading="lazy" />
        </div>
    {% endif %}
    <div class="lx-post-card-body">
        {% if cardBlog %}
            <p class="lx-post-card-source">
                <span class="lx-post-card-mark" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($cardBlog, 0, 1))) ?></span>
                <span>{{ cardBlog }}</span>
            </p>
        {% endif %}
        <h3 class="lx-post-card-title"><a href="{{ cardUrl }}">{{ cardTitle }}</a></h3>
        {% if cardExcerpt %}
            <p class="lx-post-card-excerpt">{{ cardExcerpt }}</p>
        {% endif %}
        <p class="lx-post-card-meta">
            <span>
                {% if post.published_at %}
                    <time datetime="<?= e(iso_datetime($post['published_at'] ?? null)) ?>"><?= e(local_datetime($post['published_at'] ?? null, 'M j, Y', site_timezone())) ?></time>
                    &middot;
                {% endif %}
                <?= e($t('discover.minRead', ['n' => $cardMinutes])) ?>
            </span>
            {% if cardCategory %}
                <span class="lx-post-card-category">{{ cardCategory }}</span>
            {% endif %}
        </p>
    </div>
</article>
