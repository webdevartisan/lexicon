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
  ?>
  <meta name="description" content="<?= $desc ?>" />
  <meta name="author" content="<?= e($user['display_name_cached'] ?? $user['username'] ?? 'Author') ?>" />
  <?php if (!empty($meta['robots'])) { ?><meta name="robots" content="<?= e($meta['robots']) ?>" /><?php } ?>
  <?php if (!empty($meta['canonical'])) { ?><link rel="canonical" href="<?= e($meta['canonical']) ?>" /><?php } ?>
  <?php // One alternate per language this page genuinely exists in; a monolingual blog emits none.?>
  <?php foreach (($head['alternates'] ?? []) as $alt) { ?>
  <link rel="alternate" href="<?= e($alt['href']) ?>" hreflang="<?= e($alt['hreflang']) ?>" />
  <?php } ?>
  <?php if (!empty($head['alternates'])) { ?>
  <link rel="alternate" href="<?= e($head['xDefaultUrl']) ?>" hreflang="x-default" />
  <?php } ?>

  <?php /* Shared across every theme so a new tag lands everywhere at once. */ ?>
  <?= social_meta_tags($meta ?? []) ?>

  <?php
    $logo = $settings['logo_path'] ?? null;
  $fav = $settings['favicon_path'] ?? null;
  ?>
  <link rel="shortcut icon" href="<?= $fav ?: '' ?>" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Archivo:ital,wdth,wght@0,62..125,100..900;1,62..125,100..900&family=Schibsted+Grotesk:ital,wght@0,400..900;1,400..900&family=Spline+Sans+Mono:wght@400;500&display=swap" />

  <?php /* Lexicon's own controls, identical on every theme. */ ?>
  <link rel="stylesheet" href="/assets/css/platform-chrome.css">
  <script defer src="/assets/js/platform-menu.js"></script>
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
    // Subscribe always points at the landing-page section, so it works from
    // the archive and post pages too.
    $homeUrl = lurl('/blog/'.urlencode($blog['blog_slug'] ?? ''));
  $archiveUrl = lurl('/blog/'.urlencode($blog['blog_slug'] ?? '').'/archive');
  $subscribeUrl = $homeUrl.'#newsletter';
  ?>
  <header class="masthead" role="banner">
    <div class="inner">
      <a href="<?= $homeUrl ?>" class="wordmark" aria-label="<?= e($user['blog_name'] ?? 'Offset') ?>, home">
        <?php if ($logo) { ?>
          <img src="<?= e($logo) ?>" alt="<?= e($user['blog_name'] ?? 'Offset') ?>" style="max-height:36px;height:auto;width:auto;object-fit:contain;">
        <?php } else { ?>
          <svg class="wordmark-reg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="8" /><path d="M12 1v22M1 12h22" /></svg>
          <span><?= e($user['blog_name'] ?? 'OFFSET') ?></span>
        <?php } ?>
      </a>

      <nav class="nav" id="primaryNav" aria-label="Primary">
        <a href="<?= $homeUrl ?>">Home</a>
        <a href="<?= $archiveUrl ?>">Archive</a>
        <a href="<?= $subscribeUrl ?>">Subscribe</a>
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
    <?php
      // Printers run a control strip on every sheet to check ink coverage;
      // ours doubles as the footer's only decoration.
  ?>
    <div class="color-bar" aria-hidden="true">
      <span style="background:#17191c"></span>
      <span style="background:#2036e6"></span>
      <span style="background:#5a70ff"></span>
      <span style="background:#d6dcf8"></span>
      <span style="background:#00a3c4"></span>
      <span style="background:#d9317e"></span>
      <span style="background:#f2c200"></span>
      <span style="background:#676d74"></span>
    </div>

    <div class="container">
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
        <div class="foot-display"><?= e($user['blog_name'] ?? 'OFFSET') ?></div>
      </div>

      <div class="foot-bottom">
        <span>&copy; <?= date('Y') ?> <?= e($user['blog_name'] ?? 'Offset') ?>.</span>
        <span>Published &amp; hosted by <a href="/"><?= e($_ENV['APP_NAME'] ?? '') ?></a></span>
        {% include "partials/_language_switcher.lex.php" %}
      </div>
    </div>
  </footer>

  <script src="/vendor/gsap/gsap.min.js" defer></script>
  <script src="/vendor/gsap/ScrollTrigger.min.js" defer></script>

	<script defer src="<?= $asset('js/main.js') ?>"></script>
  {% include "partials/_auth_modal.lex.php" %}
  {% yield scripts %}

<script src="/assets/js/lang-switcher.js" defer></script>
</body>
</html>
