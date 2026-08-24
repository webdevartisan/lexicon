{% extends "front.lex.php" %}

{% block title %}<?= e($profile->displayName() ?? $profile->username() ?? 'Profile'); ?> — Profile{% endblock %}

{% block meta %}
<?php
    // Only block contents run (code outside blocks is discarded by the compiler),
    // so the head meta computes its own identity rather than sharing with the body.
    $profileName = $profile->displayName() ?? $profile->username() ?? 'Profile';

    // Description for search results and link previews: the bio reads best, but
    // it is free text with markup and newlines, so flatten it before truncating.
    $profileDesc = trim((string) ($profile->bio() ?? ''));
    if ($profileDesc === '') {
        $profileDesc = $profile->occupation() ?? ($profileName.' on Lexicon');
    }
    $profileDesc = truncate(trim((string) preg_replace('/\s+/', ' ', strip_tags($profileDesc))), 160);

    // Open Graph needs an absolute image URL; stored avatars are root-relative.
    $profileImage = '';
    $metaAvatar = $profile->avatarUrl();
    if (!empty($metaAvatar)) {
        $profileImage = preg_match('#^https?://#i', $metaAvatar)
            ? $metaAvatar
            : rtrim(base_url(), '/').$metaAvatar;
    }

    $profileUrl = rtrim(base_url(), '/').'/'.locale().'/profile/'.rawurlencode($profile->slug());
?>
<meta name="description" content="<?= e($profileDesc); ?>" />
<meta property="og:type" content="profile" />
<meta property="og:title" content="<?= e($profileName); ?>" />
<meta property="og:description" content="<?= e($profileDesc); ?>" />
<meta property="og:url" content="<?= e($profileUrl); ?>" />
<meta name="twitter:card" content="summary" />
<meta name="twitter:title" content="<?= e($profileName); ?>" />
<meta name="twitter:description" content="<?= e($profileDesc); ?>" />
<?php if ($profileImage !== '') { ?>
<meta property="og:image" content="<?= e($profileImage); ?>" />
<meta name="twitter:image" content="<?= e($profileImage); ?>" />
<?php } ?>
{% endblock %}

{% block body %}

<?php
    $profileName = $profile->displayName() ?? $profile->username() ?? 'Profile';
    $profileAvatar = $profile->avatarUrl();
    $postCount = (int) ($stats['posts'] ?? 0);
    $commentCount = (int) ($stats['comments'] ?? 0);
    $hasStats = $postCount > 0 || $commentCount > 0;
?>

  {# Identity: avatar, name, role, meta, actions, stats and bio as one unit #}
  <section class="lx-profile" aria-labelledby="profile-name">
    <div class="lx-profile-card">

      <div class="lx-profile-id">
        <div class="lx-profile-avatar">
          <?php if (!empty($profileAvatar)) { ?>
            <?php // Name sits beside the image as the h1, so the avatar is decorative to a screen reader. ?>
            <img src="<?= e($profileAvatar); ?>" alt="" width="152" height="152" decoding="async">
          <?php } else { ?>
            <span class="lx-profile-initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(trim($profileName), 0, 1))); ?></span>
          <?php } ?>
        </div>

        <div class="lx-profile-headline">
          <h1 id="profile-name"><?= e($profileName); ?></h1>
          <p class="lx-profile-role"><?= e($profile->occupation() ?? 'Creator on Lexicon'); ?></p>

          <?php if (!empty($profile->location()) || !empty($profile->memberSince())) { ?>
            <ul class="lx-profile-meta">
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
          <?php } ?>

          <?php if (!empty($websiteUrl) || !empty($socialLinks)) { ?>
            <div class="lx-profile-actions">
              <?php if (!empty($websiteUrl)) { ?>
                <a href="<?= e($websiteUrl); ?>" class="lx-btn lx-btn--primary lx-btn--small"
                  target="_blank" rel="noopener noreferrer nofollow">Visit website</a>
              <?php } ?>

              <?php if (!empty($socialLinks)) { ?>
                <ul class="lx-profile-social">
                  <?php foreach ($socialLinks as $socialLink) { ?>
                    <li>
                      <a href="<?= e($socialLink['url']); ?>"
                        class="icon <?= e($socialLink['iconStyle']); ?> fa <?= e($socialLink['icon']); ?>"
                        target="_blank" rel="noopener noreferrer nofollow">
                        <span class="label"><?= e(ucfirst($socialLink['network'])); ?></span>
                      </a>
                    </li>
                  <?php } ?>
                </ul>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      </div>

      <?php if ($hasStats) { ?>
        <dl class="lx-profile-stats">
          <?php if ($postCount > 0) { ?>
            <div class="lx-profile-stat">
              <dt>Posts</dt>
              <dd><?= e(number_format($postCount)); ?></dd>
            </div>
          <?php } ?>
          <?php if ($commentCount > 0) { ?>
            <div class="lx-profile-stat">
              <dt>Comments received</dt>
              <dd><?= e(number_format($commentCount)); ?></dd>
            </div>
          <?php } ?>
        </dl>
      <?php } ?>

      <div class="lx-profile-bio">
        <h2>About</h2>
        <?php if (!empty($profile->bio())) { ?>
          <p><?= nl2br(e($profile->bio())); ?></p>
        <?php } else { ?>
          <p class="lx-profile-bio-empty"><?= e($profileName); ?> hasn&rsquo;t written a bio yet.</p>
        <?php } ?>
      </div>

    </div>
  </section>

  {# Recent posts feed (reuses the shared .posts grid) #}
  <section id="profile-posts" class="lx-profile-feed" aria-labelledby="profile-posts-heading">
    <header class="major"><h2 id="profile-posts-heading">Recent posts</h2></header>

    <?php if (empty($posts)) { ?>
      <p class="lx-profile-feed-empty"><?= e($profileName); ?> hasn&rsquo;t published anything public yet. Check back soon.</p>
    <?php } else { ?>
      <div class="posts">
        <?php foreach ($posts as $post) { ?>
          <?php
            $postUrl = '/blog/'.rawurlencode((string) ($post['blog_slug'] ?? $post['blog_id'])).'/'.rawurlencode($post['slug']);
            $postTitle = $post['title'] ?? 'Untitled';
            // Excerpts are stored as free text and some carry markup; strip it so
            // cards show prose, not literal tags, and keep the height uniform.
            $postExcerpt = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($post['excerpt'] ?? ''))));
            ?>
          <article>
            <a href="<?= e($postUrl); ?>" class="image post-thumb" tabindex="-1" aria-hidden="true">
              <?php if (!empty($post['featured_image'])) { ?>
                <img src="<?= e($post['featured_image']); ?>" alt="" loading="lazy">
              <?php } else { ?>
                <span class="post-thumb-fallback" aria-hidden="true">
                  <?= e(mb_strtoupper(mb_substr(trim($postTitle), 0, 1))); ?>
                </span>
              <?php } ?>
            </a>

            <p class="meta">
              <?= e($post['blog_name'] ?? 'Blog'); ?>
              <?php if (!empty($post['published_at'])) { ?>
                &middot; <time datetime="<?= e(iso_datetime($post['published_at'] ?? null)); ?>"><?= e(local_datetime($post['published_at'] ?? null, 'M j, Y', site_timezone())); ?></time>
              <?php } ?>
            </p>

            <h3>
              <a href="<?= e($postUrl); ?>"><?= e($postTitle); ?></a>
            </h3>

            <?php if ($postExcerpt !== '') { ?>
              <p><?= e(truncate($postExcerpt, 160)); ?></p>
            <?php } ?>

            <ul class="actions">
              <li><a class="lx-btn lx-btn--subtle lx-btn--small" href="<?= e($postUrl); ?>">Read more<span class="label"> about <?= e($postTitle); ?></span></a></li>
            </ul>
          </article>
        <?php } ?>
      </div>
    <?php } ?>
  </section>

{% endblock %}
