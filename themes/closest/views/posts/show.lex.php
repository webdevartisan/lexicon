{% extends "base.lex.php" %}

{% block content %}
<?php
$title = htmlspecialchars($post['title'] ?? 'Untitled', ENT_QUOTES);
$date = htmlspecialchars($post['published_at'] ?? '', ENT_QUOTES);
$author = htmlspecialchars(($user['display_name_cached'] ?? $user['username'] ?? ''), ENT_QUOTES);

// Featured cover from schema (featured_image), else theme fallback
$cover = $post['featured_image'] ?? ($post['cover_url'] ?? null);
$heroImg = $cover ? htmlspecialchars($cover, ENT_QUOTES) : $asset('images/work-2.jpg');
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
            <a class="btn btn-sm btn-outline-secondary" href="#"><?= htmlspecialchars($tag, ENT_QUOTES) ?></a>
          <?php } ?>
        </p>
      <?php } ?>

      <!-- Prev / Next -->
      <nav class="mt-4 d-flex justify-content-between">
        <?php if (!empty($prev_post)) { ?>
          <a class="btn btn-outline-primary"
            href="/blog/<?= urlencode($blog['blog_slug']) ?>/<?= urlencode($prev_post['slug']) ?>">&larr;
            <?= htmlspecialchars($prev_post['title'] ?? 'Previous', ENT_QUOTES) ?>
          </a>
        <?php } else { ?>
          <span></span>
        <?php } ?>

        <?php if (!empty($next_post)) { ?>
          <a class="btn btn-outline-primary"
            href="/blog/<?= urlencode($blog['blog_slug']) ?>/<?= urlencode($next_post['slug']) ?>">
            <?= htmlspecialchars($next_post['title'] ?? 'Next', ENT_QUOTES) ?> &rarr;
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
                      <?= htmlspecialchars($comment['user_name'] ?? 'Guest', ENT_QUOTES) ?>
                      <?php if (!empty($comment['created_at'])) { ?>
                        <small class="text-muted">
                          &middot;
                          <?= htmlspecialchars($comment['created_at'], ENT_QUOTES) ?>
                        </small>
                      <?php } ?>
                    </h5>
                    <p><?= nl2br(htmlspecialchars($comment['content'] ?? '', ENT_QUOTES)) ?></p>
                  </div>
                </li>
              <?php } ?>
            </ul>
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
          $rTitle = htmlspecialchars($rel['title'] ?? 'Post', ENT_QUOTES);
            $rSlug = urlencode($rel['slug'] ?? '');
            $rUrl = '/blog/'.urlencode($blog['blog_slug']).'/'.$rSlug;
            // Use featured_image if present (aliased to cover_url in model), else fallback
            $rCover = $rel['featured_image'] ?? ($rel['cover_url'] ?? null);
            $rImg = $rCover ? htmlspecialchars($rCover, ENT_QUOTES) : $asset('images/work-1.jpg');
            $rExcerpt = htmlspecialchars($rel['excerpt'] ?? '', ENT_QUOTES);
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