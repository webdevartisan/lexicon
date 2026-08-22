{% extends "base.lex.php" %}
{% block title %}Route log &mdash; {{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/archive.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'Closest');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');

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
  // Headings are overridable so this same layout backs the full route log and
  // the category/tag listings. Defaults keep the plain "/archive" page intact.
  $kicker = $archiveKicker ?? ('The route log &mdash; '.$blogTitle);
  $heading = $archiveTitle ?? 'Every waypoint, plotted.';
  $dek = $archiveDek ?? ('The full survey of <em>'.$blogTitle.'</em>, newest waypoint first.');
  ?>
<section class="log-hero">
  <div class="container">
    <div class="log-meta-top">
      <span><?= $kicker ?></span>
      <span><?= $totalPosts ?> Waypoint<?= $totalPosts === 1 ? '' : 's' ?></span>
    </div>

    <h1 class="log-title"><?= $heading ?></h1>

    <p class="log-dek">
      <?= $dek ?>
    </p>

    <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="log-back">
      &larr; Back to the trailhead
    </a>
  </div>
</section>

<section class="log">
  <div class="container">
    <?php if (!empty($posts)) { ?>
      <?php
        // Year headings break the log into seasons so long surveys still scan.
        $lastYear = null;
        ?>
      <div class="route-wrap">
        <div class="route-rail" aria-hidden="true"><span id="route-rail-fill"></span></div>
        <div class="log-rows">
        <?php foreach ($posts as $i => $post) {
            $cover = (string) ($post['featured_image'] ?? '');
            $img = preg_match($validImg, $cover) ? $cover : 'https://picsum.photos/seed/closest-log-'.$post['id'].'/900/680';
            $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
            $title = e($post['title'] ?? 'Untitled');
            $cat = trim((string) ($post['category'] ?? 'Field note'));
            $author = profile_link($post['author_name'], $post['author_profile_slug']);
            $minutes = reading_time($post['content'] ?? '');
            $n = $first + $i;

            $tz = blog_timezone((int) ($blog['id'] ?? 0));
            $date = e(local_datetime($post['published_at'] ?? null, 'j M Y', $tz));
            $year = local_datetime($post['published_at'] ?? null, 'Y', $tz);
            ?>
          <?php if ($year !== '' && $year !== $lastYear) {
              $lastYear = $year;
              ?>
          <h2 class="log-year reveal"><span>Season</span> <?= e($year) ?></h2>
          <?php } ?>

          <article class="waypoint reveal" data-card-link="<?= e($url) ?>">
            <span class="wp-node" aria-hidden="true"></span>
            <span class="wp-no">W-<?= sprintf('%02d', $n) ?></span>

            <span class="inset wp-thumb" aria-hidden="true">
              <span class="inset-img"><img src="<?= e($img) ?>" alt="" loading="lazy" /></span>
            </span>

            <div class="wp-main">
              <h3 class="wp-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
              <span class="wp-author">By <?= $author ?></span>
            </div>

            <div class="wp-meta">
              <span class="wp-chip"><?= e($cat) ?></span>
              <?php if ($date) { ?><span class="wp-date"><?= $date ?></span><?php } ?>
              <span class="wp-min"><?= $minutes ?> Min</span>
            </div>

            <span class="wp-arrow" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </span>
          </article>
        <?php } ?>
        </div>
      </div>
    <?php } else { ?>
      <p class="log-empty">No waypoints plotted yet.</p>
    <?php } ?>

    <?php if ($total > 1) { ?>
    <div class="log-pagination reveal">
      <span class="log-pagination-info">
        Leg <?= sprintf('%02d', $current) ?> / <?= sprintf('%02d', $total) ?> &mdash; waypoints <?= sprintf('%02d', $first) ?>&ndash;<?= sprintf('%02d', $last) ?> of <?= sprintf('%02d', $totalPosts) ?>
      </span>
      <nav class="log-pagination-nav" aria-label="Pagination">
        <?php if ($current > 1) { ?>
          <a href="?page=<?= $current - 1 ?>" rel="prev">&larr; Prev leg</a>
        <?php } else { ?>
          <span class="is-disabled">&larr; Prev leg</span>
        <?php } ?>

        <?php for ($p = 1; $p <= $total; $p++) { ?>
          <?php if ($p === $current) { ?>
            <span class="is-current"><?= sprintf('%02d', $p) ?></span>
          <?php } else { ?>
            <a href="?page=<?= $p ?>"><?= sprintf('%02d', $p) ?></a>
          <?php } ?>
        <?php } ?>

        <?php if ($current < $total) { ?>
          <a href="?page=<?= $current + 1 ?>" rel="next">Next leg &rarr;</a>
        <?php } else { ?>
          <span class="is-disabled">Next leg &rarr;</span>
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
