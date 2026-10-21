  <?php
    // SEO/social meta arrives pre-assembled from BlogController: blog defaults
    // on listing pages, post-level overrides on post pages.
    $desc = e($meta['description'] ?? ($user['blog_name'] ?? 'Blog'));
  ?>
  <meta name="description" content="<?= $desc ?>" />
  <meta name="author" content="<?= e($user['display_name_cached'] ?? $user['handle']) ?>" />
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
  <?= structured_data_tags($meta ?? []) ?>
  <?php if (!empty($blog['blog_slug'])) { ?>
  <link rel="alternate" type="application/rss+xml" title="<?= e($blog['blog_name'] ?? '') ?>" href="<?= e(rtrim(base_url(), '/').lurl('/blog/'.rawurlencode($blog['blog_slug']).'/feed.xml')) ?>" />
  <?php } ?>

  <?php
    $logo = $settings['logo_path'] ?? null;
  $fav = $settings['favicon_path'] ?? null;
  ?>
  <?php if (!empty($fav)) { ?>
  <link rel="icon" href="<?= e($fav) ?>" />
  <?php } else { ?>
  {% include "partials/_favicons.lex.php" %}
  <?php } ?>

  <link rel="stylesheet" href="<?= $asset('css/fonts.css') ?>">

  <?php /* Lexicon's own controls, identical on every theme. */ ?>
  <link rel="stylesheet" href="<?= e(versioned_asset('/assets/css/platform-chrome.css')) ?>">
  <script src="<?= e(versioned_asset('/assets/js/appearance.js')) ?>"></script>
  <script defer src="<?= e(versioned_asset('/assets/js/platform-menu.js')) ?>"></script>
  <link rel="stylesheet" href="<?= $asset('css/style.css') ?>">
