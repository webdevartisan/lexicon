{% extends "base.lex.php" %}
{% block title %}<?= e($t('vernissage::archive.pageTitle', ['blog' => $blog['blog_name'] ?? ''])) ?>{% endblock %}
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
  // Only the full archive's own wording can be changed on the page; listings bring their own.
  $kicker = $archiveKicker ?? $wording->html('vernissage::archive.kicker', ['blog' => $blogTitle]);
  $kickerAttrs = isset($archiveKicker) ? '' : $wording->attrs('vernissage::archive.kicker', ['blog' => $blogTitle]);
  $heading = $archiveTitle ?? $wording->html('vernissage::archive.heading');
  $headingAttrs = isset($archiveTitle) ? '' : $wording->attrs('vernissage::archive.heading');
  $dek = $archiveDek ?? $wording->html('vernissage::archive.dek', ['blog' => '<em>'.$blogTitle.'</em>']);
  $dekAttrs = isset($archiveDek) ? '' : $wording->attrs('vernissage::archive.dek', ['blog' => '<em>'.$blogTitle.'</em>']);
  ?>
<section class="archive-hero">
  <div class="container">
    <div class="archive-meta-top">
      <span<?= $kickerAttrs ?>><?= $kicker ?></span>
      <span><?= e(translate_count('vernissage::archive.count', $totalPosts)) ?></span>
    </div>

    <h1 class="archive-title"<?= $headingAttrs ?>><?= $heading ?></h1>

    <p class="archive-dek"<?= $dekAttrs ?>><?= $dek ?></p>

    {% include "partials/public/_blog_search.lex.php" %}

    <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="archive-back">
      <span<?= $wording->attrs('vernissage::archive.back') ?>><?= $wording->html('vernissage::archive.back') ?></span>
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
          $img = preg_match($validImg, $cover) ? $cover : $asset('img/cover-placeholder.svg');
          $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
          $title = e($post['title'] ?? 'Untitled');
          $cat = trim((string) ($post['category'] ?? $t('vernissage::home.fallbackCategory')));
          $date = e(local_datetime($post['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
          $author = author_byline($post['author_name'], $post['author_handle'], $post['author_profile_handle']);
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
          <span class="row-no"><?= e($t('vernissage::home.catNo', ['number' => sprintf('%02d', $n)])) ?></span>

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
            <span class="row-min"><?= e($t('vernissage::minutes', ['count' => $minutes])) ?></span>
          </div>

          <span class="row-arrow" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 17L17 7M9 7h8v8"/></svg>
          </span>
        </article>
      <?php } ?>
      </div>
    <?php } else { ?>
      <p class="catalogue-empty"<?= $wording->attrs('vernissage::archive.empty') ?>><?= $wording->html('vernissage::archive.empty') ?></p>
    <?php } ?>

    <?php if ($total > 1) { ?>
    <div class="archive-pagination reveal">
      <span class="archive-pagination-info">
        <?php $plateParams = ['page' => sprintf('%02d', $current), 'pages' => sprintf('%02d', $total), 'from' => sprintf('%02d', $first), 'to' => sprintf('%02d', $last), 'total' => sprintf('%02d', $totalPosts)]; ?>
        <span<?= $wording->attrs('vernissage::archive.plate', $plateParams) ?>><?= $wording->html('vernissage::archive.plate', $plateParams) ?></span>
      </span>
      <nav class="archive-pagination-nav" aria-label="<?= e($t('vernissage::archive.pagination')) ?>">
        <?php if ($current > 1) { ?>
          <a href="?page=<?= $current - 1 ?>" rel="prev"><span<?= $wording->attrs('vernissage::archive.prev') ?>><?= $wording->html('vernissage::archive.prev') ?></span></a>
        <?php } else { ?>
          <span class="is-disabled"<?= $wording->attrs('vernissage::archive.prev') ?>><?= $wording->html('vernissage::archive.prev') ?></span>
        <?php } ?>

        <?php for ($p = 1; $p <= $total; $p++) { ?>
          <?php if ($p === $current) { ?>
            <span class="is-current"><?= sprintf('%02d', $p) ?></span>
          <?php } else { ?>
            <a href="?page=<?= $p ?>"><?= sprintf('%02d', $p) ?></a>
          <?php } ?>
        <?php } ?>

        <?php if ($current < $total) { ?>
          <a href="?page=<?= $current + 1 ?>" rel="next"><span<?= $wording->attrs('vernissage::archive.next') ?>><?= $wording->html('vernissage::archive.next') ?></span></a>
        <?php } else { ?>
          <span class="is-disabled"<?= $wording->attrs('vernissage::archive.next') ?>><?= $wording->html('vernissage::archive.next') ?></span>
        <?php } ?>
      </nav>
    </div>
    <?php } ?>
  </div>
</section>
{% include "partials/public/_edit_texts.lex.php" %}
{% endblock %}
