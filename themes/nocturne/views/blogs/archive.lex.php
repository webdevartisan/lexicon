{% extends "base.lex.php" %}
{% block title %}<?= e($t('nocturne::archive.pageTitle', ['blog' => $blog['blog_name'] ?? ''])) ?>{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/archive.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'Nocturne');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');

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
  // Only the full archive's own wording can be changed on the page; listings bring their own.
  $kicker = $archiveKicker ?? $wording->html('nocturne::archive.kicker', ['blog' => $blogTitle]);
  $kickerAttrs = isset($archiveKicker) ? '' : $wording->attrs('nocturne::archive.kicker', ['blog' => $blogTitle]);
  $heading = $archiveTitle ?? $wording->html('nocturne::archive.heading');
  $headingAttrs = isset($archiveTitle) ? '' : $wording->attrs('nocturne::archive.heading');
  $dek = $archiveDek ?? $wording->html('nocturne::archive.dek', ['blog' => '<em>'.$blogTitle.'</em>']);
  $dekAttrs = isset($archiveDek) ? '' : $wording->attrs('nocturne::archive.dek', ['blog' => '<em>'.$blogTitle.'</em>']);
  ?>

<section class="ledger-hero">
  <div class="container">
    <div class="ledger-rail">
      <span<?= $kickerAttrs ?>><?= $kicker ?></span>
      <span><?= e(translate_count('nocturne::home.postCount', $totalPosts)) ?></span>
    </div>

    <h1 class="ledger-title"<?= $headingAttrs ?>><?= $heading ?></h1>

    <p class="ledger-dek"<?= $dekAttrs ?>><?= $dek ?></p>

    {% include "partials/public/_blog_search.lex.php" %}

    <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="ledger-back link-arrow">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M19 12H5M11 18l-6-6 6-6" /></svg>
      <span<?= $wording->attrs('nocturne::archive.back') ?>><?= $wording->html('nocturne::archive.back') ?></span>
    </a>
  </div>
</section>

<section class="ledger">
  <div class="container">
    <?php if (!empty($posts)) { ?>
    <div class="idx-list">
      <?php foreach ($posts as $i => $post) {
          $cover = (string) ($post['featured_image'] ?? '');
          $img = preg_match($validImg, $cover) ? e($cover) : $asset('img/cover-placeholder.svg');
          $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
          $title = e($post['title'] ?? 'Untitled');
          $cat = trim((string) ($post['category'] ?? $t('nocturne::archive.fallbackCategory')));
          $date = reader_date($post['published_at'] ?? null, 'j M Y');
          $author = author_byline($post['author_name'], $post['author_handle'], $post['author_profile_handle']);
          $minutes = reading_time($post['content'] ?? '');
          $n = $first + $i;
          ?>
      <article class="idx-row reveal" data-cover="<?= $img ?>" data-card-link="<?= e($url) ?>">
        <span class="idx-num"><?= sprintf('%02d', $n) ?></span>
        <h3 class="idx-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
        <div class="idx-thumb" aria-hidden="true">
          <img src="<?= $img ?>" alt="" loading="lazy" />
        </div>
        <div class="idx-meta">
          <span class="cat"><?= e($cat) ?></span>
          <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
          <span><?= translate_html('nocturne::archive.byline', ['author' => $author, 'count' => $minutes]) ?></span>
        </div>
      </article>
      <?php } ?>
    </div>

    <div class="idx-float" id="idx-float" aria-hidden="true"><img src="" alt="" /></div>
    <?php } else { ?>
      <p class="ledger-empty"<?= $wording->attrs('nocturne::archive.empty') ?>><?= $wording->html('nocturne::archive.empty') ?></p>
    <?php } ?>

    <?php if ($total > 1) { ?>
    <div class="ledger-pagination reveal">
      <span class="ledger-pagination-info">
        <?php $showingParams = ['from' => sprintf('%02d', $first), 'to' => sprintf('%02d', $last), 'total' => sprintf('%02d', $totalPosts)]; ?>
        <span<?= $wording->attrs('nocturne::archive.showing', $showingParams) ?>><?= $wording->html('nocturne::archive.showing', $showingParams) ?></span>
      </span>
      <nav class="ledger-pagination-nav" aria-label="<?= e($t('nocturne::archive.pagination')) ?>">
        <?php if ($current > 1) { ?>
          <a href="?page=<?= $current - 1 ?>" rel="prev"><span<?= $wording->attrs('nocturne::archive.prev') ?>><?= $wording->html('nocturne::archive.prev') ?></span></a>
        <?php } else { ?>
          <span class="is-disabled"<?= $wording->attrs('nocturne::archive.prev') ?>><?= $wording->html('nocturne::archive.prev') ?></span>
        <?php } ?>

        <?php for ($p = 1; $p <= $total; $p++) { ?>
          <?php if ($p === $current) { ?>
            <span class="is-current"><?= sprintf('%02d', $p) ?></span>
          <?php } else { ?>
            <a href="?page=<?= $p ?>"><?= sprintf('%02d', $p) ?></a>
          <?php } ?>
        <?php } ?>

        <?php if ($current < $total) { ?>
          <a href="?page=<?= $current + 1 ?>" rel="next"><span<?= $wording->attrs('nocturne::archive.next') ?>><?= $wording->html('nocturne::archive.next') ?></span></a>
        <?php } else { ?>
          <span class="is-disabled"<?= $wording->attrs('nocturne::archive.next') ?>><?= $wording->html('nocturne::archive.next') ?></span>
        <?php } ?>
      </nav>
    </div>
    <?php } ?>
  </div>
</section>
{% include "partials/public/_edit_texts.lex.php" %}
{% endblock %}

{% block scripts %}
  <script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
