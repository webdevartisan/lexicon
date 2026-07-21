{% extends "base.lex.php" %}
{% block title %}Archive &mdash; {{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/archive.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'OFFSET');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerNameRaw = $user['display_name_cached'] ?? $user['username'] ?? 'The Editor';
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
  $kicker = $archiveKicker ?? ('The Catalogue &mdash; '.$blogTitle);
  $heading = $archiveTitle ?? 'Everything in Print.';
  $dek = $archiveDek ?? ('Every post from <em>'.$blogTitle.'</em>, catalogued in reverse chronological order.');
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

<section class="catalogue">
  <div class="container">
    <?php if (!empty($posts)) { ?>
      <?php
        // Group the page's rows under year headings so long archives scan
        // like a catalogue rather than one flat run.
        $lastYear = null;
        ?>
      <div class="catalogue-rows">
      <?php foreach ($posts as $i => $post) {
          $cover = (string) ($post['featured_image'] ?? '');
          $img = preg_match($validImg, $cover) ? $cover : 'https://picsum.photos/seed/offset-cat-'.$post['id'].'/900/680';
          $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
          $title = e($post['title'] ?? 'Untitled');
          $cat = trim((string) ($post['category'] ?? 'Post'));
          $date = e($post['published_at'] ?? '');
          $author = profile_link($post['author_name'] ?? $ownerNameRaw, $user['public_profile_slug'] ?? null);
          $minutes = reading_time($post['content'] ?? '');
          $n = $first + $i;

          $stamp = strtotime((string) ($post['published_at'] ?? ''));
          $year = $stamp ? date('Y', $stamp) : '';
          ?>
        <?php if ($year !== '' && $year !== $lastYear) {
            $lastYear = $year;
            ?>
        <h2 class="catalogue-year reveal"><?= e($year) ?></h2>
        <?php } ?>

        <article class="index-row reveal" onclick="location.href='<?= $url ?>'">
          <span class="row-no"><?= sprintf('%02d', $n) ?></span>

          <span class="plate row-thumb" aria-hidden="true">
            <span class="plate-img"><img src="<?= e($img) ?>" alt="" loading="lazy" /></span>
          </span>

          <div class="row-main">
            <h3 class="row-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
            <span class="row-author">By <?= $author ?></span>
          </div>

          <div class="row-meta">
            <span class="row-cat"><?= e($cat) ?></span>
            <?php if ($date) { ?><span class="row-date"><?= $date ?></span><?php } ?>
            <span class="row-min"><?= $minutes ?> Min</span>
          </div>

          <span class="row-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 17L17 7M9 7h8v8"/></svg>
          </span>
        </article>
      <?php } ?>
      </div>
    <?php } else { ?>
      <p class="catalogue-empty">Nothing in print yet.</p>
    <?php } ?>

    <?php if ($total > 1) { ?>
    <div class="archive-pagination reveal">
      <span class="archive-pagination-info">
        Sheet <?= sprintf('%02d', $current) ?> / <?= sprintf('%02d', $total) ?> &mdash; showing <?= sprintf('%02d', $first) ?>&ndash;<?= sprintf('%02d', $last) ?> of <?= sprintf('%02d', $totalPosts) ?>
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
