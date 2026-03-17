{% extends "front.lex.php" %}

{% block title %}<?= e($profile->displayName() ?? 'Profile'); ?> — Profile{% endblock %}

{% block body %}

  {# Profile header #}
  <section>

    <div class="box profile-card">
      <div class="row gtr-50 aln-middle">
        <div class="col-3 col-12-small">
          <div class="profile-image">
            <?php if (!empty($profile->avatarUrl())) { ?>
              <img src="<?= e($profile->avatarUrl()); ?>"
                  alt="<?= e($profile->displayName() ?? 'Profile'); ?>">
            <?php } else { ?>
              <img src="https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?auto=format&fit=crop&w=500&q=80" alt="Profile avatar">
            <?php } ?>
          </div>
        </div>

        <div class="col-9 col-12-small profile-right">
          <div class="profile-main">
            <h1><?= e($profile->displayName() ?? ''); ?></h1>
            <header class="major">
              <p>
                <?php if (!empty($profile->location())) { ?>
                  <?= e($profile->location()); ?>
                <?php } else { ?>
                  Creator on Lexicon
                <?php } ?>
              </p>
            </header>
            <p style="margin-bottom: 1em;">
              <?= e($tagline ?? 'Sharing thoughts on Lexicon.'); ?>
            </p>

            <ul class="actions">
              <li><a href="#" class="button primary">Follow</a></li>
              <li><a href="#" class="button">Website</a></li>
            </ul>

            <div class="row gtr-50">
              <div class="col-4 col-12-xsmall">
                <strong><?= ($stats['posts'] ?? 0); ?></strong><br>Posts
              </div>
              <div class="col-4 col-12-xsmall">
                <strong><?= ($stats['followers'] ?? 0); ?></strong><br>Followers
              </div>
              <div class="col-4 col-12-xsmall">
                <strong><?= ($stats['following'] ?? 0); ?></strong><br>Following
              </div>
            </div>
          </div>

          <?php if (!empty($socialLinks)) { ?>
            <ul class="icons profile-social">
              <?php foreach ($socialLinks as $socialLink) { ?>
                <li>
                  <a href="<?= e($socialLink['url']); ?>"
                    class="icon brands <?= e($socialLink['icon'] ?? $socialLink['network']); ?>"
                    target="_blank"
                    rel="noopener noreferrer">
                    <span class="label"><?= e($socialLink['network']); ?></span>
                  </a>
                </li>
              <?php } ?>
            </ul>
          <?php } ?>
        </div>

      </div>
    </div>
  </section>

  {# About #}
  <section id="profile-about">
    <header class="major"><h2>About</h2></header>

    <?php if (!empty($profile->bio())) { ?>
      <p><?= nl2br(e($profile->bio())); ?></p>
    <?php } else { ?>
      <p>This creator hasn’t added a bio yet.</p>
    <?php } ?>

    <?php // if (!empty($profile['topics'])):?>
      <div class="row gtr-50">
        <?php // foreach ($profile['topics'] as $topic):?>
          <div class="col-3 col-6-medium col-12-small">
            <!-- <span class="button fit small alt"><?php // e($topic);?></span> -->
          </div>
        <?php // endforeach;?>
      </div>
    <?php // endif;?>
  </section>

  {# Recent posts (reuses .posts grid) #}
  <section id="profile-posts">
    <header class="major"><h2>Recent posts</h2></header>

    <?php if (empty($posts)) { ?>
      <p>No posts yet.</p>
    <?php } else { ?>
      <div class="posts">
        <?php foreach ($posts as $post) { ?>
          <article>
            <?php
              $img = !empty($post['featured_image'])
                ? $post['featured_image']
                : 'https://placehold.co/600x400/000000/FFFFFF/png?text=No+Image';
            ?>
              <a href="/blog/<?= e($post['blog_slug'] ?? $post['blog_id']); ?>/<?= e($post['slug']); ?>"
                class="image post-thumb">
                <img src="<?= e($img); ?>"
                    alt="<?= e($post['title'] ?? 'Post'); ?>"
                    loading="lazy">
              </a>
            <h3>
              <a href="/blog/<?= e($post['blog_slug'] ?? $post['blog_id']); ?>/<?= e($post['slug']); ?>">
                <?= e($post['title'] ?? 'Untitled'); ?>
              </a>
            </h3>

            <p class="meta">
              <?= e($post['blog_name'] ?? 'Blog'); ?>
              <?php if (!empty($post['published_at'])) { ?>
                &middot; <time datetime="<?= e($post['published_at']); ?>"><?= e($post['published_at']); ?></time>
              <?php } ?>
            </p>

            <p><?= e($post['excerpt'] ?? mb_substr(strip_tags($post['content'] ?? ''), 0, 180).'…'); ?></p>

            <ul class="actions">
              <li><a class="button" href="/blog/<?= e($post['blog_slug'] ?? $post['blog_id']); ?>/<?= e($post['slug']); ?>">Read more</a></li>
            </ul>
          </article>
        <?php } ?>
      </div>

      <?php if (($pagination['totalPages'] ?? 0) > 1) { ?>
        <ul class="pagination">
          <?php for ($p = 1; $p <= $pagination['totalPages']; $p++) { ?>
            <?php $isCurrent = $p === $pagination['currentPage']; ?>
            <li>
              <a href="?page=<?= $p; ?>" class="button <?= $isCurrent ? 'primary' : 'small'; ?>"><?= $p; ?></a>
            </li>
          <?php } ?>
        </ul>
      <?php } ?>
    <?php } ?>
  </section>

{% endblock %}
