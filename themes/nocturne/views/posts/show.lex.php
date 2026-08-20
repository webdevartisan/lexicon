{% extends "base.lex.php" %}
{% block title %}{{ meta.title }}{% endblock %}

{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/post.css') ?>">
{% endblock %}

{% block content %}
<?php
$title = e($post['title'] ?? 'Untitled');
  $date = e($post['published_at'] ?? '');
  $author = profile_link(
      $post['author_name'] ?? ($user['display_name_cached'] ?? $user['username'] ?? ''),
      $user['public_profile_slug'] ?? null
  );
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
      <span><?= $minutes ?> Min read</span>
    </div>

    <h1 class="post-hero-title reveal"><?= $title ?></h1>

    <div class="post-hero-byline">
      <?php if ($author) { ?><span>By <?= $author ?></span><?php } ?>
      <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="link-arrow">
        Back to <?= e($blog['blog_name'] ?? 'Blog') ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>

<?php if ($heroImg) { ?>
<div class="post-cover img-veil">
  <img src="<?= $heroImg ?>" alt="<?= $title ?>" loading="eager" decoding="async" />
</div>
<?php } ?>

<div class="container">
  <div class="post-layout">
    <div class="post-content reveal">
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
        <span class="post-nav-label">&larr; Previous</span>
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($prev_post['slug'] ?? '')) ?>" class="post-nav-title">
          <?= e($prev_post['title'] ?? 'Previous') ?>
        </a>
      </div>
      <?php } else { ?><div></div><?php } ?>

      <?php if (!empty($next_post)) { ?>
      <div class="post-nav-item next">
        <span class="post-nav-label">Next &rarr;</span>
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
          'form_label' => 'Add a comment&hellip;',
      ]; ?>
      {% include "partials/public/_comments.lex.php" %}
    </div>
  </div>
</section>

<?php if (!empty($related) && is_array($related)) { ?>
<section class="post-related">
  <div class="container">
    <h2 class="reveal">For the small hours</h2>
    <div class="related-grid">
      <?php foreach ($related as $rel) {
          $rTitle = e($rel['title'] ?? 'Post');
          $rSlug = urlencode($rel['slug'] ?? '');
          $rUrl = lurl('/blog/'.$blogSlug.'/'.$rSlug);
          $rCover = $rel['featured_image'] ?? ($rel['cover_url'] ?? null);
          $rImg = $rCover ? e($rCover) : null;
          $rExc = e($rel['excerpt'] ?? '');
          $rMin = reading_time($rel['content'] ?? '');
          ?>
      <div class="related-item reveal">
        <?php if ($rImg) { ?>
        <a href="<?= $rUrl ?>" class="related-image img-veil">
          <img src="<?= $rImg ?>" alt="<?= $rTitle ?>" loading="lazy" />
        </a>
        <?php } ?>
        <span class="related-min"><?= $rMin ?> Min</span>
        <h3 class="related-title"><a href="<?= $rUrl ?>"><?= $rTitle ?></a></h3>
        <?php if ($rExc) { ?><p class="related-exc"><?= $rExc ?></p><?php } ?>
        <a href="<?= $rUrl ?>" class="link-arrow">
          Read
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
