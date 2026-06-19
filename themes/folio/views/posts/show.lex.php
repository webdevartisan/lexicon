{% extends "base.lex.php" %}
{% block title %}{{ post.title }} &mdash; {{ blog.blog_name }}{% endblock %}

{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/post.css') ?>">
{% endblock %}

{% block content %}
<?php
$title = e($post['title'] ?? 'Untitled');
  $date = e($post['published_at'] ?? '');
  $author = e($post['author_name'] ?? ($user['display_name_cached'] ?? $user['username'] ?? ''));
  $cat = e($post['category'] ?? 'Post');
  $cover = $post['featured_image'] ?? ($post['cover_url'] ?? null);
  $heroImg = $cover ? e($cover) : null;
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  ?>

<section class="post-hero">
  <div class="container">
    <div class="post-hero-meta">
      <span><?= $cat ?></span>
      <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
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
<div class="post-cover img-mask">
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
        <span class="post-tag"><?= e($tag) ?></span>
      <?php } ?>
    </div>
    <?php } ?>
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
      <?php if (!empty($comments)) { ?>
        <h2 class="comments-title"><?= count($comments) ?> Comment<?= count($comments) === 1 ? '' : 's' ?></h2>
        <?php foreach ($comments as $comment) { ?>
          <div class="comment-item reveal">
            <div class="comment-meta">
              <strong><?= e($comment['user_name'] ?? 'Guest') ?></strong>
              <?php if (!empty($comment['created_at'])) { ?>
                &mdash; <?= e($comment['created_at']) ?>
              <?php } ?>
            </div>
            <p><?= nl2br(e($comment['content'] ?? '')) ?></p>
          </div>
        <?php } ?>
      <?php } ?>

      <?php if (!empty($comments_enabled)) { ?>
      <div class="comment-form">
        <h4>Leave a comment</h4>
        <form action="/comments/create" method="post">
          <input type="hidden" name="post_id" value="<?= ($post['id'] ?? 0) ?>">
          <?= csrf_field() ?>
          <div class="form-field">
            <label for="comment_content">Your comment</label>
            <textarea id="comment_content" name="content" rows="5" placeholder="Write something thoughtful…" required></textarea>
          </div>
          <button type="submit" class="btn-submit">
            Post comment
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </form>
      </div>
      <?php } else { ?>
        <p style="font-family:var(--mono);font-size:var(--type-mono);letter-spacing:0.14em;text-transform:uppercase;color:var(--muted);margin-top:var(--step-4);">
          Comments are closed for this post.
        </p>
      <?php } ?>
    </div>
  </div>
</section>

<?php if (!empty($related) && is_array($related)) { ?>
<section class="post-related">
  <div class="container">
    <h2 class="reveal">More to read</h2>
    <div class="related-grid">
      <?php foreach ($related as $rel) {
          $rTitle = e($rel['title'] ?? 'Post');
          $rSlug = urlencode($rel['slug'] ?? '');
          $rUrl = lurl('/blog/'.$blogSlug.'/'.$rSlug);
          $rCover = $rel['featured_image'] ?? ($rel['cover_url'] ?? null);
          $rImg = $rCover ? e($rCover) : null;
          $rExc = e($rel['excerpt'] ?? '');
          ?>
      <div class="related-item reveal">
        <?php if ($rImg) { ?>
        <a href="<?= $rUrl ?>" class="related-image">
          <img src="<?= $rImg ?>" alt="<?= $rTitle ?>" loading="lazy" />
        </a>
        <?php } ?>
        <h3 class="related-title"><a href="<?= $rUrl ?>"><?= $rTitle ?></a></h3>
        <?php if ($rExc) { ?><p style="color:var(--muted);font-size:var(--type-small);"><?= $rExc ?></p><?php } ?>
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
