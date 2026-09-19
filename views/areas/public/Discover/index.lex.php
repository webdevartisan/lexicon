{% extends "front.lex.php" %}

{% block title %}<?= e($t($tab === 'posts' ? 'discover.metaTitlePosts' : 'discover.metaTitle')) ?>{% endblock %}

{% block body %}
    {% include "partials/public/discover/_header.lex.php" %}

    {% if ($tab === 'posts'): %}
    <section aria-label="{{ t('discover.tabPosts') }}">
        {% if (empty($items)): %}
            <p>{{ t('discover.noPosts') }}</p>
        {% else %}
            <div class="lx-gallery">
                {% foreach ($items as $post): %}
                <?php
                $postUrl = lurl('/blog/'.rawurlencode($post['blog_slug']).'/'.rawurlencode($post['slug']));
                $excerpt = post_excerpt($post);
                $hasImage = !empty($post['featured_image']);
                ?>
                <article class="lx-gallery-card<?= $hasImage ? '' : ' is-textonly'; ?>">
                    <a href="<?= e($postUrl) ?>" class="lx-gallery-media" tabindex="-1" aria-hidden="true">
                        {% if post.featured_image %}
                            <img src="{{ post.featured_image }}" alt="" loading="lazy" />
                        {% else %}
                            <span class="lx-gallery-fallback" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(trim((string) $post['title']), 0, 1))); ?></span>
                        {% endif %}
                    </a>
                    <div class="lx-gallery-body">
                        <p class="meta">
                            {{ post.blog_name }}
                            {% if post.published_at %}
                                &middot; <time datetime="<?= e(iso_datetime($post['published_at'] ?? null)) ?>"><?= e(local_datetime($post['published_at'] ?? null, 'M j, Y', site_timezone())) ?></time>
                            {% endif %}
                        </p>
                        <h3><a href="<?= e($postUrl) ?>">{{ post.title }}</a></h3>
                        {% if excerpt %}
                            <p class="lx-gallery-excerpt"><?= e($excerpt) ?></p>
                        {% endif %}
                    </div>
                </article>
                {% endforeach; %}
            </div>
        {% endif %}
    </section>
    {% else %}
    <section aria-label="{{ t('discover.tabBlogs') }}">
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
                            <time datetime="<?= e(iso_datetime($blog['last_post_at'] ?? null)) ?>"><?= e(local_datetime($blog['last_post_at'] ?? null, 'M j, Y', blog_timezone((int) ($blog['id'] ?? 0)))) ?></time>
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
