{% extends "base.lex.php" %}
{% block title %}Archive &mdash; {{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/archive.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'FOLIO');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerNameRaw = $user['display_name_cached'] ?? $user['username'] ?? 'The Author';
  $ownerName = e($ownerNameRaw);

  $current = (int) ($pagination['currentPage'] ?? 1);
  $total = (int) ($pagination['totalPages'] ?? 1);
  $totalPosts = (int) ($pagination['totalPosts'] ?? count($posts ?? []));
  $perPage = (int) ($pagination['perPage'] ?? count($posts ?? []));
  $first = ($current - 1) * $perPage + 1;
  $last = min($current * $perPage, $totalPosts);

  // stored featured_image can be a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';
  ?>

<?php
  // Headings are overridable so this same layout backs the full archive and the
  // category/tag listings. Defaults keep the plain "/archive" page unchanged.
  $kicker = $archiveKicker ?? ('The Archive &mdash; '.$blogTitle);
  $heading = $archiveTitle ?? 'The Full Index.';
  $dek = $archiveDek ?? ('Every post from <em>'.$blogTitle.'</em>, arranged in reverse chronological order.');
  ?>
<section class="archive-hero">
  <div class="container">
    <div class="archive-meta-top">
      <span><?= $kicker ?></span>
      <span><?= $totalPosts ?> Post<?= $totalPosts === 1 ? '' : 's' ?></span>
    </div>

    <h1 class="archive-title"><?= $heading ?></h1>

    <p class="archive-dek">
      <?= $dek ?>
    </p>

    <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="archive-back">
      &larr; Back to the front page
    </a>
  </div>
</section>

<section class="articles">
  <div class="container">
    <?php if (!empty($posts)) { ?>
    <div class="articles-grid">
      <?php foreach ($posts as $i => $post) {
          $cover = (string) ($post['featured_image'] ?? '');
          $img = preg_match($validImg, $cover) ? e($cover) : 'https://picsum.photos/seed/folio-archive-'.$post['id'].'/900/1080';
          $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
          $title = e($post['title'] ?? 'Untitled');
          $cat = trim((string) ($post['category'] ?? 'Post'));
          $date = e(local_datetime($post['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
          $author = profile_link($post['author_name'] ?? $ownerNameRaw, $user['public_profile_slug'] ?? null);
          $minutes = reading_time($post['content'] ?? '');
          $n = $first + $i;
          ?>
      <article class="article reveal" data-card-link="<?= e($url) ?>">
        <div class="article-meta">
          <span class="cat"><?= sprintf('%02d', $n) ?> &mdash; <?= e($cat) ?></span>
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
    <?php } else { ?>
      <p style="font-family:var(--mono);text-transform:uppercase;letter-spacing:0.18em;color:var(--muted);">No posts yet.</p>
    <?php } ?>

    <?php if ($total > 1) { ?>
    <div class="archive-pagination reveal">
      <span class="archive-pagination-info">
        Showing <?= sprintf('%02d', $first) ?>&ndash;<?= sprintf('%02d', $last) ?> of <?= sprintf('%02d', $totalPosts) ?>
      </span>
      <nav class="archive-pagination-nav" aria-label="Pagination">
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
