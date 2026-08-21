{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'Vernissage');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerName = e($user['display_name_cached'] ?? $user['username'] ?? 'The Curator');
  $postCount = (int) ($totalPosts ?? count($posts ?? []));
  $featuredPost = !empty($posts) ? $posts[0] : null;
  $gridPosts = !empty($posts) ? array_slice($posts, 1, 6) : []; // landing hangs 6; the rest lives on /archive
  $hasMore = $postCount > 1 + count($gridPosts);

  $totalMinutes = 0;
  foreach (($posts ?? []) as $p) {
      $totalMinutes += reading_time($p['content'] ?? '');
  }

  // rooms get roman numerals on their door plaques
  $roman = function (int $n): string {
      $map = [1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD', 100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
      $out = '';
      foreach ($map as $value => $glyph) {
          while ($n >= $value) {
              $out .= $glyph;
              $n -= $value;
          }
      }

      return $out;
  };

  // stored featured_image is sometimes a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';
  ?>

<section class="gallery-hero" id="top">
  <div class="container">
    <div class="hero-meta-top">
      <span><?= $blogTitle ?> &mdash; Season <?= date('Y') ?></span>
      <span>Collection of <?= $postCount ?> Work<?= $postCount === 1 ? '' : 's' ?></span>
      <span><?= $totalMinutes ?> Min of Viewing</span>
    </div>

    <div class="hero-stage">
      <span class="hero-kicker reveal">
        <?= !empty($settings['tagline']) ? e($settings['tagline']) : 'The exhibition is now open' ?>
      </span>
      <h1 class="hero-headline" data-split><?= $blogTitle ?></h1>
      <div class="ornament hero-ornament" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l9 10-9 10L3 12z" /></svg>
      </div>
    </div>

    <div class="hero-bottom">
      <p class="hero-dek reveal">
        <?= !empty($settings['subtitle']) ? e($settings['subtitle']) : 'A permanent collection of essays and dispatches, hung one work at a time.' ?>
      </p>
      <?php if ($featuredPost) { ?>
      <div class="hero-pointer reveal">
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? '')) ?>" class="link-arrow">
          View the centrepiece
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>
      </div>
      <?php } ?>
    </div>
  </div>
</section>

<?php if ($featuredPost) {
    $fCover = (string) ($featuredPost['featured_image'] ?? '');
    $fImg = preg_match($validImg, $fCover) ? e($fCover) : 'https://picsum.photos/seed/vernissage-lead/1400/1050';
    $fUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? ''));
    $fTitle = e($featuredPost['title'] ?? 'Untitled');
    $fExc = e($featuredPost['excerpt'] ?? '');
    $fAuthor = profile_link($featuredPost['author_name'], $featuredPost['author_profile_slug']);
    $fDate = e(local_datetime($featuredPost['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
    $fCat = e($featuredPost['category'] ?? 'Work');
    $fMinutes = reading_time($featuredPost['content'] ?? '');
    ?>
<section class="centrepiece" id="centrepiece">
  <div class="container">
    <div class="centre-grid">
      <a href="<?= $fUrl ?>" class="frame centre-frame reveal" aria-hidden="true" tabindex="-1">
        <span class="frame-img"><img src="<?= $fImg ?>" alt="" loading="eager" /></span>
      </a>

      <div class="centre-body">
        <div class="centre-tag reveal">
          <span class="eyebrow">On view &mdash; <?= $fCat ?></span>
          <?php if ($fDate) { ?><span class="eyebrow"><?= $fDate ?></span><?php } ?>
        </div>

        <h2 class="centre-title reveal"><a href="<?= $fUrl ?>"><?= $fTitle ?></a></h2>

        <?php if ($fExc) { ?>
          <p class="centre-dek reveal"><?= $fExc ?></p>
        <?php } ?>

        <div class="centre-byline reveal">
          <?php if ($fAuthor) { ?><span><?= $fAuthor ?>, <?= date('Y') ?></span><?php } ?>
          <span><?= $fMinutes ?> Min</span>
        </div>

        <a href="<?= $fUrl ?>" class="link-arrow reveal">
          View the work
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>
      </div>
    </div>
  </div>
</section>
<?php } ?>

<?php if (!empty($gridPosts)) {
    $cards = $gridPosts;
    ?>
<section class="collection" id="collection">
  <div class="container">
    <div class="collection-head">
      <h2 class="collection-title reveal">The Collection.</h2>
      <span class="collection-count reveal" id="filter-count"><?= count($gridPosts) ?> work<?= count($gridPosts) === 1 ? '' : 's' ?></span>
    </div>

    <ul class="filter-list reveal" role="tablist" aria-label="Filter works by room">
      <?php $allActive = ($activeCategory ?? '') === ''; ?>
      <li class="<?= $allActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug) ?>" data-filter="" role="tab" aria-selected="<?= $allActive ? 'true' : 'false' ?>">All rooms</a></li>
      <?php foreach (($categories ?? []) as $ci => $c) {
          $cSlug = (string) ($c['slug'] ?? '');
          $catActive = ($activeCategory ?? '') === $cSlug;
          ?>
        <li class="<?= $catActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug.'/category/'.urlencode($cSlug)) ?>" data-filter="<?= e($cSlug) ?>" data-count="<?= (int) ($c['post_count'] ?? 0) ?>" role="tab" aria-selected="<?= $catActive ? 'true' : 'false' ?>"><span class="room-no"><?= $roman($ci + 1) ?></span><?= e($c['name']) ?></a></li>
      <?php } ?>
    </ul>

    <div class="works salon-grid">
      {% include "blogs/_index_cards.lex.php" %}
    </div>

    <?php $archiveUrl = lurl('/blog/'.$blogSlug.'/archive'); ?>
    <div class="collection-footer reveal" id="index-footer" data-archive="<?= $archiveUrl ?>" data-allmore="<?= $hasMore ? '1' : '0' ?>"<?= $hasMore ? '' : ' style="display:none;"' ?>>
      <span class="collection-count" id="index-footer-count">Hanging <?= sprintf('%02d', 1 + count($gridPosts)) ?> of <?= sprintf('%02d', $postCount) ?> &mdash; <?= $blogTitle ?></span>
      <a href="<?= $archiveUrl ?>" class="link-arrow" id="index-footer-link">
        <span id="index-footer-label">Open the catalogue raisonn&eacute;</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>
<?php } ?>

<section class="curator" id="curator">
  <div class="container">
    <div class="curator-grid">
      <?php
      // The label can be a year or a short word; words render smaller so
      // the display size doesn't overflow the column.
      $estLabel = !empty($settings['founded_year']) ? (string) $settings['founded_year'] : date('Y');
  $estLabelClass = mb_strlen($estLabel) > 6 ? ' curator-est--word' : '';
  ?>
      <p class="curator-est<?= $estLabelClass ?> reveal">Est.<br /><?= e($estLabel) ?></p>
      <div class="curator-body">
        <span class="eyebrow reveal">Curator's note &mdash; About this blog</span>
        <p class="curator-statement reveal">
          <?php if (!empty($settings['about_text'])) { ?>
            <?= e($settings['about_text']) ?>
          <?php } elseif (!empty($settings['subtitle'])) { ?>
            <?= e($settings['subtitle']) ?>
          <?php } else { ?>
            <?= $blogTitle ?> &mdash; collected, hung, and kept on permanent view.
          <?php } ?>
        </p>
        <span class="curator-sign reveal">&mdash; <?= $ownerName ?></span>

        <p class="curator-fine reveal">
          <?= $blogTitle ?> is set in Bodoni Moda &amp; Hanken Grotesk,
          hung on plaster #F4F1E9 behind one wall of viridian,
          and published on <?= e($_ENV['APP_NAME'] ?? 'the web') ?>.
        </p>
      </div>
    </div>
  </div>
</section>

<section class="private-view" id="private-view">
  <div class="container">
    <div class="invite-card reveal">
      <div class="invite-inner">
        <span class="eyebrow">The private view</span>
        <h2><?= !empty($settings['newsletter_heading']) ? e($settings['newsletter_heading']) : 'You are cordially invited.' ?></h2>
        <p><?= !empty($settings['newsletter_text']) ? e($settings['newsletter_text']) : 'One invitation per new work. No noise, and you may leave the list at any time.' ?></p>

        <form class="invite-form" action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post">
          <?= csrf_field() ?>
          <label for="email-sub">Your address</label>
          <div class="invite-input">
            <input id="email-sub" name="email" type="email" placeholder="guest@private-view.gallery"
              value="<?= e(auth()->check() ? (string) (auth()->user()['email'] ?? '') : '') ?>"
              autocomplete="email" maxlength="255" required />
            <button type="submit">
              RSVP
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </button>
          </div>
          <span class="invite-fine">Free &mdash; unsubscribe any time</span>
        </form>
      </div>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
	<script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
