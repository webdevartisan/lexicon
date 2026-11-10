{% extends "front.lex.php" %}

{% block title %}<?= e($t($tab === 'posts' ? 'discover.metaTitlePosts' : 'discover.metaTitle')) ?>{% endblock %}

{% block meta %}
<?php
$discoverTitle = $t($tab === 'posts' ? 'discover.metaTitlePosts' : 'discover.metaTitle');
$discoverDescription = $t($tab === 'posts' ? 'discover.metaDescriptionPosts' : 'discover.metaDescription');
// Search results are different for every query and have no value in an index
$discoverRobots = ($searchQuery ?? '') !== '' ? 'noindex, follow' : null;
?>
<?= platform_page_meta($discoverTitle, $discoverDescription, $head['canonicalUrl'], $discoverRobots) ?>
{% endblock %}

{% block body %}
    {% include "partials/public/discover/_header.lex.php" %}

    {% if ($tab === 'posts'): %}
    <section aria-label="{{ t('discover.tabPosts') }}">
        {% if (empty($items)): %}
            <p<?= $searchThrottled ? ' role="alert"' : '' ?>><?= e($t($searchThrottled ? 'discover.searchThrottled' : 'discover.noPosts')) ?></p>
        {% else %}
            <div class="lx-post-grid">
                {% foreach ($items as $post): %}
                    {% include "partials/public/_post_card.lex.php" %}
                {% endforeach; %}
            </div>
        {% endif %}
    </section>
    {% else %}
    <section aria-label="{{ t('discover.tabBlogs') }}">
        {% include "partials/public/discover/_featured_blogs.lex.php" %}

        {% if (empty($items)): %}
            <p>{{ t('discover.noBlogs') }}</p>
        {% else %}
            <div class="lx-blog-cards">
                {% foreach ($items as $blog): %}
                <?php $blogUrl = lurl('/blog/'.rawurlencode($blog['blog_slug'])); ?>
                <article class="lx-blog-card">
                    <h3><a href="<?= e($blogUrl) ?>">{{ blog.blog_name }}</a></h3>
                    <p class="meta">
                        {{ t('discover.byLabel') }} {{ blog.owner_name }}
                        &middot; <?= (int) $blog['post_count'] ?> {{ t('discover.postsLabel') }}
                        {% if ((int) ($blog['author_count'] ?? 0) > 1): %}
                            &middot; <?= (int) $blog['author_count'] ?> {{ t('discover.writersLabel') }}
                        {% endif %}
                    </p>
                    {% if blog.description %}
                        <p><?= e(truncate((string) $blog['description'], 180)) ?></p>
                    {% endif %}
                    {% if blog.last_post_at %}
                        <p class="meta">
                            {{ t('discover.lastPostLabel') }}:
                            <?= reader_date($blog['last_post_at'] ?? null, 'M j, Y') ?>
                        </p>
                    {% endif %}
                    <div class="lx-form-actions lx-blog-card-actions">
                        <a href="<?= e($blogUrl) ?>" class="lx-btn lx-btn-primary">{{ t('discover.visitBlog') }}</a>
                    </div>
                </article>
                {% endforeach; %}
            </div>
        {% endif %}
    </section>
    {% endif %}

    {% include "partials/public/discover/_pagination.lex.php" %}
{% endblock %}
