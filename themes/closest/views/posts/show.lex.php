{% extends "base.lex.php" %}

{% block content %}
<?php
$title = e($post['title'] ?? 'Untitled');
$date = e($post['published_at'] ?? '');
$author = e(($user['display_name_cached'] ?? $user['username'] ?? ''));

// Featured cover from schema (featured_image), else theme fallback
$cover = $post['featured_image'] ?? ($post['cover_url'] ?? null);
$heroImg = $cover ? e($cover) : $asset('images/work-2.jpg');
?>

<!-- Hero / Intro -->
<section id="intro">
  <div class="container">
    <div class="row">
      <div class="col-lg-6 col-lg-offset-3 col-md-8 col-md-offset-2 text-center">
        <div class="intro animate-box">
          <h1><?= $title ?></h1>
          <p class="subtitle">
            <?php if ($author) { ?><span><?= $author ?></span><?php } ?>
            <?php if ($author && $date) { ?> · <?php } ?>
            <?php if ($date) { ?><time datetime="<?= $date ?>"><?= $date ?></time><?php } ?>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Featured image (if present) -->
<section id="feature-image">
  <div class="container">
    <div class="row">
      <div class="col-md-10 col-md-offset-1">
        <div class="fh5co-grid animate-box"
          style="background-image: url(<?= $heroImg ?>); min-height: 360px; background-size: cover; background-position: center;">
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Main article -->
<main id="main">
  <div class="container">
    <article class="col-md-8 col-md-offset-2 animate-box">
      <header class="mb-3">
        <h2 class="h3"><?= $title ?></h2>
        <p class="text-muted">
          <?php if ($author) { ?><span><?= $author ?></span><?php } ?>
          <?php if ($author && $date) { ?> · <?php } ?>
          <?php if ($date) { ?><time datetime="<?= $date ?>"><?= $date ?></time><?php } ?>
          <?php if (!empty($post['category']) && !empty($post['category_slug'])) { ?>
            · in <a href="/blog/<?= urlencode($blog['blog_slug']) ?>/category/<?= urlencode((string) $post['category_slug']) ?>"><?= e($post['category']) ?></a>
          <?php } ?>
        </p>
      </header>

      <div class="post-content">
        <?php if (!empty($post['content_html'])) { ?>
          <?= $post['content_html'] /* pre-rendered HTML (sanitized upstream) */ ?>
        <?php } else { ?>
          <p><?= $post['content'] ?? '' ?></p>
        <?php } ?>
      </div>

      <!-- Tags -->
      <?php if (!empty($post['tags']) && is_array($post['tags'])) { ?>
        <p class="mt-4">
          <?php foreach ($post['tags'] as $tag) { ?>
            <a class="btn btn-sm btn-outline-secondary" href="/blog/<?= urlencode($blog['blog_slug']) ?>/tag/<?= urlencode((string) $tag['slug']) ?>"><?= e($tag['name']) ?></a>
          <?php } ?>
        </p>
      <?php } ?>

      {% include "partials/_engagement.lex.php" %}

      <!-- Prev / Next -->
      <nav class="mt-4 d-flex justify-content-between">
        <?php if (!empty($prev_post)) { ?>
          <a class="btn btn-outline-primary"
            href="/blog/<?= urlencode($blog['blog_slug']) ?>/<?= urlencode($prev_post['slug']) ?>">&larr;
            <?= e($prev_post['title'] ?? 'Previous') ?>
          </a>
        <?php } else { ?>
          <span></span>
        <?php } ?>

        <?php if (!empty($next_post)) { ?>
          <a class="btn btn-outline-primary"
            href="/blog/<?= urlencode($blog['blog_slug']) ?>/<?= urlencode($next_post['slug']) ?>">
            <?= e($next_post['title'] ?? 'Next') ?> &rarr;
          </a>
        <?php } ?>
      </nav>
    </article>
  </div>

  <!-- Comments -->
  <section id="comments">
    <div class="container">
      <div class="row">
        <article class="col-md-8 col-md-offset-2 animate-box">

          <?php if (!empty($comments)) { ?>
            <h3 class="mt-5">
              <?= count($comments) ?> Comment<?= count($comments) === 1 ? '' : 's' ?>
            </h3>

            <ul class="list-unstyled mt-3">
              <?php foreach ($comments as $comment) { ?>
                <li class="media mb-3">
                  <div class="media-body">
                    <h5 class="mt-0 mb-1">
                      <?= e($comment['user_name'] ?? 'Guest') ?>
                      <?php if (!empty($comment['created_at'])) { ?>
                        <small class="text-muted">
                          &middot;
                          <?= e($comment['created_at']) ?>
                        </small>
                      <?php } ?>
                    </h5>
                    <p><?= nl2br(e($comment['content'] ?? '')) ?></p>

                    <?php if (!empty($comments_enabled)) { ?>
                      <?php if (auth()->check()) { ?>
                        <button type="button" class="reply-toggle" data-reply-toggle="<?= (int) $comment['id'] ?>">Reply</button>

                        <form class="reply-form" id="reply-form-<?= (int) $comment['id'] ?>" action="/comments/create" method="post" hidden>
                          <?= csrf_field() ?>
                          <input type="hidden" name="post_id" value="<?= (int) ($post['id'] ?? 0) ?>">
                          <input type="hidden" name="parent_comment_id" value="<?= (int) $comment['id'] ?>">
                          <textarea name="content" class="form-control" rows="2" maxlength="2000"
                            placeholder="Reply to <?= e($comment['user_name'] ?? 'Guest') ?>..." required></textarea>
                          <button type="submit" class="btn btn-sm btn-primary">Reply</button>
                          <button type="button" class="reply-toggle reply-cancel" data-reply-cancel="<?= (int) $comment['id'] ?>">Cancel</button>
                        </form>
                      <?php } else { ?>
                        <a class="reply-toggle" href="<?= e(lurl('/login')) ?>">Log in to reply</a>
                      <?php } ?>
                    <?php } ?>

                    <?php if (!empty($comment['replies'])) { ?>
                      <?php $replyTotal = count($comment['replies']); ?>
                      <p class="mb-0 mt-2">
                        <button type="button" class="reply-toggle" data-collapse-toggle="<?= (int) $comment['id'] ?>"
                          data-label-show="View <?= $replyTotal ?> <?= $replyTotal === 1 ? 'reply' : 'replies' ?>"
                          data-label-hide="Hide replies">View <?= $replyTotal ?> <?= $replyTotal === 1 ? 'reply' : 'replies' ?></button>
                      </p>
                      <ul class="comment-replies" id="replies-<?= (int) $comment['id'] ?>" hidden>
                        <?php foreach ($comment['replies'] as $reply) { ?>
                          <li class="media mb-2 mt-2">
                            <div class="media-body">
                              <h6 class="mt-0 mb-1">
                                <?= e($reply['user_name'] ?? 'Guest') ?>
                                <?php if (!empty($reply['created_at'])) { ?>
                                  <small class="text-muted">
                                    &middot;
                                    <?= e($reply['created_at']) ?>
                                  </small>
                                <?php } ?>
                              </h6>
                              <p class="mb-0"><?= nl2br(e($reply['content'] ?? '')) ?></p>

                              <?php if (!empty($comments_enabled) && auth()->check()) { ?>
                                <button type="button" class="reply-toggle" data-reply-toggle="<?= (int) $reply['id'] ?>">Reply</button>

                                <form class="reply-form" id="reply-form-<?= (int) $reply['id'] ?>" action="/comments/create" method="post" hidden>
                                  <?= csrf_field() ?>
                                  <input type="hidden" name="post_id" value="<?= (int) ($post['id'] ?? 0) ?>">
                                  <input type="hidden" name="parent_comment_id" value="<?= (int) $reply['id'] ?>">
                                  <textarea name="content" class="form-control" rows="2" maxlength="2000"
                                    placeholder="Reply to <?= e($reply['user_name'] ?? 'Guest') ?>..." required></textarea>
                                  <button type="submit" class="btn btn-sm btn-primary">Reply</button>
                                  <button type="button" class="reply-toggle reply-cancel" data-reply-cancel="<?= (int) $reply['id'] ?>">Cancel</button>
                                </form>
                              <?php } ?>
                            </div>
                          </li>
                        <?php } ?>
                      </ul>
                    <?php } ?>
                  </div>
                </li>
              <?php } ?>
            </ul>

            <script>
            (function () {
              // One open reply form at a time, TikTok style
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

          <hr class="mt-5 mb-4">

          <?php if (!empty($comments_enabled)) { ?>
            <h4 class="mb-3">Leave a comment</h4>

            <form action="/comments/create" method="post">

              <input type="hidden" name="post_id" value="<?= ($post['id'] ?? 0) ?>">

              <div class="form-group">
                <label for="comment_content" class="sr-only">Comment</label>
                <textarea
                  id="comment_content"
                  name="content"
                  class="form-control"
                  rows="4"
                  maxlength="2000"
                  placeholder="Write your comment..."
                  required></textarea>
              </div>

              <button type="submit" class="btn btn-primary mt-2">
                Post comment
              </button>
            </form>
          <?php } else { ?>
            <p class="text-muted">
              Comments are closed for this post.
            </p>
          <?php } ?>

        </article>
      </div>
    </div>
  </section>
</main>

<!-- Related posts -->
<?php if (!empty($related) && is_array($related)) { ?>
  <section id="product">
    <div class="container">
      <div class="row animate-box">
        <div class="col-md-12 section-heading text-center">
          <h2>See More</h2>
          <div class="row">
            <div class="col-md-6 col-md-offset-3 subtext">
              <p>More from this blog.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="row post-entry">
        <?php foreach ($related as $rel) { ?>
          <?php
          $rTitle = e($rel['title'] ?? 'Post');
            $rSlug = urlencode($rel['slug'] ?? '');
            $rUrl = '/blog/'.urlencode($blog['blog_slug']).'/'.$rSlug;
            // Use featured_image if present (aliased to cover_url in model), else fallback
            $rCover = $rel['featured_image'] ?? ($rel['cover_url'] ?? null);
            $rImg = $rCover ? e($rCover) : $asset('images/work-1.jpg');
            $rExcerpt = e($rel['excerpt'] ?? '');
            ?>
          <div class="col-md-6">
            <div class="post animate-box">
              <a href="<?= $rUrl ?>"><img src="<?= $rImg ?>" alt="<?= $rTitle ?>" style="width:100%; height:auto;"></a>
              <div>
                <h3><a href="<?= $rUrl ?>"><?= $rTitle ?></a></h3>
                <?php if ($rExcerpt) { ?><p><?= $rExcerpt ?></p><?php } ?>
                <span><a href="<?= $rUrl ?>">Read more...</a></span>
              </div>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  </section>
<?php } ?>
{% endblock %}