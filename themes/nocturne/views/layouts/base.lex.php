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
  $subscribeUrl = $homeUrl.'#nightletter';
  ?>
  <header class="masthead" role="banner" lang="<?= e($chromeLang) ?>" dir="<?= e($chromeDir) ?>">
    <div class="inner">
      <a href="<?= $homeUrl ?>" class="wordmark" dir="auto" aria-label="<?= e($t('nocturne::nav.homeOf', ['blog' => $user['blog_name'] ?? 'Nocturne'])) ?>">
        <?php if ($logo) { ?>
          <img src="<?= e($logo) ?>" alt="<?= e($user['blog_name'] ?? 'Nocturne') ?>" style="max-height:38px;height:auto;width:auto;object-fit:contain;">
        <?php } else { ?>
          <span class="wordmark-dot" aria-hidden="true"></span><span class="wordmark-name"><?= e($user['blog_name'] ?? 'Nocturne') ?></span>
        <?php } ?>
      </a>

      <nav class="nav" id="primaryNav" aria-label="<?= e($t('nocturne::nav.primary')) ?>">
        <a href="<?= $homeUrl ?>"><?= e($t('nocturne::nav.home')) ?></a>
        <a href="<?= $archiveUrl ?>"><?= e($t('nocturne::nav.archive')) ?></a>
        <a href="<?= $subscribeUrl ?>"><?= e($t('nocturne::nav.subscribe')) ?></a>
      </nav>

      <div class="masthead-actions">
        {% include "partials/_auth_nav.lex.php" %}
        <button class="nav-toggle" type="button" aria-controls="primaryNav" aria-expanded="false" aria-label="<?= e($t('nocturne::nav.openMenu')) ?>">
          <span></span><span></span><span></span>
        </button>
      </div>
    </div>
  </header>

  <main>
    {% yield content %}
  </main>

  {% include "partials/_popular_posts.lex.php" %}

  <footer class="site" lang="<?= e($chromeLang) ?>" dir="<?= e($chromeDir) ?>">
    <div class="container">
      <div class="foot-lamp" aria-hidden="true"></div>

      <div class="foot-top">
        <p class="foot-note"><?= translate_html('nocturne::footer.note', ['br' => '<br />']) ?></p>

        {% include "partials/public/_theme_social_links.lex.php" %}
      </div>

      <div class="foot-display-wrap" aria-hidden="true">
        <div class="foot-display" dir="auto"><?= e($user['blog_name'] ?? 'Nocturne') ?></div>
      </div>

      <div class="foot-bottom">
        <span dir="auto">&copy; <?= date('Y') ?> <?= e($user['blog_name'] ?? 'Nocturne') ?>.</span>
        <span><?= translate_html('blogPage.footer.hostedBy', ['platform' => '<a href="/">'.e(env('APP_NAME', '')).'</a>']) ?></span>
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
