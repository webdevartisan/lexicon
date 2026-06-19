{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'FOLIO');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerName = e($user['display_name_cached'] ?? $user['username'] ?? 'The Author');
  $postCount = (int) ($totalPosts ?? count($posts ?? []));
  $featuredPost = !empty($posts) ? $posts[0] : null;
  $gridPosts = !empty($posts) ? array_slice($posts, 1, 6) : []; // landing shows 6; rest lives on /archive
  $hasMore = $postCount > 1 + count($gridPosts);

  $totalMinutes = 0;
  foreach (($posts ?? []) as $p) {
      $totalMinutes += reading_time($p['content'] ?? '');
  }

  $longReadPost = $gridPosts[0] ?? $featuredPost;

  // stored featured_image is sometimes a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';
  ?>

<section class="hero" id="top">
  <div class="container">
    <div class="hero-meta-top">
      <span><?= $blogTitle ?> &mdash; <?= date('Y') ?></span>
      <span><?= !empty($blog['subtitle']) ? e($blog['subtitle']) : 'A Journal of Slow Reading' ?></span>
      <span><?= $postCount ?> Post<?= $postCount === 1 ? '' : 's' ?> / <?= $totalMinutes ?> Min Read</span>
    </div>

    <h1 class="hero-headline" data-split><?= $blogTitle ?></h1>

    <div class="hero-bottom">
      <p class="hero-dek reveal">
        <?= !empty($blog['subtitle']) ? e($blog['subtitle']) : 'A collection of essays, ideas, and slow reading.' ?>
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
    $fAuthor = e($featuredPost['author_name'] ?? ($user['display_name_cached'] ?? $user['username'] ?? ''));
    $fDate = e($featuredPost['published_at'] ?? '');
    $fCat = e($featuredPost['category'] ?? 'Essay');
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
    $cats = [];
    foreach ($gridPosts as $gp) {
        $c = trim((string) ($gp['category'] ?? ''));
        if ($c !== '' && !in_array($c, $cats, true)) {
            $cats[] = $c;
        }
    }
    ?>
<section class="filters" id="index">
  <div class="container">
    <div class="row">
      <h2 class="filter-title reveal">The Index</h2>
      <span class="filter-count reveal"><?= count($gridPosts) ?> post<?= count($gridPosts) === 1 ? '' : 's' ?></span>
    </div>

    <ul class="filter-list reveal" role="tablist" aria-label="Filter posts by category">
      <li class="is-active"><button type="button" data-filter="*" role="tab" aria-selected="true">All</button></li>
      <?php foreach ($cats as $c) { ?>
        <li><button type="button" data-filter="<?= e(strtolower($c)) ?>" role="tab" aria-selected="false"><?= e($c) ?></button></li>
      <?php } ?>
    </ul>
  </div>
</section>

<section class="articles">
  <div class="container">
    <div class="articles-grid">
      <?php foreach ($gridPosts as $i => $post) {
          $cover = (string) ($post['featured_image'] ?? '');
          $img = preg_match($validImg, $cover) ? e($cover) : 'https://picsum.photos/seed/folio-card-'.($i + 1).'/900/1080';
          $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
          $title = e($post['title'] ?? 'Untitled');
          $cat = trim((string) ($post['category'] ?? 'Post'));
          $date = e($post['published_at'] ?? '');
          $author = e($post['author_name'] ?? $ownerName);
          $minutes = reading_time($post['content'] ?? '');
          ?>
      <article class="article reveal" data-category="<?= e(strtolower($cat)) ?>" onclick="location.href='<?= $url ?>'">
        <div class="article-meta">
          <span class="cat"><?= sprintf('%02d', $i + 1) ?> &mdash; <?= e($cat) ?></span>
          <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
        </div>
        <div class="article-image img-mask">
          <img src="<?= $img ?>" alt="<?= $title ?>" loading="lazy" />
          <span class="article-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 17L17 7M9 7h8v8"/></svg>
          </span>
        </div>
        <h3 class="article-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
        <div class="article-byline">
          <span><?= $author ?></span>
          <span><?= $minutes ?> Min</span>
        </div>
      </article>
      <?php } ?>
    </div>

    <?php if ($hasMore) { ?>
    <div class="articles-footer reveal">
      <span class="filter-count">Showing <?= sprintf('%02d', 1 + count($gridPosts)) ?> of <?= sprintf('%02d', $postCount) ?> &mdash; <?= $blogTitle ?></span>
      <a href="<?= lurl('/blog/'.$blogSlug.'/archive') ?>" class="link-arrow">
        Open the full archive
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
    <?php } ?>
  </div>
</section>
<?php } ?>

<?php if ($longReadPost) {
    $lrTitle = e($longReadPost['title'] ?? 'Untitled');
    $lrUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($longReadPost['slug'] ?? ''));
    $lrMinutes = reading_time($longReadPost['content'] ?? '');
    $lrAuthor = e($longReadPost['author_name'] ?? $ownerName);

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
    $head = e(implode(' ', array_slice($words, 0, -$tailLen)));
    $tail = e(implode(' ', array_slice($words, -$tailLen)));
    ?>
<section class="longread">
  <div class="container">
    <div class="grid-12">
      <div class="longread-eyebrow reveal">
        <h3>The Long Read</h3>
        <span class="eyebrow">Featured &mdash; <?= $lrMinutes ?> Min</span>
      </div>

      <blockquote class="longread-quote reveal">
        &ldquo;<?= $head ?> <span class="accent"><?= $tail ?></span>&rdquo;
      </blockquote>

      <div class="longread-author reveal">
        <strong><?= $lrAuthor ?></strong>
        <span>Post &mdash; <?= $lrMinutes ?> Min</span>
      </div>

      <div class="longread-body reveal">
        <p><em><?= $lrTitle ?></em> &mdash; continue reading the full essay in its slow, deliberate form.</p>
        <a href="<?= $lrUrl ?>">Read the full post &rarr;</a>
      </div>
    </div>
  </div>
</section>
<?php } ?>

<section class="colophon" id="colophon">
  <div class="container">
    <div class="grid-12">
      <p class="colophon-number reveal"><?= date('Y') ?>.</p>
      <div class="colophon-body">
        <span class="colophon-eyebrow reveal">About this blog</span>
        <p class="colophon-statement reveal">
          <?= !empty($blog['subtitle']) ? e($blog['subtitle']) : e($user['blog_name'] ?? 'FOLIO').' — a place for slow reading.' ?>
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
        <h2 class="reveal">Read <?= $blogTitle ?> from your own quiet rooms.</h2>
        <p class="reveal">No tracking, no algorithms, easy unsubscribe.</p>
      </div>

      <form class="news-form reveal" onsubmit="event.preventDefault(); this.querySelector('button').textContent='Thank you'; this.querySelector('input').value='';">
        <label for="email-sub">Your address</label>
        <div class="news-input">
          <input id="email-sub" type="email" placeholder="reader@quiet-room.co" autocomplete="email" required />
          <button type="submit">
            Subscribe
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </div>
        <span class="news-fine">Free &mdash; by request</span>
      </form>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
	<script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
