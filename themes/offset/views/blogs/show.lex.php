{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'OFFSET');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerName = e($user['display_name_cached'] ?? $user['username'] ?? 'The Editor');
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
      <span><?= $blogTitle ?> &mdash; Vol. <?= date('Y') ?></span>
      <span>Edition of <?= $postCount ?> Post<?= $postCount === 1 ? '' : 's' ?></span>
      <span><?= $totalMinutes ?> Min of Reading</span>
    </div>

    <div class="hero-stage">
      <h1 class="hero-headline" data-split><?= $blogTitle ?></h1>
      <span class="proof-stamp" aria-hidden="true">
        <?= !empty($settings['tagline']) ? e($settings['tagline']) : 'Fresh from the press' ?>
      </span>
    </div>

    <div class="hero-bottom">
      <p class="hero-dek reveal">
        <?= !empty($settings['subtitle']) ? e($settings['subtitle']) : 'Essays and dispatches, proofed, inked, and published.' ?>
      </p>
      <?php if ($featuredPost) { ?>
      <div class="hero-pointer reveal">
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? '')) ?>" class="link-arrow">
          Read the lead story
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>
      </div>
      <?php } ?>
    </div>
  </div>
</section>

<?php if ($featuredPost) {
    $fCover = (string) ($featuredPost['featured_image'] ?? '');
    $fImg = preg_match($validImg, $fCover) ? e($fCover) : 'https://picsum.photos/seed/offset-lead/1400/1050';
    $fUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? ''));
    $fTitle = e($featuredPost['title'] ?? 'Untitled');
    $fExc = e($featuredPost['excerpt'] ?? '');
    $fAuthor = profile_link(
        $featuredPost['author_name'] ?? ($user['display_name_cached'] ?? $user['username'] ?? ''),
        $user['public_profile_slug'] ?? null
    );
    $fDate = e($featuredPost['published_at'] ?? '');
    $fCat = e($featuredPost['category'] ?? 'Article');
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
          <span class="eyebrow">Lead story &mdash; <?= $fCat ?></span>
          <?php if ($fDate) { ?><span class="eyebrow"><?= $fDate ?></span><?php } ?>
        </div>

        <h2 class="lead-title reveal"><a href="<?= $fUrl ?>"><?= $fTitle ?></a></h2>

        <?php if ($fExc) { ?>
          <p class="lead-dek reveal"><?= $fExc ?></p>
        <?php } ?>

        <div class="lead-byline reveal">
          <?php if ($fAuthor) { ?><span>By <?= $fAuthor ?></span><?php } ?>
          <span><?= $fMinutes ?> Min</span>
        </div>

        <a href="<?= $fUrl ?>" class="link-arrow reveal">
          Read the post
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
<section class="index" id="index">
  <div class="container">
    <div class="index-head">
      <h2 class="index-title reveal">The Index.</h2>
      <span class="index-count reveal" id="filter-count"><?= count($gridPosts) ?> post<?= count($gridPosts) === 1 ? '' : 's' ?></span>
    </div>

    <ul class="filter-list reveal" role="tablist" aria-label="Filter posts by category">
      <?php $allActive = ($activeCategory ?? '') === ''; ?>
      <li class="<?= $allActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug) ?>" data-filter="" role="tab" aria-selected="<?= $allActive ? 'true' : 'false' ?>">All</a></li>
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
        $firstCover = preg_match($validImg, $c0) ? $c0 : 'https://picsum.photos/seed/offset-row-1/900/680';
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
      <span class="index-count" id="index-footer-count">Showing <?= sprintf('%02d', 1 + count($gridPosts)) ?> of <?= sprintf('%02d', $postCount) ?> &mdash; <?= $blogTitle ?></span>
      <a href="<?= $archiveUrl ?>" class="link-arrow" id="index-footer-link">
        <span id="index-footer-label">Open the full catalogue</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
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
      <p class="colophon-number<?= $aboutLabelClass ?> reveal">Est.<br /><?= e($aboutLabel) ?></p>
      <div class="colophon-body">
        <span class="eyebrow reveal">Colophon &mdash; About this blog</span>
        <p class="colophon-statement reveal">
          <?php if (!empty($settings['about_text'])) { ?>
            <?= e($settings['about_text']) ?>
          <?php } elseif (!empty($settings['subtitle'])) { ?>
            <?= e($settings['subtitle']) ?>
          <?php } else { ?>
            <?= $blogTitle ?> &mdash; written, proofed, and published in small editions.
          <?php } ?>
        </p>
        <span class="colophon-sign reveal">&mdash; <?= $ownerName ?></span>

        <p class="colophon-fine reveal">
          <?= $blogTitle ?> is set in Archivo &amp; Schibsted Grotesk,
          printed on porcelain #EEF0F1 with one plate of cobalt,
          and published on <?= e($_ENV['APP_NAME'] ?? 'the web') ?>.
        </p>
      </div>
    </div>
  </div>
</section>

<section class="newsletter" id="newsletter">
  <div class="container">
    <div class="news-slab">
      <div class="news-copy">
        <span class="eyebrow reveal">The proof run</span>
        <h2 class="reveal"><?= !empty($settings['newsletter_heading']) ? e($settings['newsletter_heading']) : 'Every new post, hot off the press.' ?></h2>
        <p class="reveal"><?= !empty($settings['newsletter_text']) ? e($settings['newsletter_text']) : 'One email per post. No tracking, easy unsubscribe.' ?></p>
      </div>

      <form class="news-form reveal" action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post">
        <?= csrf_field() ?>
        <label for="email-sub">Your address</label>
        <div class="news-input">
          <input id="email-sub" name="email" type="email" placeholder="reader@proof-room.co"
            value="<?= e(auth()->check() ? (string) (auth()->user()['email'] ?? '') : '') ?>"
            autocomplete="email" maxlength="255" required />
          <button type="submit">
            Subscribe
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </div>
        <span class="news-fine">Free &mdash; unsubscribe any time</span>
      </form>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
	<script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
