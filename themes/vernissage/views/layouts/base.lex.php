<!DOCTYPE html>
<html lang="{{ currentLang }}" {{ isRtl|raw }}>
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{% yield title %}</title>

  <?php
    // SEO/social meta arrives pre-assembled from BlogController: blog defaults
    // on listing pages, post-level overrides on post pages.
    $desc = e($meta['description'] ?? ($user['blog_name'] ?? 'Blog'));
    $ogTitle = e($meta['og_title'] ?? ($meta['title'] ?? ($user['blog_name'] ?? 'Blog')));
    $ogDesc = e($meta['og_description'] ?? ($meta['description'] ?? ''));
    $ogImage = e($meta['og_image'] ?? '');
    $ogUrl = e($meta['url'] ?? '');
  ?>
  <meta name="description" content="<?= $desc ?>" />
  <meta name="author" content="<?= e($user['display_name_cached'] ?? $user['username'] ?? 'Author') ?>" />
  <?php if (!empty($meta['robots'])) { ?><meta name="robots" content="<?= e($meta['robots']) ?>" /><?php } ?>
  <?php if (!empty($meta['canonical'])) { ?><link rel="canonical" href="<?= e($meta['canonical']) ?>" /><?php } ?>

  <meta property="og:type" content="<?= e($meta['og_type'] ?? 'website') ?>" />
  <meta property="og:title" content="<?= $ogTitle ?>" />
  <meta property="og:description" content="<?= $ogDesc ?>" />
  <meta property="og:url" content="<?= $ogUrl ?>" />
  <meta property="og:site_name" content="<?= e($meta['site_name'] ?? ($user['blog_name'] ?? '')) ?>" />
  <?php if ($ogImage !== '') { ?><meta property="og:image" content="<?= $ogImage ?>" /><?php } ?>
  <meta name="twitter:card" content="<?= e($meta['twitter_card'] ?? 'summary_large_image') ?>" />
  <meta name="twitter:title" content="<?= $ogTitle ?>" />
  <meta name="twitter:description" content="<?= $ogDesc ?>" />
  <?php if ($ogImage !== '') { ?><meta name="twitter:image" content="<?= $ogImage ?>" /><?php } ?>

  <?php
    $logo = $settings['logo_path'] ?? null;
  $fav = $settings['favicon_path'] ?? null;
  ?>
  <link rel="shortcut icon" href="<?= $fav ?: '' ?>" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Bodoni+Moda:ital,opsz,wght@0,6..96,400..900;1,6..96,400..900&family=Hanken+Grotesk:ital,wght@0,300..800;1,300..800&family=Source+Serif+4:ital,opsz,wght@0,8..60,300..700;1,8..60,300..700&family=Fragment+Mono:ital@0;1&display=swap" />

  <link rel="stylesheet" href="<?= $asset('css/style.css') ?>">
  {% yield styles %}
</head>
<body>

  <?php if (!empty($flashes ?? [])) { ?>
  <div class="flash-wrap">
    <?php foreach ($flashes as $type => $messages) { ?>
      <?php
        $class = match ($type) {
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info',
        };
        ?>
      <?php foreach ($messages as $message) { ?>
        <div class="alert <?= $class ?>" role="alert"><?= e($message) ?></div>
      <?php } ?>
    <?php } ?>
  </div>
  <?php } ?>

  <?php
    // Private View always points at the landing-page invitation, so the link
    // works from the catalogue and single-work pages too.
    $homeUrl = lurl('/blog/'.urlencode($blog['blog_slug'] ?? ''));
  $archiveUrl = lurl('/blog/'.urlencode($blog['blog_slug'] ?? '').'/archive');
  $subscribeUrl = $homeUrl.'#private-view';
  ?>
  <header class="masthead" role="banner">
    <div class="inner">
      <a href="<?= $homeUrl ?>" class="wordmark" aria-label="<?= e($user['blog_name'] ?? 'Vernissage') ?>, home">
        <?php if ($logo) { ?>
          <img src="<?= e($logo) ?>" alt="<?= e($user['blog_name'] ?? 'Vernissage') ?>" style="max-height:36px;height:auto;width:auto;object-fit:contain;">
        <?php } else { ?>
          <svg class="wordmark-mark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><path d="M12 2l9 10-9 10L3 12z" /></svg>
          <span><?= e($user['blog_name'] ?? 'Vernissage') ?></span>
        <?php } ?>
      </a>

      <nav class="nav" id="primaryNav" aria-label="Primary">
        <a href="<?= $homeUrl ?>">Exhibition</a>
        <a href="<?= $archiveUrl ?>">Catalogue</a>
        <a href="<?= $subscribeUrl ?>">Private View</a>
      </nav>

      <div class="masthead-actions">
        <span data-auth-nav="theme" style="display:contents">
          {% include "partials/_auth_nav.lex.php" %}
        </span>
        <button class="nav-toggle" type="button" aria-controls="primaryNav" aria-expanded="false" aria-label="Open menu">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <main>
    {% yield content %}
  </main>

  <footer class="site">
    <div class="container">
      <div class="foot-ornament" aria-hidden="true">
        <span class="foot-rule"></span>
        <svg viewBox="0 0 24 24" width="11" height="11" fill="currentColor" aria-hidden="true"><path d="M12 2l9 10-9 10L3 12z" /></svg>
        <span class="foot-rule"></span>
      </div>

      <?php
      // Social icons come from blog settings (Appearance > Social Profiles);
      // platforms without a URL simply don't render.
      $socialLinks = is_array($settings['social_links'] ?? null) ? $settings['social_links'] : [];
  $socialIcons = [
      'x' => '<path d="M4 4l16 16M20 4L4 20" />',
      'facebook' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />',
      'instagram' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5" /><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" /><line x1="17.5" y1="6.5" x2="17.51" y2="6.5" />',
      'linkedin' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4V9h4v1a6 6 0 0 1 2-2z" /><rect x="2" y="9" width="4" height="12" /><circle cx="4" cy="4" r="2" />',
      'youtube' => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z" /><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" />',
      'github' => '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22" />',
  ];
  $socialLabels = [
      'x' => 'X (Twitter)',
      'facebook' => 'Facebook',
      'instagram' => 'Instagram',
      'linkedin' => 'LinkedIn',
      'youtube' => 'YouTube',
      'github' => 'GitHub',
  ];
  ?>
      <?php if (!empty($socialLinks)) { ?>
      <nav class="foot-social" aria-label="Social profiles">
        <?php foreach ($socialLinks as $platform => $url) { ?>
          <?php if (empty($socialIcons[$platform])) {
              continue;
          } ?>
          <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($socialLabels[$platform]) ?>" title="<?= e($socialLabels[$platform]) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $socialIcons[$platform] ?></svg>
          </a>
        <?php } ?>
      </nav>
      <?php } ?>

      <div class="foot-display-wrap" aria-hidden="true">
        <div class="foot-display"><?= e($user['blog_name'] ?? 'Vernissage') ?></div>
      </div>

      <p class="foot-hours">Open daily &mdash; admission free</p>

      <div class="foot-bottom">
        <span>&copy; <?= date('Y') ?> <?= e($user['blog_name'] ?? 'Vernissage') ?>.</span>
        <span>Published &amp; hosted by <a href="/"><?= e($_ENV['APP_NAME'] ?? '') ?></a></span>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js"
          integrity="sha384-g4NTh/Iv5PPU4xPyhEWqPcwtNXOvdaDI8LLnyYfyNZOjKJeYQyjzQ9X5275eBjpt"
          crossorigin="anonymous" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/ScrollTrigger.min.js"
          integrity="sha384-Z3REaz79l2IaAZqJsSABtTbhjgOUYyV3p90XNnAPCSHg3EMTz1fouunq9WZRtj3d"
          crossorigin="anonymous" defer></script>

	<script defer src="<?= $asset('js/main.js') ?>"></script>
  {% include "partials/_auth_modal.lex.php" %}
  {% yield scripts %}

</body>
</html>
