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
  $km = round($minutes * 5 / 60, 1);
  $standfirst = trim((string) ($post['excerpt'] ?? ''));
  ?>

<div class="trail-ruler" aria-hidden="true"><span id="trail-ruler-fill"></span></div>

<section class="post-hero">
  <div class="container">
    <div class="post-hero-meta">
      <?php if ($cat) { ?>
        <a class="wp-chip" href="<?= lurl('/blog/'.$blogSlug.'/category/'.urlencode((string) $catSlug)) ?>"><?= e($cat) ?></a>
      <?php } ?>
      <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
      <?php $metaParams = ['minutes' => (int) $minutes, 'km' => e((string) $km)]; ?>
      <span<?= $wording->attrs('closest::post.meta', $metaParams) ?>><?= $wording->html('closest::post.meta', $metaParams) ?></span>
    </div>

    <h1 class="post-hero-title reveal" data-edit-title><?= $title ?></h1>

    <?php if ($standfirst !== '') { ?>
      <p class="post-standfirst reveal"><?= e($standfirst) ?></p>
    <?php } ?>

    <div class="post-hero-byline">
      <?php if ($author) { ?><span<?= $wording->attrs('closest::post.by', ['author' => $author]) ?>><?= $wording->html('closest::post.by', ['author' => $author]) ?></span><?php } ?>
      <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="link-arrow">
        <span<?= $wording->attrs('closest::post.backTo', ['blog' => e($blog['blog_name'] ?? '')]) ?>><?= $wording->html('closest::post.backTo', ['blog' => e($blog['blog_name'] ?? '')]) ?></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
    {% slot "post.header-aside" %}
  </div>
</section>

<?php if ($heroImg || !empty($edit_in_place)) { ?>
<div class="container">
  <div class="inset post-cover" id="post-cover" data-edit-cover<?= $heroImg ? '' : ' hidden' ?>>
    <span class="inset-img" data-edit-cover-slot><?php if ($heroImg) { ?><img src="<?= $heroImg ?>" alt="<?= $title ?>" loading="eager" decoding="async" /><?php } ?></span>
    <span class="inset-cap"<?= $wording->attrs('closest::post.fig', ['title' => $title]) ?>><?= $wording->html('closest::post.fig', ['title' => $title]) ?></span>
  </div>
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
      <span class="post-tags-label"<?= $wording->attrs('closest::post.marked') ?>><?= $wording->html('closest::post.marked') ?></span>
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
        <span class="post-nav-label"<?= $wording->attrs('closest::post.previous') ?>><?= $wording->html('closest::post.previous') ?></span>
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($prev_post['slug'] ?? '')) ?>" class="post-nav-title">
          <?= e($prev_post['title'] ?? 'Previous') ?>
        </a>
      </div>
      <?php } else { ?><div></div><?php } ?>

      <?php if (!empty($next_post)) { ?>
      <div class="post-nav-item next">
        <span class="post-nav-label"<?= $wording->attrs('closest::post.next') ?>><?= $wording->html('closest::post.next') ?></span>
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
          'heading' => 'closest::comments.heading',
          'form_label' => 'closest::comments.formLabel',
          'form_button' => 'closest::comments.formButton',
          'closed' => 'closest::comments.closed',
      ]; ?>
      {% include "partials/public/_comments.lex.php" %}
    </div>
  </div>
</section>

<?php if (!empty($related) && is_array($related)) { ?>
<section class="post-related" data-insights-related>
  <div class="container">
    <h2 class="reveal"<?= $wording->attrs('closest::post.related') ?>><?= $wording->html('closest::post.related') ?></h2>
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
        <a href="<?= $rUrl ?>" class="inset related-inset">
          <span class="inset-img"><img src="<?= $rImg ?>" alt="<?= $rTitle ?>" loading="lazy" /></span>
        </a>
        <?php } ?>
        <h3 class="related-title"><a href="<?= $rUrl ?>"><?= $rTitle ?></a></h3>
        <?php if ($rExc) { ?><p class="related-exc"><?= $rExc ?></p><?php } ?>
        <a href="<?= $rUrl ?>" class="link-arrow">
          <span<?= $wording->attrs('closest::post.read') ?>><?= $wording->html('closest::post.read') ?></span>
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
