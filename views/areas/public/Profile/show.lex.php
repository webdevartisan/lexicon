{% extends "front.lex.php" %}

{% block title %}<?= e($profile->displayName() ?? $profile->username() ?? 'Profile'); ?> — Profile{% endblock %}

{% block body %}

  {# Profile header #}
  <section>

    <div class="box profile-card">
      <div class="row gtr-50 aln-middle">
        <div class="col-3 col-12-small">
          <div class="profile-image">
            <?php $displayName = $profile->displayName() ?? $profile->username() ?? 'Profile'; ?>
            <?php if (!empty($profile->avatarUrl())) { ?>
              <img src="<?= e($profile->avatarUrl()); ?>"
                  alt="<?= e($displayName); ?>">
            <?php } else { ?>
              <?php // no avatar: render initials so the page never leans on external placeholder services?>
              <div class="profile-initials" aria-hidden="true">
                <?= e(mb_strtoupper(mb_substr(trim($displayName), 0, 1))); ?>
              </div>
            <?php } ?>
          </div>
        </div>

        <div class="col-9 col-12-small profile-right">
          <div class="profile-main">
            <h1><?= e($displayName); ?></h1>
            <header class="major">
              <p><?= e($profile->occupation() ?? 'Creator on Lexicon'); ?></p>
            </header>

            <ul class="profile-meta">
              <?php if (!empty($profile->location())) { ?>
                <li>
                  <span class="icon solid fa fa-location-dot" aria-hidden="true"></span>
                  <?= e($profile->location()); ?>
                </li>
              <?php } ?>
              <?php if (!empty($profile->memberSince())) { ?>
                <li>
                  <span class="icon solid fa fa-calendar" aria-hidden="true"></span>
                  Member since <?= e(local_datetime($profile->memberSince(), 'F Y', site_timezone())); ?>
                </li>
              <?php } ?>
            </ul>

            <?php if (!empty($websiteUrl)) { ?>
              <ul class="actions">
                <li>
                  <a href="<?= e($websiteUrl); ?>" class="button primary icon solid fa fa-globe"
                    target="_blank" rel="noopener noreferrer">Visit website</a>
                </li>
              </ul>
            <?php } ?>

            <div class="row gtr-50 profile-stats">
              <div class="col-4 col-12-xsmall">
                <strong><?= number_format($stats['posts'] ?? 0); ?></strong><br>Posts
              </div>
              <div class="col-4 col-12-xsmall">
                <strong><?= number_format($stats['comments'] ?? 0); ?></strong><br>Comments received
              </div>
            </div>
          </div>

          <?php if (!empty($socialLinks)) { ?>
            <ul class="icons profile-social">
              <?php foreach ($socialLinks as $socialLink) { ?>
                <li>
                  <a href="<?= e($socialLink['url']); ?>"
                    class="icon <?= e($socialLink['iconStyle']); ?> fa <?= e($socialLink['icon']); ?>"
                    target="_blank"
                    rel="noopener noreferrer">
                    <span class="label"><?= e(ucfirst($socialLink['network'])); ?></span>
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
      <p>This creator hasn't added a bio yet.</p>
    <?php } ?>
  </section>

  {# Recent posts (reuses .posts grid) #}
  <section id="profile-posts">
    <header class="major"><h2>Recent posts</h2></header>

    <?php if (empty($posts)) { ?>
      <p>No public posts yet. Check back soon.</p>
    <?php } else { ?>
      <div class="posts">
        <?php foreach ($posts as $post) { ?>
          <?php
            $postUrl = '/blog/'.rawurlencode((string) ($post['blog_slug'] ?? $post['blog_id'])).'/'.rawurlencode($post['slug']);
            $postTitle = $post['title'] ?? 'Untitled';
            ?>
          <article>
            <a href="<?= e($postUrl); ?>" class="image post-thumb">
              <?php if (!empty($post['featured_image'])) { ?>
                <img src="<?= e($post['featured_image']); ?>"
                    alt="<?= e($postTitle); ?>"
                    loading="lazy">
              <?php } else { ?>
                <span class="post-thumb-fallback" aria-hidden="true">
                  <?= e(mb_strtoupper(mb_substr(trim($postTitle), 0, 1))); ?>
                </span>
              <?php } ?>
            </a>

            <h3>
              <a href="<?= e($postUrl); ?>"><?= e($postTitle); ?></a>
            </h3>

            <p class="meta">
              <?= e($post['blog_name'] ?? 'Blog'); ?>
              <?php if (!empty($post['published_at'])) { ?>
                &middot; <time datetime="<?= e(iso_datetime($post['published_at'] ?? null)); ?>"><?= e(local_datetime($post['published_at'] ?? null, 'M j, Y', site_timezone())); ?></time>
              <?php } ?>
            </p>

            <?php if (!empty($post['excerpt'])) { ?>
              <p><?= e($post['excerpt']); ?></p>
            <?php } ?>

            <ul class="actions">
              <li><a class="button" href="<?= e($postUrl); ?>">Read more</a></li>
            </ul>
          </article>
        <?php } ?>
      </div>
    <?php } ?>
  </section>

{% endblock %}
