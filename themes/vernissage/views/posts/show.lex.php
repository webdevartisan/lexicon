{% extends "base.lex.php" %}
{% block title %}{{ meta.title }}{% endblock %}

{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/post.css') ?>">
{% endblock %}

{% block content %}
<?php
$title = e($post['title'] ?? 'Untitled');
  $date = e($post['published_at'] ?? '');
  $author = profile_link($post['author_name'], $post['author_profile_slug']);
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
      <span><?= $minutes ?> Min</span>
    </div>

    <h1 class="work-hero-title reveal"><?= $title ?></h1>

    <?php if ($standfirst !== '') { ?>
      <p class="work-standfirst reveal"><?= e($standfirst) ?></p>
    <?php } ?>

    <div class="work-hero-byline">
      <?php if ($author) { ?><span class="work-artist"><?= $author ?><?= $date ? ', '.e(local_datetime($post['published_at_raw'] ?? null, 'Y', blog_timezone((int) ($blog['id'] ?? 0)))) : '' ?></span><?php } ?>
      <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="link-arrow">
        Back to <?= e($blog['blog_name'] ?? 'the exhibition') ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>

<?php if ($heroImg) { ?>
<div class="container">
  <figure class="work-figure" id="work-figure">
    <span class="frame work-cover"><span class="frame-img"><img src="<?= $heroImg ?>" alt="<?= $title ?>" loading="eager" decoding="async" /></span></span>
    <figcaption class="work-figcap">Fig. I &mdash; <?= $title ?></figcaption>
  </figure>
</div>
<?php } ?>

<div class="container">
  <div class="work-layout">
    <div class="work-essay reveal">
      <?= $post['content'] ?? '' ?>
    </div>

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
        <span class="work-nav-label">&larr; Previous work</span>
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($prev_post['slug'] ?? '')) ?>" class="work-nav-title">
          <?= e($prev_post['title'] ?? 'Previous') ?>
        </a>
      </div>
      <?php } else { ?><div></div><?php } ?>

      <?php if (!empty($next_post)) { ?>
      <div class="work-nav-item next">
        <span class="work-nav-label">Next work &rarr;</span>
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
          'title_prefix' => 'The Visitors&rsquo; Book &mdash; ',
          'noun' => 'Entry',
          'noun_plural' => 'Entries',
          'form_label' => 'Leave a note for the gallery&hellip;',
          'form_button' => 'Sign the book',
          'closed' => 'The visitors&rsquo; book is closed for this work.',
      ]; ?>
      {% include "partials/public/_comments.lex.php" %}
    </div>
  </div>
</section>

<?php if (!empty($related) && is_array($related)) { ?>
<section class="work-related">
  <div class="container">
    <h2 class="reveal">Also on view</h2>
    <div class="related-grid">
      <?php foreach ($related as $rel) {
          $rTitle = e($rel['title'] ?? 'Work');
          $rSlug = urlencode($rel['slug'] ?? '');
          $rUrl = lurl('/blog/'.$blogSlug.'/'.$rSlug);
          $rCover = $rel['featured_image'] ?? ($rel['cover_url'] ?? null);
          $rImg = $rCover ? e($rCover) : null;
          $rExc = e($rel['excerpt'] ?? '');
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
          View
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>
      </div>
      <?php } ?>
    </div>
  </div>
</section>
<?php } ?>
{% endblock %}

{% block scripts %}
	<script defer src="<?= $asset('js/post.js') ?>"></script>
{% endblock %}
