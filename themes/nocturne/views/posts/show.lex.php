{% extends "base.lex.php" %}
{% block title %}{{ meta.title }}{% endblock %}

{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/post.css') ?>">
{% endblock %}

{% block content %}
<?php
$title = e($post['title'] ?? 'Untitled');
  $date = e($post['published_at'] ?? '');
  $author = author_byline($post['author_name'], $post['author_handle'], $post['author_profile_handle']);
  $cat = $post['category'] ?? null;
  $catSlug = $post['category_slug'] ?? null;
  $cover = $post['featured_image'] ?? ($post['cover_url'] ?? null);
  $heroImg = $cover ? e($cover) : null;
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $minutes = reading_time($post['content'] ?? '');
  ?>

<div class="read-progress" aria-hidden="true"><span id="read-progress-bar"></span></div>

<section class="post-hero">
  <div class="container">
    <div class="post-hero-rail">
      <?php if ($cat) { ?>
        <a href="<?= lurl('/blog/'.$blogSlug.'/category/'.urlencode((string) $catSlug)) ?>"><?= e($cat) ?></a>
      <?php } ?>
      <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
      <span<?= $wording->attrs('nocturne::post.minRead', ['count' => (int) $minutes]) ?>><?= $wording->html('nocturne::post.minRead', ['count' => (int) $minutes]) ?></span>
    </div>

    <h1 class="post-hero-title reveal" data-edit-title><?= $title ?></h1>

    <div class="post-hero-byline">
      <?php if ($author) { ?><span><?= translate_html('nocturne::by', ['author' => $author]) ?></span><?php } ?>
      <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="link-arrow">
        <span<?= $wording->attrs('nocturne::post.backTo', ['blog' => e($blog['blog_name'] ?? '')]) ?>><?= $wording->html('nocturne::post.backTo', ['blog' => e($blog['blog_name'] ?? '')]) ?></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
    {% slot "post.header-aside" %}
  </div>
</section>

<?php if ($heroImg || !empty($edit_in_place)) { ?>
<div class="post-cover img-veil" data-edit-cover<?= $heroImg ? '' : ' hidden' ?>>
  <?php if ($heroImg) { ?>
  <img src="<?= $heroImg ?>" alt="<?= $title ?>" loading="eager" decoding="async" />
  <?php } ?>
</div>
<?php } ?>

<div class="container">
  <div class="post-layout">
    <div class="post-content reveal" data-edit-body>
      <?= $post['content'] ?? '' ?>
    </div>
    {% slot "post.after-content" %}

    <?php if (!empty($post['tags']) && is_array($post['tags'])) { ?>
    <div class="post-tags reveal">
      <?php foreach ($post['tags'] as $tag) { ?>
        <a class="post-tag" href="<?= lurl('/blog/'.$blogSlug.'/tag/'.urlencode((string) $tag['slug'])) ?>"><?= e($tag['name']) ?></a>
      <?php } ?>
    </div>
    <?php } ?>

    {% include "partials/_engagement.lex.php" %}
  </div>
</div>

<?php if (!empty($prev_post) || !empty($next_post)) { ?>
<section class="post-nav">
  <div class="container">
    <div class="inner">
      <?php if (!empty($prev_post)) { ?>
      <div class="post-nav-item prev">
        <span class="post-nav-label"<?= $wording->attrs('nocturne::post.previous') ?>><?= $wording->html('nocturne::post.previous') ?></span>
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($prev_post['slug'] ?? '')) ?>" class="post-nav-title">
          <?= e($prev_post['title'] ?? 'Previous') ?>
        </a>
      </div>
      <?php } else { ?><div></div><?php } ?>

      <?php if (!empty($next_post)) { ?>
      <div class="post-nav-item next">
        <span class="post-nav-label"<?= $wording->attrs('nocturne::post.next') ?>><?= $wording->html('nocturne::post.next') ?></span>
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($next_post['slug'] ?? '')) ?>" class="post-nav-title">
          <?= e($next_post['title'] ?? 'Next') ?>
        </a>
      </div>
      <?php } ?>
    </div>
  </div>
</section>
<?php } ?>

<section class="post-comments">
  <div class="container">
    <div class="inner">
      {% include "partials/public/_comments.lex.php" %}
    </div>
  </div>
</section>

<?php if (!empty($related) && is_array($related)) { ?>
<section class="post-related" data-insights-related>
  <div class="container">
    <h2 class="reveal"<?= $wording->attrs('nocturne::post.related') ?>><?= $wording->html('nocturne::post.related') ?></h2>
    <div class="related-grid">
      <?php foreach ($related as $rel) {
          $rTitle = e($rel['title'] ?? 'Post');
          $rSlug = urlencode($rel['slug'] ?? '');
          $rUrl = lurl('/blog/'.$blogSlug.'/'.$rSlug);
          $rCover = $rel['featured_image'] ?? ($rel['cover_url'] ?? null);
          $rImg = $rCover ? e($rCover) : null;
          $rExc = e(post_excerpt($rel, 140));
          $rMin = reading_time($rel['content'] ?? '');
          ?>
      <div class="related-item reveal">
        <?php if ($rImg) { ?>
        <a href="<?= $rUrl ?>" class="related-image img-veil">
          <img src="<?= $rImg ?>" alt="<?= $rTitle ?>" loading="lazy" />
        </a>
        <?php } ?>
        <span class="related-min"><?= e($t('nocturne::minutes', ['count' => $rMin])) ?></span>
        <h3 class="related-title"><a href="<?= $rUrl ?>"><?= $rTitle ?></a></h3>
        <?php if ($rExc) { ?><p class="related-exc"><?= $rExc ?></p><?php } ?>
        <a href="<?= $rUrl ?>" class="link-arrow">
          <span<?= $wording->attrs('nocturne::post.read') ?>><?= $wording->html('nocturne::post.read') ?></span>
          <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>
      </div>
      <?php } ?>
    </div>
  </div>
</section>
<?php } ?>

{% include "partials/public/_edit_in_place.lex.php" %}
{% endblock %}

{% block scripts %}
  <script defer src="<?= $asset('js/post.js') ?>"></script>
{% endblock %}
