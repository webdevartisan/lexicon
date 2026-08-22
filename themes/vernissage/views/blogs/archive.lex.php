{% extends "base.lex.php" %}
{% block title %}Catalogue &mdash; {{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/archive.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'Vernissage');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');

  $current = (int) ($pagination['currentPage'] ?? 1);
  $total = (int) ($pagination['totalPages'] ?? 1);
  $totalPosts = (int) ($pagination['totalPosts'] ?? count($posts ?? []));
  $perPage = (int) ($pagination['perPage'] ?? count($posts ?? []));
  $first = ($current - 1) * $perPage + 1;
  $last = min($current * $perPage, $totalPosts);

  // year dividers carry their roman numeral, museum-plate style
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

  // stored featured_image can be a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';
  ?>

<?php
  // Headings are overridable so this same layout backs the full catalogue and
  // the category/tag listings. Defaults keep the plain "/archive" page unchanged.
  $kicker = $archiveKicker ?? ('Catalogue Raisonn&eacute; &mdash; '.$blogTitle);
  $heading = $archiveTitle ?? 'The Complete Works.';
  $dek = $archiveDek ?? ('Every work from <em>'.$blogTitle.'</em>, catalogued with the most recent acquisitions first.');
  ?>
<section class="archive-hero">
  <div class="container">
    <div class="archive-meta-top">
      <span><?= $kicker ?></span>
      <span><?= $totalPosts ?> Work<?= $totalPosts === 1 ? '' : 's' ?></span>
    </div>

    <h1 class="archive-title"><?= $heading ?></h1>

    <p class="archive-dek">
      <?= $dek ?>
    </p>

    <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="archive-back">
      &larr; Back to the exhibition
    </a>
  </div>
</section>

<section class="catalogue">
  <div class="container">
    <?php if (!empty($posts)) { ?>
      <?php
        // Group the page's entries under year dividers so long catalogues scan
        // like plates in a monograph rather than one flat run.
        $lastYear = null;
        ?>
      <div class="catalogue-rows">
      <?php foreach ($posts as $i => $post) {
          $cover = (string) ($post['featured_image'] ?? '');
          $img = preg_match($validImg, $cover) ? $cover : 'https://picsum.photos/seed/vernissage-cat-'.$post['id'].'/900/720';
          $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
          $title = e($post['title'] ?? 'Untitled');
          $cat = trim((string) ($post['category'] ?? 'Work'));
          $date = e(local_datetime($post['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
          $author = profile_link($post['author_name'], $post['author_profile_slug']);
          $minutes = reading_time($post['content'] ?? '');
          $n = $first + $i;

          $year = local_datetime($post['published_at'] ?? null, 'Y', blog_timezone((int) ($blog['id'] ?? 0)));
          ?>
        <?php if ($year !== '' && $year !== $lastYear) {
            $lastYear = $year;
            ?>
        <h2 class="catalogue-year reveal"><?= e($year) ?> <span class="catalogue-year-roman"><?= $roman((int) $year) ?></span></h2>
        <?php } ?>

        <article class="work-row reveal" data-card-link="<?= e($url) ?>">
          <span class="row-no">Cat. <?= sprintf('%02d', $n) ?></span>

          <span class="frame row-thumb" aria-hidden="true">
            <span class="frame-img"><img src="<?= e($img) ?>" alt="" loading="lazy" /></span>
          </span>

          <div class="row-main">
            <h3 class="row-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
            <span class="row-author"><?= $author ?><?= $year !== '' ? ', '.e($year) : '' ?></span>
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
      <p class="catalogue-empty">Nothing on view yet.</p>
    <?php } ?>

    <?php if ($total > 1) { ?>
    <div class="archive-pagination reveal">
      <span class="archive-pagination-info">
        Plate <?= sprintf('%02d', $current) ?> / <?= sprintf('%02d', $total) ?> &mdash; entries <?= sprintf('%02d', $first) ?>&ndash;<?= sprintf('%02d', $last) ?> of <?= sprintf('%02d', $totalPosts) ?>
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
