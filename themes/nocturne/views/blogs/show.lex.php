{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'Nocturne');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerNameRaw = $user['display_name_cached'] ?? $user['username'] ?? 'The Author';
  $ownerName = e($ownerNameRaw);
  $postCount = (int) ($totalPosts ?? count($posts ?? []));
  $featuredPost = !empty($posts) ? $posts[0] : null;
  $indexPosts = !empty($posts) ? array_slice($posts, 1, 6) : [];
  $hasMore = $postCount > 1 + count($indexPosts);

  // The Nightcap closes the page with a line from the longest read.
  $totalMinutes = 0;
  $nightcapPost = null;
  $nightcapMinutes = 0;
  foreach (($posts ?? []) as $p) {
      $minutes = reading_time($p['content'] ?? '');
      $totalMinutes += $minutes;
      if ($nightcapPost === null || $minutes > $nightcapMinutes) {
          $nightcapPost = $p;
          $nightcapMinutes = $minutes;
      }
  }

  // stored featured_image is sometimes a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';

  // The last word of the blog name gets the italic brass treatment.
  $nameWords = preg_split('/\s+/', trim((string) ($blog['blog_name'] ?? 'Nocturne')));
  $nameTail = e((string) array_pop($nameWords));
  $nameHead = e(implode(' ', $nameWords));
  ?>

<section class="hero" id="top">
  <div class="container">
    <div class="hero-rail">
      <span>The Night Edition</span>
      <span><?= !empty($settings['tagline']) ? e($settings['tagline']) : 'Written after hours' ?></span>
      <span><?= $postCount ?> Post<?= $postCount === 1 ? '' : 's' ?> &middot; <?= $totalMinutes ?> Min of Reading</span>
    </div>

    <h1 class="hero-name">
      <?php if ($nameHead !== '') { ?>
        <span class="hero-line"><span><?= $nameHead ?></span></span>
      <?php } ?>
      <span class="hero-line hero-line-tail"><span><em><?= $nameTail ?></em></span></span>
    </h1>

    <div class="hero-foot">
      <p class="hero-dek reveal">
        <?= !empty($settings['subtitle']) ? e($settings['subtitle']) : 'Essays and notes for the small hours.' ?>
      </p>
      <?php if ($featuredPost) { ?>
      <a href="#coverstory" class="hero-cue reveal" aria-label="Scroll to tonight's cover story">
        <span>Tonight's reading</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6" /></svg>
      </a>
      <?php } ?>
    </div>
  </div>
  <div class="hero-horizon" aria-hidden="true"></div>
</section>

<?php if ($featuredPost) {
    $fCover = (string) ($featuredPost['featured_image'] ?? '');
    $fImg = preg_match($validImg, $fCover) ? e($fCover) : 'https://picsum.photos/seed/nocturne-cover/1400/1750';
    $fUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? ''));
    $fTitle = e($featuredPost['title'] ?? 'Untitled');
    $fExc = e($featuredPost['excerpt'] ?? '');
    $fAuthor = profile_link(
        $featuredPost['author_name'] ?? ($user['display_name_cached'] ?? $user['username'] ?? ''),
        $user['public_profile_slug'] ?? null
    );
    $fDate = e($featuredPost['published_at'] ?? '');
    $fCat = e($featuredPost['category'] ?? 'Article');
    $fMin = reading_time($featuredPost['content'] ?? '');
    ?>
<section class="cover" id="coverstory">
  <div class="container">
    <div class="cover-grid">
      <div class="cover-body">
        <span class="eyebrow reveal">Tonight's cover story</span>
        <h2 class="cover-title reveal"><a href="<?= $fUrl ?>"><?= $fTitle ?></a></h2>

        <?php if ($fExc) { ?>
          <p class="cover-dek reveal"><?= $fExc ?></p>
        <?php } ?>

        <div class="cover-meta reveal">
          <span><?= $fCat ?></span>
          <?php if ($fDate) { ?><span><?= $fDate ?></span><?php } ?>
          <span><?= $fMin ?> Min</span>
        </div>

        <a href="<?= $fUrl ?>" class="link-arrow reveal">
          Read the post
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>

        <?php if ($fAuthor) { ?>
        <div class="cover-byline reveal">By <?= $fAuthor ?></div>
        <?php } ?>
      </div>

      <figure class="cover-image img-veil">
        <img src="<?= $fImg ?>" alt="<?= $fTitle ?>" loading="eager" />
      </figure>
    </div>
  </div>
</section>
<?php } ?>

<?php if (!empty($indexPosts)) {
    $cards = $indexPosts;
    ?>
<section class="index" id="index">
  <div class="container">
    <div class="index-head">
      <h2 class="index-title reveal">The Index</h2>
      <span class="index-count reveal" id="filter-count"><?= count($indexPosts) ?> post<?= count($indexPosts) === 1 ? '' : 's' ?></span>
    </div>

    <ul class="filter-list reveal" role="tablist" aria-label="Filter posts by category">
      <?php $allActive = ($activeCategory ?? '') === ''; ?>
      <li class="<?= $allActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug) ?>" data-filter="" role="tab" aria-selected="<?= $allActive ? 'true' : 'false' ?>">All</a></li>
      <?php foreach (($categories ?? []) as $c) {
          $cSlug = (string) ($c['slug'] ?? '');
          $catActive = ($activeCategory ?? '') === $cSlug;
          ?>
        <li class="<?= $catActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug.'/category/'.urlencode($cSlug)) ?>" data-filter="<?= e($cSlug) ?>" data-count="<?= (int) ($c['post_count'] ?? 0) ?>" role="tab" aria-selected="<?= $catActive ? 'true' : 'false' ?>"><?= e($c['name']) ?></a></li>
      <?php } ?>
    </ul>

    <div class="idx-list" id="idx-list">
      {% include "blogs/_index_cards.lex.php" %}
    </div>

    <div class="idx-float" id="idx-float" aria-hidden="true"><img src="" alt="" /></div>

    <?php $archiveUrl = lurl('/blog/'.$blogSlug.'/archive'); ?>
    <div class="index-foot reveal" id="index-footer" data-archive="<?= $archiveUrl ?>" data-allmore="<?= $hasMore ? '1' : '0' ?>"<?= $hasMore ? '' : ' style="display:none;"' ?>>
      <span class="index-count" id="index-footer-count">Showing <?= sprintf('%02d', 1 + count($indexPosts)) ?> of <?= sprintf('%02d', $postCount) ?> &mdash; <?= $blogTitle ?></span>
      <a href="<?= $archiveUrl ?>" class="link-arrow" id="index-footer-link">
        <span id="index-footer-label">Open the full archive</span>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>
<?php } ?>

<?php if ($nightcapPost) {
    $ncTitle = e($nightcapPost['title'] ?? 'Untitled');
    $ncUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($nightcapPost['slug'] ?? ''));
    $ncMinutes = reading_time($nightcapPost['content'] ?? '');
    $ncAuthor = profile_link($nightcapPost['author_name'] ?? $ownerNameRaw, $user['public_profile_slug'] ?? null);

    $excerpt = trim((string) ($nightcapPost['excerpt'] ?? ''));
    $quote = trim(preg_replace('/\s+/', ' ', $excerpt !== ''
        ? $excerpt
        : strip_tags((string) ($nightcapPost['content'] ?? ''))));

    if (mb_strlen($quote) > 200) {
        $cut = mb_substr($quote, 0, 200);
        $space = mb_strrpos($cut, ' ');
        $quote = ($space !== false ? mb_substr($cut, 0, $space) : $cut).'…';
    }
    ?>
<section class="nightcap">
  <div class="container">
    <div class="nightcap-head reveal">
      <span class="eyebrow">The Nightcap</span>
      <span class="eyebrow">One for the slow hours &mdash; <?= $ncMinutes ?> Min</span>
    </div>

    <blockquote class="nightcap-quote reveal">
      <?= e($quote) ?>
    </blockquote>

    <div class="nightcap-foot reveal">
      <span class="nightcap-source">from <a href="<?= $ncUrl ?>"><em><?= $ncTitle ?></em></a> by <?= $ncAuthor ?></span>
      <a href="<?= $ncUrl ?>" class="link-arrow">
        Pour the full read
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>
<?php } ?>

<section class="desk" id="desk">
  <div class="container">
    <div class="desk-grid">
      <?php
        $aboutLabel = !empty($settings['founded_year']) ? (string) $settings['founded_year'] : date('Y');
  $aboutLabelClass = mb_strlen($aboutLabel) > 6 ? ' desk-mark--word' : '';
  ?>
      <p class="desk-mark<?= $aboutLabelClass ?> reveal"><?= e($aboutLabel) ?></p>
      <div class="desk-body">
        <span class="eyebrow reveal">From the desk of <?= $ownerName ?></span>
        <p class="desk-statement reveal">
          <?php if (!empty($settings['about_text'])) { ?>
            <?= e($settings['about_text']) ?>
          <?php } elseif (!empty($settings['subtitle'])) { ?>
            <?= e($settings['subtitle']) ?>
          <?php } else { ?>
            <?= $blogTitle ?>, a room kept warm for readers who arrive late.
          <?php } ?>
        </p>
        <span class="desk-sign reveal">&mdash; <?= $ownerName ?></span>
      </div>
    </div>
  </div>
</section>

<section class="nightletter" id="nightletter">
  <div class="container">
    <div class="nightletter-grid">
      <div class="nightletter-copy">
        <span class="eyebrow reveal">The Night Letter</span>
        <h2 class="reveal"><?= !empty($settings['newsletter_heading']) ? e($settings['newsletter_heading']) : 'New posts from '.$blogTitle.', delivered while you sleep.' ?></h2>
        <p class="reveal"><?= !empty($settings['newsletter_text']) ? e($settings['newsletter_text']) : 'No tracking, no algorithms, easy unsubscribe.' ?></p>
      </div>

      <form class="nightletter-form reveal" action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post">
        <?= csrf_field() ?>
        <label for="email-sub">Your address</label>
        <div class="nightletter-input">
          <input id="email-sub" name="email" type="email" placeholder="reader@latehours.co"
            value="<?= e(auth()->check() ? (string) (auth()->user()['email'] ?? '') : '') ?>"
            autocomplete="email" maxlength="255" required />
          <button type="submit">
            Subscribe
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </div>
        <span class="nightletter-fine">Free &mdash; unsubscribe any time</span>
      </form>
    </div>
  </div>
</section>
{% endblock %}

{% block scripts %}
  <script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
