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
    // Subscribe always points at the landing-page section, so it works from
    // the archive and post pages too.
    $homeUrl = lurl('/blog/'.urlencode($blog['blog_slug'] ?? ''));
  $archiveUrl = lurl('/blog/'.urlencode($blog['blog_slug'] ?? '').'/archive');
  $subscribeUrl = $homeUrl.'#newsletter';
  ?>
  <header class="masthead" role="banner">
    <div class="inner">
      <a href="<?= $homeUrl ?>" class="wordmark" aria-label="<?= e($user['blog_name'] ?? 'Folio') ?>, home">
        <?php if ($logo) { ?>
          <img src="<?= e($logo) ?>" alt="<?= e($user['blog_name'] ?? 'Folio') ?>" style="max-height:40px;height:auto;width:auto;object-fit:contain;filter:none;">
        <?php } else { ?>
          <?= e($user['blog_name'] ?? 'FOLIO') ?>
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

  {% include "partials/_popular_posts.lex.php" %}

  <footer class="site">
    <div class="container">
      {% include "partials/public/_theme_social_links.lex.php" %}

      <div class="foot-display-wrap" aria-hidden="true">
        <div class="foot-display"><?= e($user['blog_name'] ?? 'FOLIO') ?></div>
      </div>

      <div class="foot-bottom">
        <span>&copy; <?= date('Y') ?> <?= e($user['blog_name'] ?? 'Folio') ?>.</span>
        <span>Published &amp; hosted by <a href="/"><?= e(env('APP_NAME', '')) ?></a></span>
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
