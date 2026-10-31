{% extends "base.lex.php" %}
{% block title %}<?= e($t('folio::archive.pageTitle', ['blog' => $blog['blog_name'] ?? ''])) ?>{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/archive.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'FOLIO');
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
  // Headings are overridable so this same layout backs the full archive and the
  // category/tag listings. Defaults keep the plain "/archive" page unchanged.
  // Only the full archive's own wording can be changed on the page; listings bring their own.
  $kicker = $archiveKicker ?? $wording->html('folio::archive.kicker', ['blog' => $blogTitle]);
  $kickerAttrs = isset($archiveKicker) ? '' : $wording->attrs('folio::archive.kicker', ['blog' => $blogTitle]);
  $heading = $archiveTitle ?? $wording->html('folio::archive.heading');
  $headingAttrs = isset($archiveTitle) ? '' : $wording->attrs('folio::archive.heading');
  $dek = $archiveDek ?? $wording->html('folio::archive.dek', ['blog' => '<em>'.$blogTitle.'</em>']);
  $dekAttrs = isset($archiveDek) ? '' : $wording->attrs('folio::archive.dek', ['blog' => '<em>'.$blogTitle.'</em>']);
  ?>
<section class="archive-hero">
  <div class="container">
    <div class="archive-meta-top">
      <span<?= $kickerAttrs ?>><?= $kicker ?></span>
      <span><?= e(translate_count('folio::home.postCount', $totalPosts)) ?></span>
    </div>

    <h1 class="archive-title"<?= $headingAttrs ?>><?= $heading ?></h1>

    <p class="archive-dek"<?= $dekAttrs ?>><?= $dek ?></p>

    {% include "partials/public/_blog_search.lex.php" %}

    <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="archive-back">
      <span<?= $wording->attrs('folio::archive.back') ?>><?= $wording->html('folio::archive.back') ?></span>
    </a>
  </div>
</section>

<section class="articles">
  <div class="container">
    <?php if (!empty($posts)) { ?>
    <div class="articles-grid">
      <?php foreach ($posts as $i => $post) {
          $cover = (string) ($post['featured_image'] ?? '');
          $img = preg_match($validImg, $cover) ? e($cover) : $asset('img/cover-placeholder.svg');
          $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
          $title = e($post['title'] ?? 'Untitled');
          $cat = trim((string) ($post['category'] ?? $t('folio::archive.fallbackCategory')));
          $date = e(local_datetime($post['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
          $author = author_byline($post['author_name'], $post['author_handle'], $post['author_profile_handle']);
          $minutes = reading_time($post['content'] ?? '');
          $n = $first + $i;
          ?>
      <article class="article reveal" data-card-link="<?= e($url) ?>">
        <div class="article-meta">
          <span class="cat"><?= sprintf('%02d', $n) ?> &middot; <?= e($cat) ?></span>
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
          <span><?= e($t('folio::minutes', ['count' => $minutes])) ?></span>
        </div>
      </article>
      <?php } ?>
    </div>
    <?php } else { ?>
      <p style="font-family:var(--mono);text-transform:uppercase;letter-spacing:0.18em;color:var(--muted);"<?= $wording->attrs('folio::archive.empty') ?>><?= $wording->html('folio::archive.empty') ?></p>
    <?php } ?>

    <?php if ($total > 1) { ?>
    <div class="archive-pagination reveal">
      <span class="archive-pagination-info">
        <?php $showingParams = ['from' => sprintf('%02d', $first), 'to' => sprintf('%02d', $last), 'total' => sprintf('%02d', $totalPosts)]; ?>
        <span<?= $wording->attrs('folio::archive.showing', $showingParams) ?>><?= $wording->html('folio::archive.showing', $showingParams) ?></span>
      </span>
      <nav class="archive-pagination-nav" aria-label="<?= e($t('folio::archive.pagination')) ?>">
        <?php if ($current > 1) { ?>
          <a href="?page=<?= $current - 1 ?>" rel="prev"><span<?= $wording->attrs('folio::archive.prev') ?>><?= $wording->html('folio::archive.prev') ?></span></a>
        <?php } else { ?>
          <span class="is-disabled"<?= $wording->attrs('folio::archive.prev') ?>><?= $wording->html('folio::archive.prev') ?></span>
        <?php } ?>

        <?php for ($p = 1; $p <= $total; $p++) { ?>
          <?php if ($p === $current) { ?>
            <span class="is-current"><?= sprintf('%02d', $p) ?></span>
          <?php } else { ?>
            <a href="?page=<?= $p ?>"><?= sprintf('%02d', $p) ?></a>
          <?php } ?>
        <?php } ?>

        <?php if ($current < $total) { ?>
          <a href="?page=<?= $current + 1 ?>" rel="next"><span<?= $wording->attrs('folio::archive.next') ?>><?= $wording->html('folio::archive.next') ?></span></a>
        <?php } else { ?>
          <span class="is-disabled"<?= $wording->attrs('folio::archive.next') ?>><?= $wording->html('folio::archive.next') ?></span>
        <?php } ?>
      </nav>
    </div>
    <?php } ?>
  </div>
</section>
{% include "partials/public/_edit_texts.lex.php" %}
{% endblock %}
