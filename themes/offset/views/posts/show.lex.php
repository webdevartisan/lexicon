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
  $standfirst = trim((string) ($post['excerpt'] ?? ''));
  ?>

<div class="press-progress" aria-hidden="true"><span id="press-progress-bar"></span></div>

<section class="post-hero">
  <div class="container">
    <div class="post-hero-meta">
      <?php if ($cat) { ?>
        <a href="<?= lurl('/blog/'.$blogSlug.'/category/'.urlencode((string) $catSlug)) ?>"><?= e($cat) ?></a>
      <?php } ?>
      <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
      <span><?= e($t('offset::minutes', ['count' => $minutes])) ?></span>
    </div>

    <h1 class="post-hero-title reveal" data-edit-title><?= $title ?></h1>

    <?php if ($standfirst !== '') { ?>
      <p class="post-standfirst reveal"><?= e($standfirst) ?></p>
    <?php } ?>

    <div class="post-hero-byline">
      <?php if ($author) { ?><span><?= translate_html('offset::by', ['author' => $author]) ?></span><?php } ?>
      <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="link-arrow">
        <?= e($t('offset::post.backTo', ['blog' => $blog['blog_name'] ?? ''])) ?>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>

<?php if ($heroImg || !empty($edit_in_place)) { ?>
<div class="container">
  <div class="plate post-cover" id="post-cover" data-edit-cover<?= $heroImg ? '' : ' hidden' ?>>
    <span class="plate-img" data-edit-cover-slot><?php if ($heroImg) { ?><img src="<?= $heroImg ?>" alt="<?= $title ?>" loading="eager" decoding="async" /><?php } ?></span>
  </div>
</div>
<?php } ?>

<div class="container">
  <div class="post-layout">
    <div class="post-content reveal" data-edit-body>
      <?= $post['content'] ?? '' ?>
    </div>

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
        <span class="post-nav-label"><?= e($t('offset::post.previous')) ?></span>
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($prev_post['slug'] ?? '')) ?>" class="post-nav-title">
          <?= e($prev_post['title'] ?? 'Previous') ?>
        </a>
      </div>
      <?php } else { ?><div></div><?php } ?>

      <?php if (!empty($next_post)) { ?>
      <div class="post-nav-item next">
        <span class="post-nav-label"><?= e($t('offset::post.next')) ?></span>
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
      <?php $comment_labels = [
          'heading' => 'offset::comments.heading',
          'form_label' => 'offset::comments.formLabel',
          'form_button' => 'offset::comments.formButton',
      ]; ?>
      {% include "partials/public/_comments.lex.php" %}
    </div>
  </div>
</section>

<?php if (!empty($related) && is_array($related)) { ?>
<section class="post-related" data-insights-related>
  <div class="container">
    <h2 class="reveal"><?= e($t('offset::post.related')) ?></h2>
    <div class="related-grid">
      <?php foreach ($related as $rel) {
          $rTitle = e($rel['title'] ?? 'Post');
          $rSlug = urlencode($rel['slug'] ?? '');
          $rUrl = lurl('/blog/'.$blogSlug.'/'.$rSlug);
          $rCover = $rel['featured_image'] ?? ($rel['cover_url'] ?? null);
          $rImg = $rCover ? e($rCover) : null;
          $rExc = e(post_excerpt($rel, 140));
          ?>
      <div class="related-item reveal">
        <?php if ($rImg) { ?>
        <a href="<?= $rUrl ?>" class="plate related-plate">
          <span class="plate-img"><img src="<?= $rImg ?>" alt="<?= $rTitle ?>" loading="lazy" /></span>
        </a>
        <?php } ?>
        <h3 class="related-title"><a href="<?= $rUrl ?>"><?= $rTitle ?></a></h3>
        <?php if ($rExc) { ?><p class="related-exc"><?= $rExc ?></p><?php } ?>
        <a href="<?= $rUrl ?>" class="link-arrow">
          <?= e($t('offset::post.read')) ?>
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
