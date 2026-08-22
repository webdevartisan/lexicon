{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'Closest');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerName = e($user['display_name_cached'] ?? $user['username'] ?? 'The Surveyor');
  $postCount = (int) ($totalPosts ?? count($posts ?? []));
  $featuredPost = !empty($posts) ? $posts[0] : null;
  $gridPosts = !empty($posts) ? array_slice($posts, 1, 6) : [];
  $hasMore = $postCount > 1 + count($gridPosts);

  // Total trail: reading minutes across the landing's posts, and the distance
  // they'd cover at walking pace (5 km/h) — the atlas measures reading in km.
  $totalMinutes = 0;
  foreach (($posts ?? []) as $p) {
      $totalMinutes += reading_time($p['content'] ?? '');
  }
  $totalKm = round($totalMinutes * 5 / 60, 1);

  // stored featured_image is sometimes a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';

  $blogTz = blog_timezone((int) ($blog['id'] ?? 0));
  $fmtDate = static fn ($raw): string => local_datetime($raw, 'j M Y', $blogTz);
  ?>

<section class="trailhead" id="top">
  <div class="container">
    <div class="th-meta">
      <span>Est. <?= e(!empty($settings['founded_year']) ? (string) $settings['founded_year'] : date('Y')) ?> &middot; Field atlas</span>
      <span><?= $postCount ?> Waypoint<?= $postCount === 1 ? '' : 's' ?></span>
      <span><?= $totalMinutes ?> Min &asymp; <?= $totalKm ?> km of trail</span>
    </div>

    <h1 class="th-name" data-split><?= $blogTitle ?></h1>

    <div class="th-route" aria-hidden="true">
      <svg viewBox="0 0 1200 150" preserveAspectRatio="none" focusable="false">
        <path class="route-ahead" d="M 10 95 C 180 40, 320 130, 480 88 S 760 30, 940 74 S 1130 118, 1260 60" />
        <path class="route-walked" id="route-walked" pathLength="1" d="M 10 95 C 180 40, 320 130, 480 88 S 760 30, 940 74" style="stroke-dashoffset:0;" />
        <circle class="route-start" cx="10" cy="95" r="5" />
      </svg>
      <a class="yah" id="yah" href="#nearest" tabindex="-1">
        <span class="yah-pin"><span></span></span>
        <span class="yah-label">You are here</span>
      </a>
    </div>

    <div class="th-bottom">
      <p class="th-dek reveal">
        <?= !empty($settings['subtitle']) ? e($settings['subtitle']) : 'Notes from the field, plotted one waypoint at a time.' ?>
      </p>
      <?php if (!empty($settings['tagline'])) { ?>
        <span class="th-blaze reveal"><?= e($settings['tagline']) ?></span>
      <?php } ?>
    </div>
  </div>
</section>

<?php if ($featuredPost) {
    $fCover = (string) ($featuredPost['featured_image'] ?? '');
    $fImg = preg_match($validImg, $fCover) ? e($fCover) : 'https://picsum.photos/seed/closest-near/1400/1000';
    $fUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? ''));
    $fTitle = e($featuredPost['title'] ?? 'Untitled');
    $fExc = e($featuredPost['excerpt'] ?? '');
    $fAuthor = profile_link($featuredPost['author_name'], $featuredPost['author_profile_slug']);
    $fDate = e($fmtDate($featuredPost['published_at'] ?? ''));
    $fCat = e($featuredPost['category'] ?? 'Field note');
    $fMinutes = reading_time($featuredPost['content'] ?? '');
    ?>
<section class="nearest" id="nearest">
  <div class="container">
    <div class="nearest-grid">
      <a href="<?= $fUrl ?>" class="inset nearest-inset reveal" aria-hidden="true" tabindex="-1">
        <span class="inset-img"><img src="<?= $fImg ?>" alt="" loading="eager" /></span>
        <span class="inset-cap">Inset W-01 &middot; <?= $fCat ?></span>
      </a>

      <div class="nearest-body">
        <div class="nearest-tag reveal">
          <span class="wp-chip wp-chip--blaze">W-01 &middot; Nearest</span>
          <span class="eyebrow">0.0 km from here</span>
        </div>

        <h2 class="nearest-title reveal"><a href="<?= $fUrl ?>"><?= $fTitle ?></a></h2>

        <?php if ($fExc) { ?>
          <p class="nearest-dek reveal"><?= $fExc ?></p>
        <?php } ?>

        <div class="nearest-byline reveal">
          <?php if ($fAuthor) { ?><span>By <?= $fAuthor ?></span><?php } ?>
          <?php if ($fDate) { ?><span><?= $fDate ?></span><?php } ?>
          <span><?= $fMinutes ?> Min</span>
        </div>

        <a href="<?= $fUrl ?>" class="link-arrow reveal">
          Walk to the nearest post
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
<section class="route" id="route">
  <div class="container">
    <div class="route-head">
      <h2 class="route-title reveal">The route.</h2>
      <span class="route-count reveal" id="filter-count"><?= count($gridPosts) ?> waypoint<?= count($gridPosts) === 1 ? '' : 's' ?></span>
    </div>

    <ul class="filter-list reveal" role="tablist" aria-label="Filter posts by category">
      <?php $allActive = ($activeCategory ?? '') === ''; ?>
      <li class="<?= $allActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug) ?>" data-filter="" role="tab" aria-selected="<?= $allActive ? 'true' : 'false' ?>">Full route</a></li>
      <?php foreach (($categories ?? []) as $c) {
          $cSlug = (string) ($c['slug'] ?? '');
          $catActive = ($activeCategory ?? '') === $cSlug;
          ?>
        <li class="<?= $catActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug.'/category/'.urlencode($cSlug)) ?>" data-filter="<?= e($cSlug) ?>" data-count="<?= (int) ($c['post_count'] ?? 0) ?>" role="tab" aria-selected="<?= $catActive ? 'true' : 'false' ?>"><?= e($c['name']) ?></a></li>
      <?php } ?>
    </ul>

    <div class="route-wrap">
      <div class="route-rail" aria-hidden="true"><span id="route-rail-fill"></span></div>
      <div class="index-rows">
        {% include "blogs/_index_cards.lex.php" %}
      </div>
    </div>

    <?php $archiveUrl = lurl('/blog/'.$blogSlug.'/archive'); ?>
    <div class="route-footer reveal" id="index-footer" data-archive="<?= $archiveUrl ?>" data-allmore="<?= $hasMore ? '1' : '0' ?>"<?= $hasMore ? '' : ' style="display:none;"' ?>>
      <span class="route-count" id="index-footer-count">Waypoints <?= sprintf('%02d', 1 + count($gridPosts)) ?> of <?= sprintf('%02d', $postCount) ?> plotted &mdash; <?= $blogTitle ?></span>
      <a href="<?= $archiveUrl ?>" class="link-arrow" id="index-footer-link">
        <span id="index-footer-label">Follow the full route</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>
<?php } ?>

<section class="legend" id="legend">
  <div class="container">
    <div class="legend-grid">
      <div class="legend-side">
        <h2 class="legend-title reveal">The<br />legend.</h2>
        <?php $estLabel = !empty($settings['founded_year']) ? (string) $settings['founded_year'] : date('Y'); ?>
        <span class="legend-est reveal">Surveyed since <?= e($estLabel) ?></span>
      </div>

      <div class="legend-body">
        <ul class="legend-key reveal" aria-hidden="true">
          <li><span class="key-sym key-sym--dot"></span> One published waypoint</li>
          <li><span class="key-sym key-sym--blaze"></span> A blaze &mdash; marks the active leg</li>
          <li><span class="key-sym key-sym--dash"></span> Trail ahead, not yet written</li>
        </ul>

        <p class="legend-statement reveal">
          <?php if (!empty($settings['about_text'])) { ?>
            <?= e($settings['about_text']) ?>
          <?php } elseif (!empty($settings['subtitle'])) { ?>
            <?= e($settings['subtitle']) ?>
          <?php } else { ?>
            <?= $blogTitle ?> &mdash; surveyed on foot, plotted in words, published waypoint by waypoint.
          <?php } ?>
        </p>
        <span class="legend-sign reveal">&mdash; <?= $ownerName ?>, surveyor</span>

        <p class="legend-fine reveal">
          <?= $blogTitle ?> is set in Besley &amp; Barlow, drawn on chart paper #F3F5EE
          with one blaze of trail orange, and published on <?= e($_ENV['APP_NAME'] ?? 'the web') ?>.
        </p>
      </div>
    </div>
  </div>
</section>

<section class="dispatches" id="dispatches">
  <div class="container">
    <div class="dispatch-slab">
      <div class="dispatch-copy">
        <span class="eyebrow reveal">Field dispatches</span>
        <h2 class="reveal"><?= !empty($settings['newsletter_heading']) ? e($settings['newsletter_heading']) : 'Every new waypoint, sent from the trail.' ?></h2>
        <p class="reveal"><?= !empty($settings['newsletter_text']) ? e($settings['newsletter_text']) : 'One email per post. No tracking, easy unsubscribe.' ?></p>
      </div>

      <form class="dispatch-form reveal" action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post">
        <?= csrf_field() ?>
        <label for="email-sub">Your address</label>
        <div class="dispatch-input">
          <input id="email-sub" name="email" type="email" placeholder="reader@basecamp.co"
            value="<?= e(auth()->check() ? (string) (auth()->user()['email'] ?? '') : '') ?>"
            autocomplete="email" maxlength="255" required />
          <button type="submit">
            Subscribe
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </div>
        <span class="dispatch-fine">Free &mdash; unsubscribe at any waypoint</span>
      </form>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
	<script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
