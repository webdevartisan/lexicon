{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'FOLIO');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerNameRaw = $user['display_name_cached'] ?? $user['username'] ?? 'The Author';
  $ownerName = e($ownerNameRaw);
  $postCount = (int) ($totalPosts ?? count($posts ?? []));
  $featuredPost = !empty($posts) ? $posts[0] : null;
  $gridPosts = !empty($posts) ? array_slice($posts, 1, 6) : []; // landing shows 6; rest lives on /archive
  $hasMore = $postCount > 1 + count($gridPosts);

  // The Long Read spotlights the longest piece on the landing page.
  $totalMinutes = 0;
  $longReadPost = null;
  $longReadMinutes = 0;
  foreach (($posts ?? []) as $p) {
      $minutes = reading_time($p['content'] ?? '');
      $totalMinutes += $minutes;
      if ($longReadPost === null || $minutes > $longReadMinutes) {
          $longReadPost = $p;
          $longReadMinutes = $minutes;
      }
  }

  // stored featured_image is sometimes a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';
  ?>

<section class="hero" id="top">
  <div class="container">
    <div class="hero-meta-top">
      <span><?= $blogTitle ?> &mdash; <?= date('Y') ?></span>
      <span><?= !empty($settings['tagline']) ? e($settings['tagline']) : 'A Journal of Slow Reading' ?></span>
      <span><?= $postCount ?> Post<?= $postCount === 1 ? '' : 's' ?> / <?= $totalMinutes ?> Min Read</span>
    </div>

    <h1 class="hero-headline" data-split><?= $blogTitle ?></h1>

    <div class="hero-bottom">
      <p class="hero-dek reveal">
        <?= !empty($settings['subtitle']) ? e($settings['subtitle']) : 'A collection of essays, ideas, and slow reading.' ?>
      </p>
      <?php if ($featuredPost) { ?>
      <div class="hero-pointer reveal">
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? '')) ?>" class="link-arrow">
          Begin reading
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>
      </div>
      <?php } ?>
    </div>
  </div>
</section>

<?php if ($featuredPost) {
    $fCover = (string) ($featuredPost['featured_image'] ?? '');
    $fImg = preg_match($validImg, $fCover) ? e($fCover) : 'https://picsum.photos/seed/folio-lead/1400/1750';
    $fUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? ''));
    $fTitle = e($featuredPost['title'] ?? 'Untitled');
    $fExc = e($featuredPost['excerpt'] ?? '');
    $fAuthor = profile_link($featuredPost['author_name'], $featuredPost['author_profile_slug']);
    $fDate = e(local_datetime($featuredPost['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
    $fCat = e($featuredPost['category'] ?? 'Article');
    ?>
<section class="lead" id="lead">
  <div class="container">
    <div class="grid-12">
      <figure class="lead-image img-mask">
        <img src="<?= $fImg ?>" alt="<?= $fTitle ?>" loading="eager" />
      </figure>

      <div class="lead-body">
        <div class="lead-tag">
          <span><?= $fCat ?></span>
          <?php if ($fDate) { ?><span><?= $fDate ?></span><?php } ?>
        </div>

        <h2 class="lead-title"><?= $fTitle ?></h2>

        <?php if ($fExc) { ?>
          <p class="lead-dek"><?= $fExc ?></p>
        <?php } ?>

        <a href="<?= $fUrl ?>" class="link-arrow" style="align-self:flex-start;margin-top:var(--step-2);">
          Read the post
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>

        <?php if ($fAuthor) { ?>
        <div class="lead-byline">
          <span>By <?= $fAuthor ?></span>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>
</section>
<?php } ?>

<?php if (!empty($gridPosts)) {
    $cards = $gridPosts;
    ?>
<section class="filters" id="index">
  <div class="container">
    <div class="row">
      <h2 class="filter-title reveal">The Index</h2>
      <span class="filter-count reveal" id="filter-count"><?= count($gridPosts) ?> post<?= count($gridPosts) === 1 ? '' : 's' ?></span>
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
  </div>
</section>

<section class="articles">
  <div class="container">
    <div class="articles-grid">
      {% include "blogs/_index_cards.lex.php" %}
    </div>

    <?php $archiveUrl = lurl('/blog/'.$blogSlug.'/archive'); ?>
    <div class="articles-footer reveal" id="index-footer" data-archive="<?= $archiveUrl ?>" data-allmore="<?= $hasMore ? '1' : '0' ?>"<?= $hasMore ? '' : ' style="display:none;"' ?>>
      <span class="filter-count" id="index-footer-count">Showing <?= sprintf('%02d', 1 + count($gridPosts)) ?> of <?= sprintf('%02d', $postCount) ?> &mdash; <?= $blogTitle ?></span>
      <a href="<?= $archiveUrl ?>" class="link-arrow" id="index-footer-link">
        <span id="index-footer-label">Open the full archive</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>
<?php } ?>

<?php if ($longReadPost) {
    $lrTitle = e($longReadPost['title'] ?? 'Untitled');
    $lrUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($longReadPost['slug'] ?? ''));
    $lrMinutes = reading_time($longReadPost['content'] ?? '');
    $lrAuthor = profile_link($longReadPost['author_name'], $longReadPost['author_profile_slug']);

    $excerpt = trim((string) ($longReadPost['excerpt'] ?? ''));
    $quote = trim(preg_replace('/\s+/', ' ', $excerpt !== ''
        ? $excerpt
        : strip_tags((string) ($longReadPost['content'] ?? ''))));

    if (mb_strlen($quote) > 220) {
        $cut = mb_substr($quote, 0, 220);
        $space = mb_strrpos($cut, ' ');
        $quote = ($space !== false ? mb_substr($cut, 0, $space) : $cut).'…';
    }

    // Fade the last word or two into muted weight so the quote ends on a quieter beat.
    $words = preg_split('/\s+/', $quote);
    $tailLen = count($words) >= 4 ? 2 : 1;
    // Not $head: template globals share this scope, and `head` is the i18n one
    // the layout reads for canonical, hreflang and the language switcher.
    $quoteHead = e(implode(' ', array_slice($words, 0, -$tailLen)));
    $quoteTail = e(implode(' ', array_slice($words, -$tailLen)));
    ?>
<section class="longread">
  <div class="container">
    <div class="grid-12">
      <div class="longread-eyebrow reveal">
        <h3>The Long Read</h3>
        <span class="eyebrow">Featured &mdash; <?= $lrMinutes ?> Min</span>
      </div>

      <blockquote class="longread-quote reveal">
        &ldquo;<?= $quoteHead ?> <span class="accent"><?= $quoteTail ?></span>&rdquo;
      </blockquote>

      <div class="longread-author reveal">
        <strong><?= $lrAuthor ?></strong>
        <span>Post &mdash; <?= $lrMinutes ?> Min</span>
      </div>

      <div class="longread-body reveal">
        <p><em><?= $lrTitle ?></em> &mdash; continue reading the full article in its slow, deliberate form.</p>
        <a href="<?= $lrUrl ?>">Read the full post &rarr;</a>
      </div>
    </div>
  </div>
</section>
<?php } ?>

<section class="colophon" id="colophon">
  <div class="container">
    <div class="grid-12">
      <?php
        // The label can be a year or a short word; words render smaller so
        // the display size doesn't overflow the column.
        $aboutLabel = !empty($settings['founded_year']) ? (string) $settings['founded_year'] : date('Y');
  $aboutLabelClass = mb_strlen($aboutLabel) > 6 ? ' colophon-number--word' : '';
  ?>
      <p class="colophon-number<?= $aboutLabelClass ?> reveal"><?= e($aboutLabel) ?>.</p>
      <div class="colophon-body">
        <span class="colophon-eyebrow reveal">About this blog</span>
        <p class="colophon-statement reveal">
          <?php if (!empty($settings['about_text'])) { ?>
            <?= e($settings['about_text']) ?>
          <?php } elseif (!empty($settings['subtitle'])) { ?>
            <?= e($settings['subtitle']) ?>
          <?php } else { ?>
            <?= e($blog['blog_name'] ?? 'FOLIO') ?> — a place for slow reading.
          <?php } ?>
        </p>
        <span class="colophon-sign reveal">&mdash; <?= e($user['display_name_cached'] ?? $user['username'] ?? 'The Author') ?></span>
      </div>
    </div>
  </div>
</section>

<section class="newsletter" id="newsletter">
  <div class="container">
    <div class="grid-12">
      <div class="news-copy">
        <span class="eyebrow reveal">Stay in touch</span>
        <h2 class="reveal"><?= !empty($settings['newsletter_heading']) ? e($settings['newsletter_heading']) : 'Read '.$blogTitle.' from your own quiet rooms.' ?></h2>
        <p class="reveal"><?= !empty($settings['newsletter_text']) ? e($settings['newsletter_text']) : 'No tracking, no algorithms, easy unsubscribe.' ?></p>
      </div>

      <form class="news-form reveal" action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post">
        <?= csrf_field() ?>
        <label for="email-sub">Your address</label>
        <div class="news-input">
          <input id="email-sub" name="email" type="email" placeholder="reader@quiet-room.co"
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
