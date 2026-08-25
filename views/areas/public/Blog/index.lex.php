{% extends "front.lex.php" %}

{% block title %}{{ t('explore.metaTitle') }}{% endblock %}

{% block body %}

    <section class="lx-page-head" aria-labelledby="explore-heading">
        <h1 id="explore-heading">{{ t('explore.heroTitle') }}</h1>
    </section>

    {% if (!empty($featuredCreators)): %}
        <section class="lx-bleed lx-slider" aria-label="{{ t('explore.featuredTitle') }}" data-slider>
            <div class="lx-slider-rail" data-slider-rail>
                {% foreach ($featuredCreators as $creatorBlog): %}
                <?php
                $creatorUrl = '/blog/'.rawurlencode($creatorBlog['blog_slug']);
                $creatorInitial = mb_strtoupper(mb_substr(trim((string) ($creatorBlog['blog_name'] ?? '?')), 0, 1));
                $creatorPosts = (int) ($creatorBlog['postcount'] ?? 0);
                $creatorWriters = (int) ($creatorBlog['authorcount'] ?? 1);
                $creatorBanner = trim((string) ($creatorBlog['banner_path'] ?? ''));
                // Stored paths are root-relative under /uploads; leave absolute URLs alone.
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
                            <p class="lx-slide-eyebrow">{{ t('explore.featuredTitle') }}</p>
                            <h2 class="lx-slide-title"><a href="<?= e($creatorUrl) ?>">{{ creatorBlog.blog_name }}</a></h2>
                            <p class="lx-slide-meta">
                                <?= $creatorPosts ?> {{ t('explore.postsLabel') }}
                                {% if ($creatorWriters > 1): %}
                                    &middot; <?= $creatorWriters ?> {{ t('explore.writersLabel') }}
                                {% endif %}
                            </p>
                            {% if creatorBlog.description %}
                                <p class="lx-slide-desc"><?= e(truncate((string) $creatorBlog['description'], 220)) ?></p>
                            {% endif %}
                            <a href="<?= e($creatorUrl) ?>" class="lx-btn lx-btn-primary lx-slide-cta">{{ t('explore.visitBlog') }}</a>
                        </div>
                    </div>
                </article>
                {% endforeach; %}
            </div>
            <button type="button" class="lx-slider-nav lx-slider-prev" data-slider-prev aria-label="Previous featured blog">
                <span class="fa-solid fa-chevron-left" aria-hidden="true"></span>
            </button>
            <button type="button" class="lx-slider-nav lx-slider-next" data-slider-next aria-label="Next featured blog">
                <span class="fa-solid fa-chevron-right" aria-hidden="true"></span>
            </button>
            <div class="lx-slider-dots" data-slider-dots aria-label="Slide navigation"></div>
        </section>
    {% endif %}

    <?php
    $blogsTabUrl = '/blogs?'.http_build_query(array_filter(['tab' => 'blogs', 'q' => $searchQuery]));
    $postsTabUrl = '/blogs?'.http_build_query(array_filter(['tab' => 'posts', 'q' => $searchQuery]));
    ?>

    <div class="lx-explore-toolbar">
        <div class="lx-explore-tabs" role="tablist">
            <a href="<?= e($blogsTabUrl) ?>" role="tab" class="lx-explore-tab <?= $tab === 'blogs' ? 'active' : '' ?>"
               aria-selected="<?= $tab === 'blogs' ? 'true' : 'false' ?>">{{ t('explore.tabBlogs') }}</a>
            <a href="<?= e($postsTabUrl) ?>" role="tab" class="lx-explore-tab <?= $tab === 'posts' ? 'active' : '' ?>"
               aria-selected="<?= $tab === 'posts' ? 'true' : 'false' ?>">{{ t('explore.tabPosts') }}</a>
        </div>

        <form method="get" action="/blogs" class="lx-explore-search" role="search" aria-label="{{ t('explore.searchButton') }}">
            <input type="hidden" name="tab" value="{{ tab }}" />
            <label class="lx-visually-hidden" for="explore-q">{{ t('explore.searchButton') }}</label>
            <div class="lx-search-field">
                <input
                    type="search"
                    name="q"
                    id="explore-q"
                    value="{{ searchQuery }}"
                    placeholder="<?= e($tab === 'posts' ? $t('explore.searchPlaceholderPosts') : $t('explore.searchPlaceholderBlogs')) ?>"
                />
                <button type="submit" class="lx-search-field-submit" aria-label="{{ t('explore.searchButton') }}">
                    <span class="fa-solid fa-magnifying-glass" aria-hidden="true"></span>
                </button>
            </div>
        </form>
    </div>

    {% if (!empty($searchQuery)): %}
        <p class="lx-search-meta" role="status">
            <strong><?= (int) ($pagination['total'] ?? 0) ?></strong>
            {{ t('explore.resultsFor') }}
            <strong>"{{ searchQuery }}"</strong>
        </p>
    {% endif %}

    {% if ($tab === 'posts'): %}
    <section id="explore-posts" aria-label="{{ t('explore.tabPosts') }}">
        {% if ($searchQuery !== ''): %}
        <header class="lx-section-head">
            <h2>{{ t('explore.searchResults') }}</h2>
        </header>
        {% endif %}

        {% if (empty($items)): %}
            <p>{{ t('explore.noPosts') }}</p>
        {% else %}
            <div class="lx-gallery">
                {% foreach ($items as $post): %}
                <?php
                $postUrl = '/blog/'.rawurlencode($post['blog_slug']).'/'.rawurlencode($post['slug']);
                $rawExcerpt = ($post['excerpt'] ?? '') !== '' && $post['excerpt'] !== null
                    ? $post['excerpt']
                    : ($post['content'] ?? '');
                $excerpt = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $rawExcerpt)));
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
                            <p class="lx-gallery-excerpt"><?= e(truncate($excerpt, 160)) ?></p>
                        {% endif %}
                    </div>
                </article>
                {% endforeach; %}
            </div>
        {% endif %}
    </section>
    {% else %}
    <section id="explore-blogs" aria-label="{{ t('explore.tabBlogs') }}">
        {% if ($searchQuery !== ''): %}
        <header class="lx-section-head">
            <h2>{{ t('explore.searchResults') }}</h2>
        </header>
        {% endif %}

        {% if (empty($items)): %}
            <p>{{ t('explore.noBlogs') }}</p>
        {% else %}
            <div class="lx-blog-cards">
                {% foreach ($items as $blog): %}
                <?php $blogUrl = '/blog/'.rawurlencode($blog['blog_slug']); ?>
                <article class="lx-blog-card">
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
                    {% if blog.last_post_at %}
                        <p class="meta">
                            {{ t('explore.lastPostLabel') }}:
                            <time datetime="<?= e(iso_datetime($blog['last_post_at'] ?? null)) ?>"><?= e(local_datetime($blog['last_post_at'] ?? null, 'M j, Y', blog_timezone((int) ($blog['id'] ?? 0)))) ?></time>
                        </p>
                    {% endif %}
                    <div class="lx-form-actions lx-blog-card-actions">
                        <a href="<?= e($blogUrl) ?>" class="lx-btn lx-btn-primary">{{ t('explore.visitBlog') }}</a>
                    </div>
                </article>
                {% endforeach; %}
            </div>
        {% endif %}
    </section>
    {% endif %}

    {% if ((int) ($pagination['totalPages'] ?? 0) > 1): %}
        <?php
        $totalPages = (int) $pagination['totalPages'];
        $currentPage = (int) $pagination['currentPage'];

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
        <nav aria-label="{{ t('explore.paginationAria') }}">
            <ul class="lx-pagination">
                {% if ($currentPage > 1): %}
                    <li>
                        <a href="<?= e($pageUrl($currentPage - 1)) ?>" class="lx-pagination-step" rel="prev">
                            <span class="fa-solid fa-chevron-left" aria-hidden="true"></span>
                            <span class="lx-visually-hidden">Previous page</span>
                        </a>
                    </li>
                {% endif %}

                <?php $previous = 0; ?>
                {% foreach ($pagesToShow as $p): %}
                    {% if ($p - $previous > 1): %}
                        <li><span class="lx-pagination-gap" aria-hidden="true">&hellip;</span></li>
                    {% endif %}
                    <li>
                        <a href="<?= e($pageUrl($p)) ?>"
                           <?= $p === $currentPage ? 'aria-current="page"' : '' ?>>
                            <?= (int) $p ?>
                        </a>
                    </li>
                    <?php $previous = $p; ?>
                {% endforeach; %}

                {% if ($currentPage < $totalPages): %}
                    <li>
                        <a href="<?= e($pageUrl($currentPage + 1)) ?>" class="lx-pagination-step" rel="next">
                            <span class="lx-visually-hidden">Next page</span>
                            <span class="fa-solid fa-chevron-right" aria-hidden="true"></span>
                        </a>
                    </li>
                {% endif %}
            </ul>
        </nav>
    {% endif %}

{% endblock %}
