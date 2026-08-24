{% extends "front.lex.php" %}

{% block title %}<?= e($profile->displayName() ?? $profile->username() ?? 'Profile'); ?> | <?= e(site_setting('site_name', 'Lexicon')); ?>{% endblock %}

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

    // Topic chips are derived from the blogs this author actually publishes on,
    // read straight out of the posts we already loaded, so no extra query and
    // nothing invented. Keyed by slug to dedupe, capped so the row stays tidy.
    $authorBlogs = [];
    foreach ($posts as $chipPost) {
        $chipSlug = $chipPost['blog_slug'] ?? null;
        $chipName = $chipPost['blog_name'] ?? null;
        if ($chipSlug !== null && $chipName !== null && !isset($authorBlogs[$chipSlug])) {
            $authorBlogs[$chipSlug] = $chipName;
        }
    }
    $authorBlogs = array_slice($authorBlogs, 0, 4, true);
?>

  <section class="lx-profile" aria-labelledby="profile-name">
    <div class="lx-profile-card">

      <div class="lx-profile-avatar">
        <?php if (!empty($profileAvatar)) { ?>
          <?php // Name follows immediately as the h1, so the avatar is decorative to a screen reader. ?>
          <img src="<?= e($profileAvatar); ?>" alt="" width="132" height="132" decoding="async">
        <?php } else { ?>
          <span class="lx-profile-initials" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(trim($profileName), 0, 1))); ?></span>
        <?php } ?>
      </div>

      <h1 id="profile-name"><?= e($profileName); ?></h1>
      <?php if (!empty($profile->occupation())) { ?>
        <p class="lx-profile-role"><?= e($profile->occupation()); ?></p>
      <?php } ?>

      <?php if ($hasStats) { ?>
        <dl class="lx-profile-stats">
          <?php if ($postCount > 0) { ?>
            <div class="lx-profile-stat"><dd><?= e(number_format($postCount)); ?></dd><dt>Posts</dt></div>
          <?php } ?>
          <?php if ($commentCount > 0) { ?>
            <div class="lx-profile-stat"><dd><?= e(number_format($commentCount)); ?></dd><dt>Comments</dt></div>
          <?php } ?>
        </dl>
      <?php } ?>

      <?php if (!empty($profile->location()) || !empty($profile->memberSince())) { ?>
        <ul class="lx-profile-meta">
          <?php if (!empty($profile->location())) { ?>
            <li><span class="icon solid fa fa-location-dot" aria-hidden="true"></span><?= e($profile->location()); ?></li>
          <?php } ?>
          <?php if (!empty($profile->memberSince())) { ?>
            <li><span class="icon solid fa fa-calendar" aria-hidden="true"></span>Member since <?= e(local_datetime($profile->memberSince(), 'F Y', site_timezone())); ?></li>
          <?php } ?>
        </ul>
      <?php } ?>

      <?php if (!empty($profile->bio())) { ?>
        <p class="lx-profile-bio"><?= nl2br(e($profile->bio())); ?></p>
      <?php } ?>

      <?php if (!empty($authorBlogs)) { ?>
        <ul class="lx-profile-tags" aria-label="Publishes on">
          <?php foreach ($authorBlogs as $blogSlug => $blogName) { ?>
            <li><a href="/blog/<?= e(rawurlencode((string) $blogSlug)); ?>"><?= e($blogName); ?></a></li>
          <?php } ?>
        </ul>
      <?php } ?>

      <?php if (!empty($websiteUrl)) { ?>
        <div class="lx-profile-cta">
          <a href="<?= e($websiteUrl); ?>" class="lx-btn lx-btn--primary"
            target="_blank" rel="noopener noreferrer nofollow">Visit website</a>
        </div>
      <?php } ?>

      <?php if (!empty($socialLinks)) { ?>
        <ul class="lx-profile-social" aria-label="Social links">
          <?php foreach ($socialLinks as $socialLink) { ?>
            <?php // FontAwesome 6 splits families: brand glyphs need fa-brands, not the solid-default fa. ?>
            <?php $iconFamily = $socialLink['iconStyle'] === 'brands' ? 'fa-brands' : 'fa-solid'; ?>
            <li>
              <a href="<?= e($socialLink['url']); ?>"
                class="<?= e($iconFamily); ?> <?= e($socialLink['icon']); ?>"
                target="_blank" rel="noopener noreferrer nofollow">
                <span class="label"><?= e(ucfirst($socialLink['network'])); ?></span>
              </a>
            </li>
          <?php } ?>
        </ul>
      <?php } ?>

    </div>
  </section>

  <?php // Only creators with public posts get a feed; a reader's profile ends at the card. ?>
  <?php if (!empty($posts)) { ?>
  <section id="profile-posts" class="lx-profile-feed" aria-labelledby="profile-posts-heading">
    <header class="major"><h2 id="profile-posts-heading">Recent posts</h2></header>

      <div class="lx-gallery">
        <?php foreach ($posts as $post) { ?>
          <?php
            $postUrl = '/blog/'.rawurlencode((string) ($post['blog_slug'] ?? $post['blog_id'])).'/'.rawurlencode($post['slug']);
            $postTitle = $post['title'] ?? 'Untitled';
            // Excerpts are stored as free text and some carry markup; strip it so
            // cards show prose, not literal tags.
            $postExcerpt = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) ($post['excerpt'] ?? ''))));
            $hasImage = !empty($post['featured_image']);
            ?>
          <article class="lx-gallery-card<?= $hasImage ? '' : ' is-textonly'; ?>">
            <a href="<?= e($postUrl); ?>" class="lx-gallery-media" tabindex="-1" aria-hidden="true">
              <?php if ($hasImage) { ?>
                <img src="<?= e($post['featured_image']); ?>" alt="" loading="lazy">
              <?php } else { ?>
                <span class="lx-gallery-fallback" aria-hidden="true"><?= e(mb_strtoupper(mb_substr(trim($postTitle), 0, 1))); ?></span>
              <?php } ?>
            </a>

            <div class="lx-gallery-body">
              <p class="meta">
                <?= e($post['blog_name'] ?? 'Blog'); ?>
                <?php if (!empty($post['published_at'])) { ?>
                  &middot; <time datetime="<?= e(iso_datetime($post['published_at'] ?? null)); ?>"><?= e(local_datetime($post['published_at'] ?? null, 'M j, Y', site_timezone())); ?></time>
                <?php } ?>
              </p>

              <h3><a href="<?= e($postUrl); ?>"><?= e($postTitle); ?></a></h3>

              <?php if ($postExcerpt !== '') { ?>
                <p class="lx-gallery-excerpt"><?= e(truncate($postExcerpt, 140)); ?></p>
              <?php } ?>
            </div>
          </article>
        <?php } ?>
      </div>
  </section>
  <?php } ?>

{% endblock %}
