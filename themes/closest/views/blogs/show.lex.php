{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'Closest');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerName = e($user['display_name_cached'] ?? $user['handle']);
  $postCount = (int) ($totalPosts ?? count($posts ?? []));
  $featuredPost = !empty($posts) ? $posts[0] : null;
  $gridPosts = !empty($posts) ? array_slice($posts, 1, 6) : [];
  $hasMore = $postCount > 1 + count($gridPosts);

  // Total trail: reading minutes across the landing's posts, and the distance
  // they'd cover at walking pace (5 km/h). The atlas measures reading in km.
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
      <span><?= e($t('closest::home.est', ['year' => !empty($settings['founded_year']) ? (string) $settings['founded_year'] : date('Y')])) ?></span>
      <span><?= e(translate_count('closest::home.waypoints', $postCount)) ?></span>
      <span><?= e($t('closest::home.trail', ['minutes' => $totalMinutes, 'km' => $totalKm])) ?></span>
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
        <span class="yah-label"><?= e($t('closest::home.youAreHere')) ?></span>
      </a>
    </div>

    <div class="th-bottom">
      <p class="th-dek reveal">
        <?= !empty($settings['subtitle']) ? e($settings['subtitle']) : e($t('closest::home.subtitle')) ?>
      </p>
      <?php if (!empty($settings['tagline'])) { ?>
        <span class="th-blaze reveal"><?= e($settings['tagline']) ?></span>
      <?php } ?>
    </div>
  </div>
</section>

<?php if ($featuredPost) {
    $fCover = (string) ($featuredPost['featured_image'] ?? '');
    $fImg = preg_match($validImg, $fCover) ? e($fCover) : $asset('img/cover-placeholder.svg');
    $fUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? ''));
    $fTitle = e($featuredPost['title'] ?? 'Untitled');
    $fExc = e(post_excerpt($featuredPost, 220));
    $fAuthor = author_byline($featuredPost['author_name'], $featuredPost['author_handle'], $featuredPost['author_profile_handle']);
    $fDate = e($fmtDate($featuredPost['published_at'] ?? ''));
    $fCat = e($featuredPost['category'] ?? $t('closest::home.fallbackCategory'));
    $fMinutes = reading_time($featuredPost['content'] ?? '');
    ?>
<section class="nearest" id="nearest">
  <div class="container">
    <div class="nearest-grid">
      <a href="<?= $fUrl ?>" class="inset nearest-inset reveal" aria-hidden="true" tabindex="-1">
        <span class="inset-img"><img src="<?= $fImg ?>" alt="" loading="eager" /></span>
        <span class="inset-cap"><?= translate_html('closest::home.inset', ['category' => $fCat]) ?></span>
      </a>

      <div class="nearest-body">
        <div class="nearest-tag reveal">
          <span class="wp-chip wp-chip--blaze"><?= e($t('closest::home.nearest')) ?></span>
          <span class="eyebrow"><?= e($t('closest::home.distance')) ?></span>
        </div>

        <h2 class="nearest-title reveal"><a href="<?= $fUrl ?>"><?= $fTitle ?></a></h2>

        <?php if ($fExc) { ?>
          <p class="nearest-dek reveal"><?= $fExc ?></p>
        <?php } ?>

        <div class="nearest-byline reveal">
          <?php if ($fAuthor) { ?><span><?= translate_html('closest::by', ['author' => $fAuthor]) ?></span><?php } ?>
          <?php if ($fDate) { ?><span><?= $fDate ?></span><?php } ?>
          <span><?= e($t('closest::minutes', ['count' => $fMinutes])) ?></span>
        </div>

        <a href="<?= $fUrl ?>" class="link-arrow reveal">
          <?= e($t('closest::home.walkNearest')) ?>
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
      <h2 class="route-title reveal"><?= e($t('closest::home.routeTitle')) ?></h2>
      <span class="route-count reveal" id="filter-count"><?= e(translate_count('closest::home.routeCount', count($gridPosts))) ?></span>
    </div>

    <ul class="filter-list reveal" role="tablist" aria-label="<?= e($t('closest::nav.filterByCategory')) ?>"
        data-text="<?= e(json_encode(['openAll' => $t('closest::home.openAll'), 'inCategory' => $t('closest::home.inCategory'), 'viewAll' => $t('closest::home.viewAll'), 'count' => ['one' => $t('closest::home.routeCount.one'), 'other' => $t('closest::home.routeCount.other')]], JSON_UNESCAPED_UNICODE)) ?>">
      <?php $allActive = ($activeCategory ?? '') === ''; ?>
      <li class="<?= $allActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug) ?>" data-filter="" role="tab" aria-selected="<?= $allActive ? 'true' : 'false' ?>"><?= e($t('closest::home.all')) ?></a></li>
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
      <span class="route-count" id="index-footer-count"><?= translate_html('closest::home.plotted', ['shown' => sprintf('%02d', 1 + count($gridPosts)), 'total' => sprintf('%02d', $postCount), 'blog' => $blogTitle]) ?></span>
      <a href="<?= $archiveUrl ?>" class="link-arrow" id="index-footer-link">
        <span id="index-footer-label"><?= e($t('closest::home.openAll')) ?></span>
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
        <h2 class="legend-title reveal"><?= translate_html('closest::home.legendTitle', ['br' => '<br />']) ?></h2>
        <?php $estLabel = !empty($settings['founded_year']) ? (string) $settings['founded_year'] : date('Y'); ?>
        <span class="legend-est reveal"><?= e($t('closest::home.surveyedSince', ['year' => $estLabel])) ?></span>
      </div>

      <div class="legend-body">
        <ul class="legend-key reveal" aria-hidden="true">
          <li><span class="key-sym key-sym--dot"></span> <?= e($t('closest::home.keyDot')) ?></li>
          <li><span class="key-sym key-sym--blaze"></span> <?= e($t('closest::home.keyBlaze')) ?></li>
          <li><span class="key-sym key-sym--dash"></span> <?= e($t('closest::home.keyDash')) ?></li>
        </ul>

        <p class="legend-statement reveal">
          <?php if (!empty($settings['about_text'])) { ?>
            <?= e($settings['about_text']) ?>
          <?php } elseif (!empty($settings['subtitle'])) { ?>
            <?= e($settings['subtitle']) ?>
          <?php } else { ?>
            <?= translate_html('closest::home.aboutDefault', ['blog' => $blogTitle]) ?>
          <?php } ?>
        </p>
        <span class="legend-sign reveal"><?= translate_html('closest::home.sign', ['owner' => $ownerName]) ?></span>

        <p class="legend-fine reveal">
          <?= translate_html('closest::home.fine', ['blog' => $blogTitle, 'platform' => e(env('APP_NAME', ''))]) ?>
        </p>
      </div>
    </div>
  </div>
</section>

<section class="dispatches" id="dispatches">
  <div class="container">
    <div class="dispatch-slab">
      <div class="dispatch-copy">
        <span class="eyebrow reveal"><?= e($t('closest::home.newsEyebrow')) ?></span>
        <h2 class="reveal"><?= !empty($settings['newsletter_heading']) ? e($settings['newsletter_heading']) : e($t('closest::home.newsHeading')) ?></h2>
        <p class="reveal"><?= !empty($settings['newsletter_text']) ? e($settings['newsletter_text']) : e($t('closest::home.newsText')) ?></p>
      </div>

      <form class="dispatch-form reveal" action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post">
        <?= csrf_field_lazy() ?>
        <label for="email-sub"><?= e($t('closest::emailLabel')) ?></label>
        <div class="dispatch-input">
          <input id="email-sub" name="email" type="email" placeholder="reader@basecamp.co"
            value="<?= e(auth()->check() ? (string) (auth()->user()['email'] ?? '') : '') ?>"
            autocomplete="email" maxlength="255" required />
          <button type="submit">
            <?= e($t('closest::home.subscribe')) ?>
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </div>
        <span class="dispatch-fine"><?= e($t('closest::home.newsFine')) ?></span>
      </form>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
	<script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
