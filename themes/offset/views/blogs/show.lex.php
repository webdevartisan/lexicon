{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'OFFSET');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerName = e($user['display_name_cached'] ?? $user['handle']);
  $postCount = (int) ($totalPosts ?? count($posts ?? []));
  $featuredPost = !empty($posts) ? $posts[0] : null;
  $gridPosts = !empty($posts) ? array_slice($posts, 1, 6) : []; // landing lists 6; the rest lives on /archive
  $hasMore = $postCount > 1 + count($gridPosts);

  $totalMinutes = 0;
  foreach (($posts ?? []) as $p) {
      $totalMinutes += reading_time($p['content'] ?? '');
  }

  // stored featured_image is sometimes a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';
  ?>

<section class="press-hero" id="top">
  <div class="container">
    <div class="hero-meta-top">
      <span><?= translate_html('offset::home.volume', ['blog' => $blogTitle, 'year' => date('Y')]) ?></span>
      <span><?= e(translate_count('offset::home.edition', $postCount)) ?></span>
      <span><?= e($t('offset::home.readingTotal', ['count' => $totalMinutes])) ?></span>
    </div>

    <div class="hero-stage">
      <h1 class="hero-headline" data-split dir="auto"><?= $blogTitle ?></h1>
      <span class="proof-stamp" aria-hidden="true"<?= edit_text_attrs('tagline', $t('offset::home.tagline')) ?>><?= !empty($settings['tagline']) ? e($settings['tagline']) : e($t('offset::home.tagline')) ?></span>
    </div>

    <div class="hero-bottom">
      <p class="hero-dek reveal"<?= edit_text_attrs('subtitle', $t('offset::home.subtitle')) ?>><?= !empty($settings['subtitle']) ? e($settings['subtitle']) : e($t('offset::home.subtitle')) ?></p>
      <?php if ($featuredPost) { ?>
      <div class="hero-pointer reveal">
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? '')) ?>" class="link-arrow">
          <span<?= $wording->attrs('offset::home.readLead') ?>><?= $wording->html('offset::home.readLead') ?></span>
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
    $fCat = e($featuredPost['category'] ?? $t('offset::home.fallbackCategory'));
    $fMinutes = reading_time($featuredPost['content'] ?? '');
    ?>
<section class="lead" id="lead">
  <div class="container">
    <div class="lead-grid">
      <a href="<?= $fUrl ?>" class="plate lead-plate reveal" aria-hidden="true" tabindex="-1">
        <span class="plate-img"><img src="<?= $fImg ?>" alt="" loading="eager" /></span>
      </a>

      <div class="lead-body">
        <div class="lead-tag reveal">
          <span class="eyebrow"><?php $leadStoryLine = '<span'.$wording->attrs('offset::home.leadStoryLine').'>'.$wording->html('offset::home.leadStoryLine').'</span>'; ?><?= translate_html('offset::home.leadStory', ['line' => $leadStoryLine, 'category' => $fCat]) ?></span>
          <?php if ($fDate) { ?><span class="eyebrow"><?= $fDate ?></span><?php } ?>
        </div>

        <h2 class="lead-title reveal"><a href="<?= $fUrl ?>"><?= $fTitle ?></a></h2>

        <?php if ($fExc) { ?>
          <p class="lead-dek reveal"><?= $fExc ?></p>
        <?php } ?>

        <div class="lead-byline reveal">
          <?php if ($fAuthor) { ?><span><?= translate_html('offset::by', ['author' => $fAuthor]) ?></span><?php } ?>
          <span><?= e($t('offset::minutes', ['count' => $fMinutes])) ?></span>
        </div>

        <a href="<?= $fUrl ?>" class="link-arrow reveal">
          <span<?= $wording->attrs('offset::home.readPost') ?>><?= $wording->html('offset::home.readPost') ?></span>
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
<section class="index" id="index">
  <div class="container">
    <div class="index-head">
      <h2 class="index-title reveal"<?= $wording->attrs('offset::home.indexTitle') ?>><?= $wording->html('offset::home.indexTitle') ?></h2>
      <span class="index-count reveal" id="filter-count"><?= e(translate_count('offset::home.indexCount', count($gridPosts))) ?></span>
    </div>

    <ul class="filter-list reveal" role="tablist" aria-label="<?= e($t('offset::nav.filterByCategory')) ?>"
        data-text="<?= e(json_encode(['openAll' => $t('offset::home.openAll'), 'inCategory' => $t('offset::home.inCategory'), 'viewAll' => $t('offset::home.viewAll'), 'count' => ['one' => $t('offset::home.indexCount.one'), 'other' => $t('offset::home.indexCount.other')]], JSON_UNESCAPED_UNICODE)) ?>">
      <?php $allActive = ($activeCategory ?? '') === ''; ?>
      <li class="<?= $allActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug) ?>" data-filter="" role="tab" aria-selected="<?= $allActive ? 'true' : 'false' ?>"><?= e($t('offset::home.all')) ?></a></li>
      <?php foreach (($categories ?? []) as $c) {
          $cSlug = (string) ($c['slug'] ?? '');
          $catActive = ($activeCategory ?? '') === $cSlug;
          ?>
        <li class="<?= $catActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug.'/category/'.urlencode($cSlug)) ?>" data-filter="<?= e($cSlug) ?>" data-count="<?= (int) ($c['post_count'] ?? 0) ?>" role="tab" aria-selected="<?= $catActive ? 'true' : 'false' ?>"><?= e($c['name']) ?></a></li>
      <?php } ?>
    </ul>

    <div class="index-wrap">
      <div class="index-rows articles-grid">
        {% include "blogs/_index_cards.lex.php" %}
      </div>

      <?php
        // The light table: a pinned proofing pane that previews whichever row
        // the cursor rests on. Desktop only; rows carry their own thumbs on mobile.
        $firstCover = '';
    $firstCap = '';
    if (!empty($gridPosts)) {
        $c0 = (string) ($gridPosts[0]['featured_image'] ?? '');
        $firstCover = preg_match($validImg, $c0) ? $c0 : $asset('img/cover-placeholder.svg');
        $firstCap = (string) ($gridPosts[0]['title'] ?? '');
    }
    ?>
      <aside class="light-table" id="light-table" aria-hidden="true">
        <div class="plate light-plate">
          <span class="plate-img"><img src="<?= e($firstCover) ?>" alt="" loading="lazy" /></span>
        </div>
        <span class="light-cap" id="light-cap"><?= e($firstCap) ?></span>
      </aside>
    </div>

    <?php $archiveUrl = lurl('/blog/'.$blogSlug.'/archive'); ?>
    <div class="index-footer reveal" id="index-footer" data-archive="<?= $archiveUrl ?>" data-allmore="<?= $hasMore ? '1' : '0' ?>"<?= $hasMore ? '' : ' style="display:none;"' ?>>
      <span class="index-count" id="index-footer-count"><?= translate_html('offset::home.showing', ['shown' => sprintf('%02d', 1 + count($gridPosts)), 'total' => sprintf('%02d', $postCount), 'blog' => $blogTitle]) ?></span>
      <a href="<?= $archiveUrl ?>" class="link-arrow" id="index-footer-link">
        <span id="index-footer-label"><?= e($t('offset::home.openAll')) ?></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>
<?php } ?>

<section class="colophon" id="colophon">
  <div class="container">
    <div class="colophon-grid">
      <?php
      // The label can be a year or a short word; words render smaller so
      // the display size doesn't overflow the column.
      $aboutLabel = !empty($settings['founded_year']) ? (string) $settings['founded_year'] : date('Y');
  $aboutLabelClass = mb_strlen($aboutLabel) > 6 ? ' colophon-number--word' : '';
  ?>
      <p class="colophon-number<?= $aboutLabelClass ?> reveal"><span<?= $wording->attrs('offset::home.est') ?>><?= $wording->html('offset::home.est') ?></span><br /><span<?= edit_text_attrs('founded_year', date('Y')) ?>><?= e($aboutLabel) ?></span></p>
      <div class="colophon-body">
        <span class="eyebrow reveal"<?= $wording->attrs('offset::home.colophonEyebrow') ?>><?= $wording->html('offset::home.colophonEyebrow') ?></span>
        <?php $aboutDefault = !empty($settings['subtitle']) ? e($settings['subtitle']) : translate_html('offset::home.colophonDefault', ['blog' => $blogTitle]); ?>
        <p class="colophon-statement reveal"<?= edit_text_attrs('about_text', $aboutDefault) ?>><?= !empty($settings['about_text']) ? e($settings['about_text']) : $aboutDefault ?></p>
        <span class="colophon-sign reveal"><?= $ownerName ?></span>

        <p class="colophon-fine reveal">
          <?= translate_html('offset::home.colophonFine', ['blog' => $blogTitle, 'platform' => e(env('APP_NAME', ''))]) ?>
        </p>
      </div>
    </div>
  </div>
</section>

<section class="newsletter" id="newsletter">
  <div class="container">
    <div class="news-slab">
      <div class="news-copy">
        <span class="eyebrow reveal"<?= $wording->attrs('offset::home.newsEyebrow') ?>><?= $wording->html('offset::home.newsEyebrow') ?></span>
        <h2 class="reveal"<?= edit_text_attrs('newsletter_heading', $t('offset::home.newsHeading')) ?>><?= !empty($settings['newsletter_heading']) ? e($settings['newsletter_heading']) : e($t('offset::home.newsHeading')) ?></h2>
        <p class="reveal"<?= edit_text_attrs('newsletter_text', $t('offset::home.newsText')) ?>><?= !empty($settings['newsletter_text']) ? e($settings['newsletter_text']) : e($t('offset::home.newsText')) ?></p>
      </div>

      <form class="news-form reveal" action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post">
        <?= csrf_field_lazy() ?>
        <label for="email-sub"><?= e($t('offset::emailLabel')) ?></label>
        <div class="news-input">
          <input id="email-sub" name="email" type="email" placeholder="reader@proof-room.co"
            value="<?= e(auth()->check() ? (string) (auth()->user()['email'] ?? '') : '') ?>"
            autocomplete="email" maxlength="255" required />
          <button type="submit">
            <?= e($t('offset::home.subscribe')) ?>
            <svg class="arrow" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </div>
        <span class="news-fine"<?= $wording->attrs('offset::home.newsFine') ?>><?= $wording->html('offset::home.newsFine') ?></span>
      </form>
    </div>
  </div>
</section>
{% include "partials/public/_edit_texts.lex.php" %}
{% endblock %}

{% block scripts %}
	<script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
