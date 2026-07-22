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
      <span><?= $minutes ?> Min &asymp; <?= $km ?> km</span>
    </div>

    <h1 class="post-hero-title reveal"><?= $title ?></h1>

    <?php if ($standfirst !== '') { ?>
      <p class="post-standfirst reveal"><?= e($standfirst) ?></p>
    <?php } ?>

    <div class="post-hero-byline">
      <?php if ($author) { ?><span>Surveyed by <?= $author ?></span><?php } ?>
      <a href="<?= lurl('/blog/'.$blogSlug) ?>" class="link-arrow">
        Back to <?= e($blog['blog_name'] ?? 'Blog') ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>

<?php if ($heroImg) { ?>
<div class="container">
  <div class="inset post-cover" id="post-cover">
    <span class="inset-img"><img src="<?= $heroImg ?>" alt="<?= $title ?>" loading="eager" decoding="async" /></span>
    <span class="inset-cap">Fig. 01 &middot; <?= $title ?></span>
  </div>
</div>
<?php } ?>

<div class="container">
  <div class="post-layout">
    <div class="post-content reveal">
      <?= $post['content'] ?? '' ?>
    </div>

    <?php if (!empty($post['tags']) && is_array($post['tags'])) { ?>
    <div class="post-tags reveal">
      <span class="post-tags-label">Marked</span>
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
        <span class="post-nav-label">&larr; Back down the trail</span>
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($prev_post['slug'] ?? '')) ?>" class="post-nav-title">
          <?= e($prev_post['title'] ?? 'Previous') ?>
        </a>
      </div>
      <?php } else { ?><div></div><?php } ?>

      <?php if (!empty($next_post)) { ?>
      <div class="post-nav-item next">
        <span class="post-nav-label">Onward &rarr;</span>
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
        <h2 class="comments-title">Trail register &mdash; <?= count($comments) ?> entr<?= count($comments) === 1 ? 'y' : 'ies' ?></h2>
        <?php foreach ($comments as $comment) { ?>
          <div class="comment-item reveal" id="comment-<?= (int) $comment['id'] ?>">
            <div class="comment-meta">
              <strong><?= profile_link($comment['user_name'] ?? 'Guest', $comment['author_profile_slug'] ?? null) ?></strong>
              <?php if (!empty($comment['created_at'])) { ?>
                &mdash; <?= e(relative_time($comment['created_at'] ?? null)) ?>
              <?php } ?>
            </div>
            <p><?= nl2br(e($comment['content'] ?? '')) ?></p>

            <?php if (!empty($comments_enabled)) { ?>
              <button type="button" class="reply-toggle" data-reply-toggle="<?= (int) $comment['id'] ?>">Reply</button>

              <form class="reply-form" id="reply-form-<?= (int) $comment['id'] ?>" action="/comments/create" method="post" hidden>
                <?= csrf_field() ?>
                <input type="hidden" name="post_id" value="<?= (int) ($post['id'] ?? 0) ?>">
                <input type="hidden" name="parent_comment_id" value="<?= (int) $comment['id'] ?>">
                <textarea name="content" rows="2" maxlength="2000"
                  placeholder="Reply to <?= e($comment['user_name'] ?? 'Guest') ?>..." required></textarea>
                <?php if (!auth()->check()) { ?>
                  <p style="margin: .35rem 0 0; font-size: .8em; opacity: .7;">You'll be asked to log in &mdash; your reply is kept.</p>
                <?php } ?>
                <button type="submit" class="btn-reply">Reply</button>
                <button type="button" class="reply-toggle reply-cancel" data-reply-cancel="<?= (int) $comment['id'] ?>">Cancel</button>
              </form>
            <?php } ?>

            <?php if (!empty($comment['replies'])) { ?>
              <?php $replyTotal = count($comment['replies']); ?>
              <p style="margin: .5rem 0 0;">
                <button type="button" class="reply-toggle" data-collapse-toggle="<?= (int) $comment['id'] ?>"
                  data-label-show="View <?= $replyTotal ?> <?= $replyTotal === 1 ? 'reply' : 'replies' ?>"
                  data-label-hide="Hide replies">View <?= $replyTotal ?> <?= $replyTotal === 1 ? 'reply' : 'replies' ?></button>
              </p>
              <div class="comment-replies" id="replies-<?= (int) $comment['id'] ?>" hidden>
                <?php foreach ($comment['replies'] as $reply) { ?>
                  <div class="comment-item" id="comment-<?= (int) $reply['id'] ?>">
                    <div class="comment-meta">
                      <strong><?= profile_link($reply['user_name'] ?? 'Guest', $reply['author_profile_slug'] ?? null) ?></strong>
                      <?php if (!empty($reply['created_at'])) { ?>
                        &mdash; <?= e(relative_time($reply['created_at'] ?? null)) ?>
                      <?php } ?>
                    </div>
                    <p><?= nl2br(e($reply['content'] ?? '')) ?></p>

                    <?php if (!empty($comments_enabled)) { ?>
                      <button type="button" class="reply-toggle" data-reply-toggle="<?= (int) $reply['id'] ?>">Reply</button>

                      <form class="reply-form" id="reply-form-<?= (int) $reply['id'] ?>" action="/comments/create" method="post" hidden>
                        <?= csrf_field() ?>
                        <input type="hidden" name="post_id" value="<?= (int) ($post['id'] ?? 0) ?>">
                        <input type="hidden" name="parent_comment_id" value="<?= (int) $reply['id'] ?>">
                        <textarea name="content" rows="2" maxlength="2000"
                          placeholder="Reply to <?= e($reply['user_name'] ?? 'Guest') ?>..." required></textarea>
                        <?php if (!auth()->check()) { ?>
                          <p style="margin: .35rem 0 0; font-size: .8em; opacity: .7;">You'll be asked to log in &mdash; your reply is kept.</p>
                        <?php } ?>
                        <button type="submit" class="btn-reply">Reply</button>
                        <button type="button" class="reply-toggle reply-cancel" data-reply-cancel="<?= (int) $reply['id'] ?>">Cancel</button>
                      </form>
                    <?php } ?>
                  </div>
                <?php } ?>
              </div>
            <?php } ?>
          </div>
        <?php } ?>

        <script>
        (function () {
          // One open reply form at a time
          function closeAll() {
            document.querySelectorAll('.reply-form').forEach(function (f) { f.setAttribute('hidden', ''); });
          }

          document.addEventListener('click', function (ev) {
            var toggle = ev.target.closest('[data-reply-toggle]');
            if (toggle) {
              var form = document.getElementById('reply-form-' + toggle.getAttribute('data-reply-toggle'));
              var wasHidden = form.hasAttribute('hidden');
              closeAll();
              if (wasHidden) {
                form.removeAttribute('hidden');
                form.querySelector('textarea').focus();
              }
              return;
            }

            var cancel = ev.target.closest('[data-reply-cancel]');
            if (cancel) { closeAll(); return; }

            var collapse = ev.target.closest('[data-collapse-toggle]');
            if (collapse) {
              var list = document.getElementById('replies-' + collapse.getAttribute('data-collapse-toggle'));
              var show = list.hasAttribute('hidden');
              list.toggleAttribute('hidden', !show);
              collapse.textContent = collapse.getAttribute(show ? 'data-label-hide' : 'data-label-show');
            }
          });
        })();
        </script>
      <?php } ?>

      <?php if (!empty($comments_enabled)) { ?>
      <div class="comment-form">
        <h4>Sign the register</h4>
        <form action="/comments/create" method="post">
          <input type="hidden" name="post_id" value="<?= ($post['id'] ?? 0) ?>">
          <?= csrf_field() ?>
          <div class="form-field">
            <label for="comment_content">Your entry</label>
            <textarea id="comment_content" name="content" rows="5" maxlength="2000" placeholder="Passed through, and here is what I noticed&hellip;" required></textarea>
          </div>
          <button type="submit" class="btn-submit">
            Leave an entry
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </form>
      </div>
      <?php } else { ?>
        <p class="comments-closed">
          The register is closed for this waypoint.
        </p>
      <?php } ?>
    </div>
  </div>
</section>

<?php if (!empty($related) && is_array($related)) { ?>
<section class="post-related">
  <div class="container">
    <h2 class="reveal">Nearby on the map</h2>
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
        <a href="<?= $rUrl ?>" class="inset related-inset">
          <span class="inset-img"><img src="<?= $rImg ?>" alt="<?= $rTitle ?>" loading="lazy" /></span>
        </a>
        <?php } ?>
        <h3 class="related-title"><a href="<?= $rUrl ?>"><?= $rTitle ?></a></h3>
        <?php if ($rExc) { ?><p class="related-exc"><?= $rExc ?></p><?php } ?>
        <a href="<?= $rUrl ?>" class="link-arrow">
          Walk over
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
	<script defer src="/assets/js/comment-anchor.js"></script>
{% endblock %}
