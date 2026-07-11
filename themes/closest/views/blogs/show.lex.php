{% extends "base.lex.php" %}
{% block title %} {{ blog.blog_name }} {% endblock %}
{% block content %}

<?php
$banner = $settings['banner_path'] ?? null;
$blogTitle = e($blog['blog_name'] ?? ($username."'s Blog"));
?>

<?php if (!empty($banner)) { ?>
  <section id="banner" class="hero">
    <figure class="hero-media" style="margin:0">
      <img
        src="<?= e($banner) ?>"
        alt="<?= $blogTitle ?> banner"
        loading="lazy"
        decoding="async"
        width="1600"
        height="500"
        style="display:block;width:100%;height:400px;object-fit:cover"
      />
    </figure>
  </section>
<?php } ?>

  <!-- Intro -->
  <section id="intro">
    <div class="container">
      <div class="row">
        <div class="col-lg-6 col-lg-offset-3 col-md-8 col-md-offset-2 text-center">
          <div class="intro animate-box style=" >
            <h2> {{ blog.blog_name }} </h2>
            <?php if (!empty($blog['subtitle'])) { ?>
              <p class="subtitle"><?= e($blog['subtitle']) ?></p>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Work (dynamic posts mapped to tiles) -->
  <section id="work">
    <div class="container">
      <div class="row">
        <?php if (!empty($posts)) { ?>
          <?php foreach ($posts as $i => $post) { ?>
            <?php
              $cover = $post['featured_image'] ?? null;
              $bg = $cover ? e($cover) : $asset('images/work-1.jpg');
              $url = '/blog/'.urlencode($blog['blog_slug']).'/'.urlencode($post['slug']);
              $title = e($post['title'] ?? 'Untitled');
              $cat = e($post['category'] ?? 'Post');
              $date = e($post['published_at'] ?? '');
              ?>
            <div class="col-md-<?= ($i % 3 === 2) ? 12 : 6; ?>">
              <div class="fh5co-grid animate-box" style="background-image: url(<?= $bg ?>);">
                <a class="image-popup text-center" href="<?= $url ?>">
                  <div class="work-title">
                    <h3><?= $title ?></h3>
                    <span><?= $cat ?></span>
                    <?php if ($date) { ?>
                      <time datetime="<?= $date ?>"><?= $date ?></time>
                    <?php } ?>
                  </div>
                </a>
              </div>
            </div>
          <?php } ?>
        <?php } else { ?>
          <div class="col-md-12">
            <p>No posts yet.</p>
          </div>
        <?php } ?>
      </div>
    </div>

      <?php if (!empty($pagination) && $pagination['totalPages'] > 1) { ?>
        <nav class="pagination-wrapper" aria-label="Page navigation">
          <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $pagination['totalPages']; $i++) { ?>
              <li class="page-item <?= $i === $pagination['currentPage'] ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
              </li>
            <?php } ?>
          </ul>
        </nav>
      <?php } ?>

  </section>

  <!-- Services (static theme section) -->
  <section id="services">
    <div class="container">
      <div class="row">
        <div class="col-md-4 animate-box">
          <div class="service">
            <div class="service-icon"><i class="icon-command"></i></div>
            <h2>Brand Identity</h2>
            <p>Far far away, behind the word mountains...</p>
          </div>
        </div>
        <div class="col-md-4 animate-box">
          <div class="service">
            <div class="service-icon"><i class="icon-drop2"></i></div>
            <h2>Web Design &amp; UI</h2>
            <p>Far far away, behind the word mountains...</p>
          </div>
        </div>
        <div class="col-md-4 animate-box">
          <div class="service">
            <div class="service-icon"><i class="icon-anchor"></i></div>
            <h2>Development &amp; CMS</h2>
            <p>Far far away, behind the word mountains...</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Subscribe -->
  <section id="subscribe">
    <div class="container">
      <div class="row">
        <div class="col-lg-6 col-lg-offset-3 col-md-8 col-md-offset-2 text-center animate-box">
          <h2>Never miss a post</h2>
          <p class="text-muted">Get an email when <?= e($blog['blog_name'] ?? 'this blog') ?> publishes something new. Unsubscribe any time.</p>
          <form action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post"
            style="display:flex; gap:.5rem; justify-content:center; flex-wrap:wrap; margin-top:1rem;">
            <?= csrf_field() ?>
            <label for="subscribe-email" class="sr-only">Email address</label>
            <input id="subscribe-email" name="email" type="email" class="form-control"
              style="max-width:280px;" placeholder="you@example.com"
              value="<?= e(auth()->check() ? (string) (auth()->user()['email'] ?? '') : '') ?>"
              autocomplete="email" maxlength="255" required>
            <button type="submit" class="btn btn-primary">Subscribe</button>
          </form>
        </div>
      </div>
    </div>
  </section>
{% endblock %}
