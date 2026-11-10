{% extends "base.lex.php" %}
{% block title %}{{ meta.title }}{% endblock %}

{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/post.css') ?>">
{% endblock %}

{% block content %}
<?php
$title = e($post['title'] ?? 'Untitled');
  $date = reader_date($post['published_at_raw'] ?? null, 'jS M Y');
  $author = author_byline($post['author_name'], $post['author_handle'], $post['author_profile_handle']);
  $cat = $post['category'] ?? null;
  $catSlug = $post['category_slug'] ?? null;
  $cover = $post['featured_image'] ?? ($post['cover_url'] ?? null);
  $heroImg = $cover ? e($cover) : null;
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $minutes = reading_time($post['content'] ?? '');
  $standfirst = trim((string) ($post['excerpt'] ?? ''));
  ?>

<div class="tour-progress" aria-hidden="true"><span id="tour-progress-fill"></span></div>

<section class="work-hero">
  <div class="container">
    <div class="work-hero-meta">
      <?php if ($cat) { ?>
        <a href="<?= lurl('/blog/'.$blogSlug.'/category/'.urlencode((string) $catSlug)) ?>"><?= e($cat) ?></a>
      <?php } ?>
      <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
      <span><?= e($t('vernissage::minutes', ['count' => $minutes])) ?></span>
    </div>

    <h1 class="work-hero-title reveal" data-edit-title><?= $title ?></h1>

    <?php if ($standfirst !== '') { ?>
      <p class="work-standfirst reveal"><?= e($standfirst) ?></p>
    <?php } ?>

    <div class="work-hero-byline">
      <?php if ($author) { ?><span class="work-artist"><?= $author ?><?= $date ? ', '.e(local_datetime($post['published_at_raw'] ?? null, 'Y', 'UTC')) : '' ?></span><?php } ?>
      <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="link-arrow">
        <span<?= $wording->attrs('vernissage::post.backTo', ['blog' => e($blog['blog_name'] ?? '')]) ?>><?= $wording->html('vernissage::post.backTo', ['blog' => e($blog['blog_name'] ?? '')]) ?></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
    {% slot "post.header-aside" %}
  </div>
</section>

<?php if ($heroImg || !empty($edit_in_place)) { ?>
<div class="container">
  <figure class="work-figure" id="work-figure" data-edit-cover<?= $heroImg ? '' : ' hidden' ?>>
    <span class="frame work-cover"><span class="frame-img" data-edit-cover-slot><?php if ($heroImg) { ?><img src="<?= $heroImg ?>" alt="<?= $title ?>" loading="eager" decoding="async" /><?php } ?></span></span>
    <?php $figParams = ['title' => $title]; ?>
    <figcaption class="work-figcap"<?= $wording->attrs('vernissage::post.fig', $figParams) ?>><?= $wording->html('vernissage::post.fig', $figParams) ?></figcaption>
  </figure>
</div>
<?php } ?>

<div class="container">
  <div class="work-layout">
    <div class="work-essay reveal" data-edit-body>
      <?= $post['content'] ?? '' ?>
    </div>
    {% slot "post.after-content" %}

    <?php if (!empty($post['tags']) && is_array($post['tags'])) { ?>
    <div class="work-tags reveal">
      <?php foreach ($post['tags'] as $tag) { ?>
        <a class="work-tag-chip" href="<?= lurl('/blog/'.$blogSlug.'/tag/'.urlencode((string) $tag['slug'])) ?>"><?= e($tag['name']) ?></a>
      <?php } ?>
    </div>
    <?php } ?>

    {% include "partials/_engagement.lex.php" %}
  </div>
</div>

<?php if (!empty($prev_post) || !empty($next_post)) { ?>
<section class="work-nav">
  <div class="container">
    <div class="inner">
      <?php if (!empty($prev_post)) { ?>
      <div class="work-nav-item prev">
        <span class="work-nav-label"<?= $wording->attrs('vernissage::post.previous') ?>><?= $wording->html('vernissage::post.previous') ?></span>
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($prev_post['slug'] ?? '')) ?>" class="work-nav-title">
          <?= e($prev_post['title'] ?? 'Previous') ?>
        </a>
      </div>
      <?php } else { ?><div></div><?php } ?>

      <?php if (!empty($next_post)) { ?>
      <div class="work-nav-item next">
        <span class="work-nav-label"<?= $wording->attrs('vernissage::post.next') ?>><?= $wording->html('vernissage::post.next') ?></span>
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($next_post['slug'] ?? '')) ?>" class="work-nav-title">
          <?= e($next_post['title'] ?? 'Next') ?>
        </a>
      </div>
      <?php } ?>
    </div>
  </div>
</section>
<?php } ?>

<section class="work-comments">
  <div class="container">
    <div class="inner">
      <?php $comment_labels = [
          'heading' => 'vernissage::comments.heading',
          'form_label' => 'vernissage::comments.formLabel',
          'form_button' => 'vernissage::comments.formButton',
          'closed' => 'vernissage::comments.closed',
      ]; ?>
      {% include "partials/public/_comments.lex.php" %}
    </div>
  </div>
</section>

<?php if (!empty($related) && is_array($related)) { ?>
<section class="work-related" data-insights-related>
  <div class="container">
    <h2 class="reveal"<?= $wording->attrs('vernissage::post.related') ?>><?= $wording->html('vernissage::post.related') ?></h2>
    <div class="related-grid">
      <?php foreach ($related as $rel) {
          $rTitle = e($rel['title'] ?? 'Work');
          $rSlug = urlencode($rel['slug'] ?? '');
          $rUrl = lurl('/blog/'.$blogSlug.'/'.$rSlug);
          $rCover = $rel['featured_image'] ?? ($rel['cover_url'] ?? null);
          $rImg = $rCover ? e($rCover) : null;
          $rExc = e(post_excerpt($rel, 140));
          ?>
      <div class="related-item reveal">
        <?php if ($rImg) { ?>
        <a href="<?= $rUrl ?>" class="frame related-frame">
          <span class="frame-img"><img src="<?= $rImg ?>" alt="<?= $rTitle ?>" loading="lazy" /></span>
        </a>
        <?php } ?>
        <h3 class="related-title"><a href="<?= $rUrl ?>"><?= $rTitle ?></a></h3>
        <?php if ($rExc) { ?><p class="related-exc"><?= $rExc ?></p><?php } ?>
        <a href="<?= $rUrl ?>" class="link-arrow">
          <span<?= $wording->attrs('vernissage::post.read') ?>><?= $wording->html('vernissage::post.read') ?></span>
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
