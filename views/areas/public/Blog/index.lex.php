{% extends "front.lex.php" %}

{% block title %}BlogHub — Discover blogs{% endblock %}

{% block body %}

    <!-- Hero / banner -->
    <section id="banner">
        <div class="content">
            <header>
                <h1>Discover great writing</h1>
                <p>Browse creators, topics, and the latest posts from across the platform.</p>
            </header>

            <form method="get" action="" class="home-search">
                <div class="row gtr-50 gtr-uniform">
                    <div class="col-8 col-12-small">
                        <input
                            type="text"
                            name="q"
                            id="q"
                            value="<?= htmlspecialchars($searchQuery ?? '') ?>"
                            placeholder="Search posts or blogs..."
                        />
                    </div>
                    <div class="col-4 col-6-small">
                        <select name="category" id="category">
                            <option value="">All categories</option>
                            <?php foreach ($categories as $cat) { ?>
                                <?php
                                    $isActive = isset($activeCategory)
                                        && (int) $activeCategory === (int) $cat['id'];
                                ?>
                                <option
                                    value="<?= (int) $cat['id']; ?>"
                                    <?= $isActive ? 'selected' : ''; ?>
                                >
                                    <?= htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-2 col-6-small">
                        <ul class="actions">
                            <li>
                                <button type="submit" class="button primary icon solid fa-search">
                                    Go
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php if (($mode ?? '') === 'search') { ?>
                    <p class="search-meta">
                        Showing
                        <strong><?= (int) ($pagination['totalPosts'] ?? 0); ?></strong>
                        result(s) for
                        <strong>"<?= htmlspecialchars($searchQuery); ?>"</strong>
                    </p>
                <?php } ?>
            </form>
        </div>
        <span class="image">
            <img src="images/DiscoverGreatWritingsIlustration.webp" alt="BlogHub" />
        </span>
    </section>

    <!-- Latest / search results -->
    <section id="home-posts">
        <header class="major">
            <h2>
                <?php if (($mode ?? '') === 'search' && $searchQuery !== '') { ?>
                    Search results
                <?php } elseif (!empty($activeCategory)) { ?>
                    Latest in category
                <?php } else { ?>
                    Latest posts
                <?php } ?>
            </h2>
        </header>

        <?php if (empty($posts)) { ?>
            <p>No posts found. Try adjusting your search or picking a different category.</p>
        <?php } else { ?>
            <div class="posts">
                <?php foreach ($posts as $post) { ?>
                    <article>
                      <?php $blogSlugs = array_column($blogs, 'blog_slug', 'id'); ?>
                        <?php if (!empty($post['featured_image'])) { ?>
                            <a href="/blog/<?= htmlspecialchars($blogSlugs[$post['blog_id']] ?? $post['blog_id']); ?>/<?= htmlspecialchars($post['slug']); ?>"
                               class="image">
                                <img src="<?= htmlspecialchars($post['featured_image']); ?>"
                                     alt="<?= htmlspecialchars($post['title']); ?>" />
                            </a>
                        <?php } ?>

                        <h3>
                            <a href="/blog/<?= htmlspecialchars($blogSlugs[$post['blog_id']] ?? $post['blog_id']); ?>/<?= htmlspecialchars($post['slug']); ?>">
                                <?= htmlspecialchars($post['title'] ?? 'Untitled'); ?>
                            </a>
                        </h3>

                        <p class="meta">
                            <?= htmlspecialchars($post['blog_name'] ?? 'Blog'); ?>
                            <?php if (!empty($post['category_name'])) { ?>
                                &middot;
                                <a href="?category=<?= (int) ($post['category_id'] ?? 0); ?>">
                                    <?= htmlspecialchars($post['category_name']); ?>
                                </a>
                            <?php } ?>
                            <?php if (!empty($post['published_at'])) { ?>
                                &middot;
                                <time datetime="<?= htmlspecialchars($post['published_at']); ?>">
                                    <?= htmlspecialchars($post['published_at']); ?>
                                </time>
                            <?php } ?>
                        </p>

                        <p>
                            <?= htmlspecialchars($post['excerpt'] ?? mb_substr(strip_tags($post['content'] ?? ''), 0, 180).'…'); ?>
                        </p>

                        <ul class="actions">
                            <li>
                                <a href="/blog/<?= htmlspecialchars($blogSlugs[$post['blog_id']] ?? $post['blog_id']); ?>/<?= htmlspecialchars($post['slug']); ?>"
                                   class="button">
                                    Read more
                                </a>
                            </li>
                        </ul>
                    </article>
                <?php } ?>
            </div>

            <!-- Pagination -->
            <?php if (($pagination['totalPages'] ?? 0) > 1) { ?>
                <ul class="pagination">
                    <?php for ($p = 1; $p <= (int) $pagination['totalPages']; $p++) { ?>
                        <?php
                            $isCurrent = $p === (int) $pagination['currentPage'];
                        $query = [
                            'page' => $p,
                            'q' => $searchQuery ?? null,
                            'category' => $activeCategory ?? null,
                        ];
                        $href = '?'.http_build_query(array_filter($query, fn ($v) => $v !== null && $v !== ''));
                        ?>
                        <li>
                            <a href="<?= $href; ?>"
                               class="button <?= $isCurrent ? 'primary' : 'small'; ?>">
                                <?= $p; ?>
                            </a>
                        </li>
                    <?php } ?>
                </ul>
            <?php } ?>
        <?php } ?>
    </section>

    <!-- Featured creators / blogs -->
    <?php if (!empty($featuredCreators)) { ?>
        <section id="featured-creators">
            <header class="major">
                <h2>Featured creators</h2>
            </header>
            <div class="features">
                <?php foreach ($featuredCreators as $creatorBlog) { ?>
                    <article>
                        <span class="icon solid fa-user"></span>
                        <div class="content">
                            <h3>
                                <a href="/blog/<?= htmlspecialchars($creatorBlog['blog_slug'] ?? $creatorBlog['id']); ?>">
                                    <?= htmlspecialchars($creatorBlog['blog_name'] ?? $creatorBlog['ownername']); ?>
                                </a>
                            </h3>
                            <p>
                                Posts: <?= (int) ($creatorBlog['postcount'] ?? 0); ?>
                                &middot;
                                Authors: <?= (int) ($creatorBlog['authorcount'] ?? 1); ?>
                            </p>
                            <p>
                                <?= htmlspecialchars($creatorBlog['description'] ?? ''); ?>
                            </p>
                        </div>
                    </article>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

    <!-- Category browse -->
    <?php if (!empty($categories)) { ?>
        <section id="category-browse">
            <header class="major">
                <h2>Browse by category</h2>
            </header>
            <div class="row gtr-50">
                <?php foreach ($categories as $cat) { ?>
                    <div class="col-3 col-6-medium col-12-small">
                        <a href="?category=<?= (int) $cat['id']; ?>"
                           class="button fit <?= isset($activeCategory) && (int) $activeCategory === (int) $cat['id'] ? 'primary' : 'alt'; ?>">
                            <?= htmlspecialchars($cat['name']); ?>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </section>
    <?php } ?>

{% endblock %}
