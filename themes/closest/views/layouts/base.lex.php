<!DOCTYPE html>
<html lang="{{ currentLang }}" {{ isRtl|raw }}>
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{% yield title %}</title>

  {% include "partials/public/_theme_head.lex.php" %}
  {% yield styles %}
</head>
<body>
{% include "partials/_impersonation_banner.lex.php" %}

  {% include "partials/public/_theme_flashes.lex.php" %}

  <?php
    // Subscribe points at the landing-page section so it works from the
    // archive and post pages too.
    $homeUrl = lurl('/blog/'.urlencode($blog['blog_slug'] ?? ''));
  $archiveUrl = lurl('/blog/'.urlencode($blog['blog_slug'] ?? '').'/archive');
  $subscribeUrl = $homeUrl.'#dispatches';
  ?>
  <header class="masthead" role="banner">
    <div class="inner">
      <a href="<?= $homeUrl ?>" class="wordmark" aria-label="<?= e($user['blog_name'] ?? 'Closest') ?>, home">
        <?php if ($logo) { ?>
          <img src="<?= e($logo) ?>" alt="<?= e($user['blog_name'] ?? 'Closest') ?>" style="max-height:36px;height:auto;width:auto;object-fit:contain;">
        <?php } else { ?>
          <svg class="wordmark-pin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8.5" /><circle cx="12" cy="12" r="2.4" fill="currentColor" stroke="none" /></svg>
          <span><?= e($user['blog_name'] ?? 'Closest') ?></span>
        <?php } ?>
      </a>

      <nav class="nav" id="primaryNav" aria-label="Primary">
        <a href="<?= $homeUrl ?>">Trailhead</a>
        <a href="<?= $archiveUrl ?>">Route log</a>
        <a href="<?= $subscribeUrl ?>">Dispatches</a>
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

  {% include "partials/_popular_posts.lex.php" %}

  <?php
    // The bottom margin of a survey sheet: scale bar, sheet reference, big
    // sheet title. The grid ref is a stable hash of the slug, pure decoration.
    $gridRef = strtoupper(substr(dechex(crc32((string) ($blog['blog_slug'] ?? 'closest'))), 0, 4));
  ?>
  <footer class="sheet-margin">
    <div class="container">
      <div class="sheet-row" aria-hidden="true">
        <span class="scale-bar">
          <span></span><span></span><span></span><span></span><span></span><span></span>
        </span>
        <span class="scale-cap">0&ensp;&mdash;&ensp;5 km &middot; sheet <?= date('Y') ?> &middot; grid <?= $gridRef ?></span>
        <svg class="declination" viewBox="0 0 24 40" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M12 36V8" /><path d="M8 12l4-6 4 6" /><path d="M12 3l1.2 2.4-1.2-.7-1.2.7z" fill="currentColor" stroke="none" /></svg>
      </div>

      {% include "partials/public/_theme_social_links.lex.php" %}

      <div class="foot-sheet-wrap" aria-hidden="true">
        <div class="foot-sheet"><?= e($user['blog_name'] ?? 'Closest') ?></div>
      </div>

      <div class="foot-bottom">
        <span>&copy; <?= date('Y') ?> <?= e($user['blog_name'] ?? 'Closest') ?>.</span>
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

  {% include "partials/public/_theme_scripts.lex.php" %}
</body>
</html>
