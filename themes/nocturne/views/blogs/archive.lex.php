{% extends "base.lex.php" %}
{% block title %}Archive &mdash; {{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/archive.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'Nocturne');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerName = e($user['display_name_cached'] ?? $user['username'] ?? 'The Author');

  $current = (int) ($pagination['currentPage'] ?? 1);
  $total = (int) ($pagination['totalPages'] ?? 1);
  $totalPosts = (int) ($pagination['totalPosts'] ?? count($posts ?? []));
  $perPage = (int) ($pagination['perPage'] ?? count($posts ?? []));
  $first = ($current - 1) * $perPage + 1;
  $last = min($current * $perPage, $totalPosts);

  // stored featured_image can be a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';

  // Headings are overridable so this same layout backs the full archive and
  // the category/tag listings.
  $kicker = $archiveKicker ?? ('The Ledger &mdash; '.$blogTitle);
  $heading = $archiveTitle ?? 'Every Night, Filed.';
  $dek = $archiveDek ?? ('Every post from <em>'.$blogTitle.'</em>, newest first.');
  ?>

<section class="ledger-hero">
  <div class="container">
    <div class="ledger-rail">
      <span><?= $kicker ?></span>
      <span><?= $totalPosts ?> Post<?= $totalPosts === 1 ? '' : 's' ?></span>
    </div>

    <h1 class="ledger-title"><?= $heading ?></h1>

    <p class="ledger-dek"><?= $dek ?></p>

    <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="ledger-back link-arrow">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6" /></svg>
      Back to the front page
    </a>
  </div>
</section>

<section class="ledger">
  <div class="container">
    <?php if (!empty($posts)) { ?>
    <div class="idx-list">
      <?php foreach ($posts as $i => $post) {
          $cover = (string) ($post['featured_image'] ?? '');
          $img = preg_match($validImg, $cover) ? e($cover) : 'https://picsum.photos/seed/nocturne-ledger-'.$post['id'].'/900/1080';
          $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
          $title = e($post['title'] ?? 'Untitled');
          $cat = trim((string) ($post['category'] ?? 'Post'));
          $date = e($post['published_at'] ?? '');
          $author = e($post['author_name'] ?? $ownerName);
          $minutes = reading_time($post['content'] ?? '');
          $n = $first + $i;
          ?>
      <article class="idx-row reveal" data-cover="<?= $img ?>" onclick="location.href='<?= $url ?>'">
        <span class="idx-num"><?= sprintf('%02d', $n) ?></span>
        <h3 class="idx-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
        <div class="idx-thumb" aria-hidden="true">
          <img src="<?= $img ?>" alt="" loading="lazy" />
        </div>
        <div class="idx-meta">
          <span class="cat"><?= e($cat) ?></span>
          <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
          <span><?= $author ?> &middot; <?= $minutes ?> Min</span>
        </div>
      </article>
      <?php } ?>
    </div>

    <div class="idx-float" id="idx-float" aria-hidden="true"><img src="" alt="" /></div>
    <?php } else { ?>
      <p class="ledger-empty">No posts yet. The lamp is on, though.</p>
    <?php } ?>

    <?php if ($total > 1) { ?>
    <div class="ledger-pagination reveal">
      <span class="ledger-pagination-info">
        Showing <?= sprintf('%02d', $first) ?>&ndash;<?= sprintf('%02d', $last) ?> of <?= sprintf('%02d', $totalPosts) ?>
      </span>
      <nav class="ledger-pagination-nav" aria-label="Pagination">
        <?php if ($current > 1) { ?>
          <a href="?page=<?= $current - 1 ?>" rel="prev">&larr; Prev</a>
        <?php } else { ?>
          <span class="is-disabled">&larr; Prev</span>
        <?php } ?>

        <?php for ($p = 1; $p <= $total; $p++) { ?>
          <?php if ($p === $current) { ?>
            <span class="is-current"><?= sprintf('%02d', $p) ?></span>
          <?php } else { ?>
            <a href="?page=<?= $p ?>"><?= sprintf('%02d', $p) ?></a>
          <?php } ?>
        <?php } ?>

        <?php if ($current < $total) { ?>
          <a href="?page=<?= $current + 1 ?>" rel="next">Next &rarr;</a>
        <?php } else { ?>
          <span class="is-disabled">Next &rarr;</span>
        <?php } ?>
      </nav>
    </div>
    <?php } ?>
  </div>
</section>
{% endblock %}

{% block scripts %}
  <script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
