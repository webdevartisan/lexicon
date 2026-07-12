<!DOCTYPE html>
<html lang="{{ currentLang }}" {{ isRtl|raw }}>
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{% yield title %}</title>

  <?php
    $desc = e($meta['description'] ?? ($user['blog_name'] ?? 'Blog'));
  ?>
  <meta name="description" content="<?= $desc ?>" />
  <meta name="keywords" content="blog, posts" />
  <meta name="author" content="<?= e($user['display_name_cached'] ?? $user['username'] ?? 'Author') ?>" />

  <meta property="og:title" content="" />
  <meta property="og:image" content="" />
  <meta property="og:url" content="" />
  <meta property="og:site_name" content="" />
  <meta property="og:description" content="" />
  <meta name="twitter:title" content="" />
  <meta name="twitter:image" content="" />
  <meta name="twitter:url" content="" />
  <meta name="twitter:card" content="" />

  <?php
    $logo = $settings['logo_path'] ?? null;
  $fav = $settings['favicon_path'] ?? null;
  ?>
  <link rel="shortcut icon" href="<?= $fav ?: '' ?>" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300..900;1,9..144,300..600&family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" />

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

  <header class="masthead" role="banner">
    <div class="inner">
      <a href="<?= lurl('/blog/'.urlencode($blog['blog_slug'] ?? '')) ?>" class="wordmark" aria-label="<?= e($user['blog_name'] ?? 'Folio') ?>, home">
        <?php if ($logo) { ?>
          <img src="<?= e($logo) ?>" alt="<?= e($user['blog_name'] ?? 'Folio') ?>" style="max-height:40px;height:auto;width:auto;object-fit:contain;filter:none;">
        <?php } else { ?>
          <?= e($user['blog_name'] ?? 'FOLIO') ?>
        <?php } ?>
      </a>

      <nav class="nav" id="primaryNav" aria-label="Primary">
        <a href="<?= lurl('/blog/'.urlencode($blog['blog_slug'] ?? '')) ?>">Home</a>
        <a href="#newsletter">Subscribe</a>
      </nav>

      <div class="masthead-actions">
        <a href="#newsletter" class="nav-subscribe">Subscribe</a>
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
      <div class="foot-grid">
        <div class="foot-mark">
          <a href="<?= lurl('/blog/'.urlencode($blog['blog_slug'] ?? '')) ?>" class="wordmark"><?= e($user['blog_name'] ?? 'FOLIO') ?></a>
          <?php if (!empty($settings['subtitle'])) { ?>
            <p><?= e($settings['subtitle']) ?></p>
          <?php } ?>
        </div>

        <nav class="foot-nav" aria-label="Footer">
          <a href="<?= lurl('/blog/'.urlencode($blog['blog_slug'] ?? '')) ?>">Home</a>
          <a href="<?= lurl('/blog/'.urlencode($blog['blog_slug'] ?? '').'/archive') ?>">Archive</a>
          <a href="#newsletter">Subscribe</a>
          <a href="<?= lurl('/') ?>"><?= e(env('APP_NAME', 'Home')) ?></a>
        </nav>
      </div>

      <div class="foot-display-wrap" aria-hidden="true">
        <div class="foot-display"><?= e($user['blog_name'] ?? 'FOLIO') ?></div>
      </div>

      <div class="foot-bottom">
        <span>&copy; <?= date('Y') ?> <?= e($user['blog_name'] ?? 'Folio') ?>.</span>
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
  {% yield scripts %}

</body>
</html>
