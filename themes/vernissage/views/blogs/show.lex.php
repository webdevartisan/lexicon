{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'Vernissage');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerName = e($user['display_name_cached'] ?? $user['handle']);
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
      <span><?= translate_html('vernissage::home.season', ['blog' => $blogTitle, 'year' => date('Y')]) ?></span>
      <span><?= e(translate_count('vernissage::home.collection', $postCount)) ?></span>
      <span><?= e($t('vernissage::home.viewingTotal', ['count' => $totalMinutes])) ?></span>
    </div>

    <div class="hero-stage">
      <span class="hero-kicker reveal">
        <?= !empty($settings['tagline']) ? e($settings['tagline']) : e($t('vernissage::home.tagline')) ?>
      </span>
      <h1 class="hero-headline" data-split dir="auto"><?= $blogTitle ?></h1>
      <div class="ornament hero-ornament" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l9 10-9 10L3 12z" /></svg>
      </div>
    </div>

    <div class="hero-bottom">
      <p class="hero-dek reveal">
        <?= !empty($settings['subtitle']) ? e($settings['subtitle']) : e($t('vernissage::home.subtitle')) ?>
      </p>
      <?php if ($featuredPost) { ?>
      <div class="hero-pointer reveal">
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? '')) ?>" class="link-arrow">
          <?= e($t('vernissage::home.viewCentrepiece')) ?>
          <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>
      </div>
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
    $fDate = e(local_datetime($featuredPost['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
    $fCat = e($featuredPost['category'] ?? $t('vernissage::home.fallbackCategory'));
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
          <span class="eyebrow"><?= translate_html('vernissage::home.onView', ['category' => $fCat]) ?></span>
          <?php if ($fDate) { ?><span class="eyebrow"><?= $fDate ?></span><?php } ?>
        </div>

        <h2 class="centre-title reveal"><a href="<?= $fUrl ?>"><?= $fTitle ?></a></h2>

        <?php if ($fExc) { ?>
          <p class="centre-dek reveal"><?= $fExc ?></p>
        <?php } ?>

        <div class="centre-byline reveal">
          <?php if ($fAuthor) { ?><span><?= $fAuthor ?>, <?= date('Y') ?></span><?php } ?>
          <span><?= e($t('vernissage::minutes', ['count' => $fMinutes])) ?></span>
        </div>

        <a href="<?= $fUrl ?>" class="link-arrow reveal">
          <?= e($t('vernissage::home.viewWork')) ?>
          <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
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
      <h2 class="collection-title reveal"><?= e($t('vernissage::home.collectionTitle')) ?></h2>
      <span class="collection-count reveal" id="filter-count"><?= e(translate_count('vernissage::home.workCount', count($gridPosts))) ?></span>
    </div>

    <ul class="filter-list reveal" role="tablist" aria-label="<?= e($t('vernissage::home.filter')) ?>"
        data-text="<?= e(json_encode(['openAll' => $t('vernissage::home.openAll'), 'inCategory' => $t('vernissage::home.inCategory'), 'viewAll' => $t('vernissage::home.viewAll'), 'count' => ['one' => $t('vernissage::home.workCount.one'), 'other' => $t('vernissage::home.workCount.other')]], JSON_UNESCAPED_UNICODE)) ?>">
      <?php $allActive = ($activeCategory ?? '') === ''; ?>
      <li class="<?= $allActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug) ?>" data-filter="" role="tab" aria-selected="<?= $allActive ? 'true' : 'false' ?>"><?= e($t('vernissage::home.all')) ?></a></li>
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
      <span class="collection-count" id="index-footer-count"><?= translate_html('vernissage::home.hanging', ['shown' => sprintf('%02d', 1 + count($gridPosts)), 'total' => sprintf('%02d', $postCount), 'blog' => $blogTitle]) ?></span>
      <a href="<?= $archiveUrl ?>" class="link-arrow" id="index-footer-link">
        <span id="index-footer-label"><?= e($t('vernissage::home.openAll')) ?></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
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
      <p class="curator-est<?= $estLabelClass ?> reveal"><?= e($t('vernissage::home.est')) ?><br /><?= e($estLabel) ?></p>
      <div class="curator-body">
        <span class="eyebrow reveal"><?= e($t('vernissage::home.curatorEyebrow')) ?></span>
        <p class="curator-statement reveal">
          <?php if (!empty($settings['about_text'])) { ?>
            <?= e($settings['about_text']) ?>
          <?php } elseif (!empty($settings['subtitle'])) { ?>
            <?= e($settings['subtitle']) ?>
          <?php } else { ?>
            <?= translate_html('vernissage::home.curatorDefault', ['blog' => $blogTitle]) ?>
          <?php } ?>
        </p>
        <span class="curator-sign reveal"><?= $ownerName ?></span>

        <p class="curator-fine reveal">
          <?= translate_html('vernissage::home.fine', ['blog' => $blogTitle, 'platform' => e(env('APP_NAME', ''))]) ?>
        </p>
      </div>
    </div>
  </div>
</section>

<section class="private-view" id="private-view">
  <div class="container">
    <div class="invite-card reveal">
      <div class="invite-inner">
        <span class="eyebrow"><?= e($t('vernissage::home.newsEyebrow')) ?></span>
        <h2><?= !empty($settings['newsletter_heading']) ? e($settings['newsletter_heading']) : e($t('vernissage::home.newsHeading')) ?></h2>
        <p><?= !empty($settings['newsletter_text']) ? e($settings['newsletter_text']) : e($t('vernissage::home.newsText')) ?></p>

        <form class="invite-form" action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post">
          <?= csrf_field_lazy() ?>
          <label for="email-sub"><?= e($t('vernissage::emailLabel')) ?></label>
          <div class="invite-input">
            <input id="email-sub" name="email" type="email" placeholder="guest@private-view.gallery"
              value="<?= e(auth()->check() ? (string) (auth()->user()['email'] ?? '') : '') ?>"
              autocomplete="email" maxlength="255" required />
            <button type="submit">
              <?= e($t('vernissage::home.subscribe')) ?>
              <svg class="arrow" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </button>
          </div>
          <span class="invite-fine"><?= e($t('vernissage::home.newsFine')) ?></span>
        </form>
      </div>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
	<script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
