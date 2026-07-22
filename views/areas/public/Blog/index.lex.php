{% extends "front.lex.php" %}

{% block title %}{{ t('explore.metaTitle') }}{% endblock %}

{% block body %}

    <!-- Hero / banner -->
    <section id="banner">
        <div class="content">
            <header>
                <h1>{{ t('explore.heroTitle') }}</h1>
                <p>{{ t('explore.heroSubtitle') }}</p>
            </header>

            <form method="get" action="/blogs" class="home-search">
                <input type="hidden" name="tab" value="{{ tab }}" />
                <div class="row gtr-50 gtr-uniform">
                    <div class="col-9 col-12-small">
                        <input
                            type="text"
                            name="q"
                            id="q"
                            value="{{ searchQuery }}"
                            placeholder="<?= e($tab === 'posts' ? $t('explore.searchPlaceholderPosts') : $t('explore.searchPlaceholderBlogs')) ?>"
                        />
                    </div>
                    <div class="col-3 col-6-small">
                        <ul class="actions">
                            <li>
                                <button type="submit" class="button primary icon solid fa-search">
                                    {{ t('explore.searchButton') }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                {% if (!empty($searchQuery)): %}
                    <p class="search-meta">
                        <strong><?= (int) ($pagination['total'] ?? 0) ?></strong>
                        {{ t('explore.resultsFor') }}
                        <strong>"{{ searchQuery }}"</strong>
                    </p>
                {% endif %}
            </form>
        </div>
        <span class="image">
            <img src="/images/DiscoverGreatWritingsIlustration.webp" alt="" />
        </span>
    </section>

    <?php
    // Tab links keep the search term; page resets when switching context.
    $blogsTabUrl = '/blogs?'.http_build_query(array_filter(['tab' => 'blogs', 'q' => $searchQuery]));
                            $postsTabUrl = '/blogs?'.http_build_query(array_filter(['tab' => 'posts', 'q' => $searchQuery]));
                            ?>

    <!-- Tabs -->
    <div class="explore-tabs" role="tablist">
        <a href="<?= e($blogsTabUrl) ?>" role="tab" class="explore-tab <?= $tab === 'blogs' ? 'active' : '' ?>"
           aria-selected="<?= $tab === 'blogs' ? 'true' : 'false' ?>">{{ t('explore.tabBlogs') }}</a>
        <a href="<?= e($postsTabUrl) ?>" role="tab" class="explore-tab <?= $tab === 'posts' ? 'active' : '' ?>"
           aria-selected="<?= $tab === 'posts' ? 'true' : 'false' ?>">{{ t('explore.tabPosts') }}</a>
    </div>

    {% if ($tab === 'posts'): %}
    <!-- Latest posts / search results -->
    <section id="explore-posts">
        <header class="major">
            <h2><?= e($searchQuery !== '' ? $t('explore.searchResults') : $t('explore.tabPosts')) ?></h2>
        </header>

        {% if (empty($items)): %}
            <p>{{ t('explore.noPosts') }}</p>
        {% else %}
            <div class="posts">
                {% foreach ($items as $post): %}
                <?php
                                            $postUrl = '/blog/'.rawurlencode($post['blog_slug']).'/'.rawurlencode($post['slug']);
                            $excerpt = ($post['excerpt'] ?? '') !== '' && $post['excerpt'] !== null
                                ? $post['excerpt']
                                : truncate(strip_tags($post['content'] ?? ''), 160);
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
                        <time datetime="<?= e(iso_datetime($post['published_at'] ?? null)) ?>"><?= e(local_datetime($post['published_at'] ?? null, 'M j, Y', site_timezone())) ?></time>
                        {% endif %}
                    </p>

                    <p>{{ excerpt }}</p>

                    <ul class="actions">
                        <li><a href="<?= e($postUrl) ?>" class="button">{{ t('explore.readMore') }}</a></li>
                    </ul>
                </article>
                {% endforeach; %}
            </div>
        {% endif %}
    </section>
    {% else %}
    <!-- Blog directory -->
    <section id="explore-blogs">
        <header class="major">
            <h2><?= e($searchQuery !== '' ? $t('explore.searchResults') : $t('explore.tabBlogs')) ?></h2>
        </header>

        {% if (empty($items)): %}
            <p>{{ t('explore.noBlogs') }}</p>
        {% else %}
            <div class="blog-cards">
                {% foreach ($items as $blog): %}
                <?php $blogUrl = '/blog/'.rawurlencode($blog['blog_slug']); ?>
                <article class="blog-card">
                    <h3><a href="<?= e($blogUrl) ?>">{{ blog.blog_name }}</a></h3>
                    <p class="meta">
                        {{ t('explore.byLabel') }} {{ blog.owner_name }}
                        &middot; <?= (int) $blog['post_count'] ?> {{ t('explore.postsLabel') }}
                        {% if ((int) ($blog['author_count'] ?? 0) > 1): %}
                        &middot; <?= (int) $blog['author_count'] ?> {{ t('explore.writersLabel') }}
                        {% endif %}
                    </p>
                    {% if blog.description %}
                    <p><?= e(truncate((string) $blog['description'], 180)) ?></p>
                    {% endif %}
                    <p class="meta">
                        {% if blog.last_post_at %}
                        {{ t('explore.lastPostLabel') }}:
                        <time datetime="<?= e(iso_datetime($blog['last_post_at'] ?? null)) ?>"><?= e(local_datetime($blog['last_post_at'] ?? null, 'M j, Y', blog_timezone((int) ($blog['id'] ?? 0)))) ?></time>
                        {% endif %}
                    </p>
                    <ul class="actions">
                        <li><a href="<?= e($blogUrl) ?>" class="button">{{ t('explore.visitBlog') }}</a></li>
                    </ul>
                </article>
                {% endforeach; %}
            </div>
        {% endif %}
    </section>
    {% endif %}

    <!-- Windowed pagination shared by both tabs -->
    {% if ((int) ($pagination['totalPages'] ?? 0) > 1): %}
        <?php
        $totalPages = (int) $pagination['totalPages'];
                            $currentPage = (int) $pagination['currentPage'];

                            // Window of pages around the current one; edges always visible.
                            $window = 2;
                            $pagesToShow = [1, $totalPages];
                            for ($p = $currentPage - $window; $p <= $currentPage + $window; $p++) {
                                if ($p >= 1 && $p <= $totalPages) {
                                    $pagesToShow[] = $p;
                                }
                            }
                            $pagesToShow = array_unique($pagesToShow);
                            sort($pagesToShow);

                            $pageUrl = function (int $p) use ($tab, $searchQuery): string {
                                return '/blogs?'.http_build_query(array_filter([
                                    'tab' => $tab,
                                    'q' => $searchQuery,
                                    'page' => $p > 1 ? $p : null,
                                ]));
                            };
                            ?>
        <ul class="pagination" aria-label="{{ t('explore.paginationAria') }}">
            <?php $previous = 0; ?>
            {% foreach ($pagesToShow as $p): %}
                {% if ($p - $previous > 1): %}
                    <li><span class="pagination-gap">&hellip;</span></li>
                {% endif %}
                <li>
                    <a href="<?= e($pageUrl($p)) ?>"
                       class="button small <?= $p === $currentPage ? 'primary' : '' ?>"
                       <?= $p === $currentPage ? 'aria-current="page"' : '' ?>>
                        {{ p }}
                    </a>
                </li>
                <?php $previous = $p; ?>
            {% endforeach; %}
        </ul>
    {% endif %}

    <!-- Featured blogs: admin picks only -->
    {% if (!empty($featuredCreators)): %}
        <section id="featured-creators">
            <header class="major">
                <h2>{{ t('explore.featuredTitle') }}</h2>
            </header>
            <div class="features">
                {% foreach ($featuredCreators as $creatorBlog): %}
                <?php $creatorUrl = '/blog/'.rawurlencode($creatorBlog['blog_slug']); ?>
                    <article>
                        <span class="icon solid fa-user"></span>
                        <div class="content">
                            <h3><a href="<?= e($creatorUrl) ?>">{{ creatorBlog.blog_name }}</a></h3>
                            <p>
                                {{ t('explore.postsLabel') }}: <?= (int) ($creatorBlog['postcount'] ?? 0) ?>
                                &middot;
                                {{ t('explore.writersLabel') }}: <?= (int) ($creatorBlog['authorcount'] ?? 1) ?>
                            </p>
                            {% if creatorBlog.description %}
                            <p><?= e(truncate((string) $creatorBlog['description'], 160)) ?></p>
                            {% endif %}
                        </div>
                    </article>
                {% endforeach; %}
            </div>
        </section>
    {% endif %}

{% endblock %}
