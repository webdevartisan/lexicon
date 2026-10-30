{% extends "base.lex.php" %}
{% block title %}{{ blog.blog_name }}{% endblock %}
{% block styles %}
  <link rel="stylesheet" href="<?= $asset('css/blog.css') ?>">
{% endblock %}

{% block content %}
<?php
$blogTitle = e($blog['blog_name'] ?? 'FOLIO');
  $blogSlug = urlencode($blog['blog_slug'] ?? '');
  $ownerNameRaw = $user['display_name_cached'] ?? $user['handle'];
  $ownerName = e($ownerNameRaw);
  $postCount = (int) ($totalPosts ?? count($posts ?? []));
  $featuredPost = !empty($posts) ? $posts[0] : null;
  $gridPosts = !empty($posts) ? array_slice($posts, 1, 6) : []; // landing shows 6; rest lives on /archive
  $hasMore = $postCount > 1 + count($gridPosts);

  // The Long Read spotlights the longest piece on the landing page.
  $totalMinutes = 0;
  $longReadPost = null;
  $longReadMinutes = 0;
  foreach (($posts ?? []) as $p) {
      $minutes = reading_time($p['content'] ?? '');
      $totalMinutes += $minutes;
      if ($longReadPost === null || $minutes > $longReadMinutes) {
          $longReadPost = $p;
          $longReadMinutes = $minutes;
      }
  }

  // stored featured_image is sometimes a category label rather than a real path
  $validImg = '#^(https?://|/|data:)#i';
  ?>

<section class="hero" id="top">
  <div class="container">
    <div class="hero-meta-top">
      <span><?= translate_html('folio::home.year', ['blog' => $blogTitle, 'year' => date('Y')]) ?></span>
      <span<?= edit_text_attrs('tagline', $t('folio::home.tagline')) ?>><?= !empty($settings['tagline']) ? e($settings['tagline']) : e($t('folio::home.tagline')) ?></span>
      <span><?= e(translate_count('folio::home.postCount', $postCount)) ?> / <?= e($t('folio::home.minRead', ['count' => $totalMinutes])) ?></span>
    </div>

    <h1 class="hero-headline" data-split dir="auto"><?= $blogTitle ?></h1>

    <div class="hero-bottom">
      <p class="hero-dek reveal"<?= edit_text_attrs('subtitle', $t('folio::home.subtitle')) ?>><?= !empty($settings['subtitle']) ? e($settings['subtitle']) : e($t('folio::home.subtitle')) ?></p>
      <?php if ($featuredPost) { ?>
      <div class="hero-pointer reveal">
        <a href="<?= lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? '')) ?>" class="link-arrow">
          <span<?= $wording->attrs('folio::home.begin') ?>><?= $wording->html('folio::home.begin') ?></span>
          <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>
      </div>
      <?php } ?>
    </div>
  </div>
</section>

<?php if ($featuredPost) {
    $fCover = (string) ($featuredPost['featured_image'] ?? '');
    $fImg = preg_match($validImg, $fCover) ? e($fCover) : $asset('img/cover-placeholder.svg');
    $fUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($featuredPost['slug'] ?? ''));
    $fTitle = e($featuredPost['title'] ?? 'Untitled');
    $fExc = e(post_excerpt($featuredPost, 220));
    $fAuthor = author_byline($featuredPost['author_name'], $featuredPost['author_handle'], $featuredPost['author_profile_handle']);
    $fDate = e(local_datetime($featuredPost['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
    $fCat = e($featuredPost['category'] ?? $t('folio::home.fallbackCategory'));
    ?>
<section class="lead" id="lead">
  <div class="container">
    <div class="grid-12">
      <figure class="lead-image img-mask">
        <img src="<?= $fImg ?>" alt="<?= $fTitle ?>" loading="eager" />
      </figure>

      <div class="lead-body">
        <div class="lead-tag">
          <span><?= $fCat ?></span>
          <?php if ($fDate) { ?><span><?= $fDate ?></span><?php } ?>
        </div>

        <h2 class="lead-title"><?= $fTitle ?></h2>

        <?php if ($fExc) { ?>
          <p class="lead-dek"><?= $fExc ?></p>
        <?php } ?>

        <a href="<?= $fUrl ?>" class="link-arrow" style="align-self:flex-start;margin-top:var(--step-2);">
          <span<?= $wording->attrs('folio::home.readPost') ?>><?= $wording->html('folio::home.readPost') ?></span>
          <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
        </a>

        <?php if ($fAuthor) { ?>
        <div class="lead-byline">
          <span><?= translate_html('folio::by', ['author' => $fAuthor]) ?></span>
        </div>
        <?php } ?>
      </div>
    </div>
  </div>
</section>
<?php } ?>

<?php if (!empty($gridPosts)) {
    $cards = $gridPosts;
    ?>
<section class="filters" id="index">
  <div class="container">
    <div class="row">
      <h2 class="filter-title reveal"<?= $wording->attrs('folio::home.indexTitle') ?>><?= $wording->html('folio::home.indexTitle') ?></h2>
      <span class="filter-count reveal" id="filter-count"><?= e(translate_count('folio::home.indexCount', count($gridPosts))) ?></span>
    </div>

    <ul class="filter-list reveal" role="tablist" aria-label="<?= e($t('folio::nav.filterByCategory')) ?>"
        data-text="<?= e(json_encode(['openAll' => $t('folio::home.openAll'), 'inCategory' => $t('folio::home.inCategory'), 'viewAll' => $t('folio::home.viewAll'), 'count' => ['one' => $t('folio::home.indexCount.one'), 'other' => $t('folio::home.indexCount.other')]], JSON_UNESCAPED_UNICODE)) ?>">
      <?php $allActive = ($activeCategory ?? '') === ''; ?>
      <li class="<?= $allActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug) ?>" data-filter="" role="tab" aria-selected="<?= $allActive ? 'true' : 'false' ?>"><?= e($t('folio::home.all')) ?></a></li>
      <?php foreach (($categories ?? []) as $c) {
          $cSlug = (string) ($c['slug'] ?? '');
          $catActive = ($activeCategory ?? '') === $cSlug;
          ?>
        <li class="<?= $catActive ? 'is-active' : '' ?>"><a href="<?= lurl('/blog/'.$blogSlug.'/category/'.urlencode($cSlug)) ?>" data-filter="<?= e($cSlug) ?>" data-count="<?= (int) ($c['post_count'] ?? 0) ?>" role="tab" aria-selected="<?= $catActive ? 'true' : 'false' ?>"><?= e($c['name']) ?></a></li>
      <?php } ?>
    </ul>
  </div>
</section>

<section class="articles">
  <div class="container">
    <div class="articles-grid">
      {% include "blogs/_index_cards.lex.php" %}
    </div>

    <?php $archiveUrl = lurl('/blog/'.$blogSlug.'/archive'); ?>
    <div class="articles-footer reveal" id="index-footer" data-archive="<?= $archiveUrl ?>" data-allmore="<?= $hasMore ? '1' : '0' ?>"<?= $hasMore ? '' : ' style="display:none;"' ?>>
      <span class="filter-count" id="index-footer-count"><?= translate_html('folio::home.showing', ['shown' => sprintf('%02d', 1 + count($gridPosts)), 'total' => sprintf('%02d', $postCount), 'blog' => $blogTitle]) ?></span>
      <a href="<?= $archiveUrl ?>" class="link-arrow" id="index-footer-link">
        <span id="index-footer-label"><?= e($t('folio::home.openAll')) ?></span>
        <svg class="arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
      </a>
    </div>
  </div>
</section>
<?php } ?>

<?php if ($longReadPost) {
    $lrTitle = e($longReadPost['title'] ?? 'Untitled');
    $lrUrl = lurl('/blog/'.$blogSlug.'/'.urlencode($longReadPost['slug'] ?? ''));
    $lrMinutes = reading_time($longReadPost['content'] ?? '');
    $lrAuthor = author_byline($longReadPost['author_name'], $longReadPost['author_handle'], $longReadPost['author_profile_handle']);

    $excerpt = trim((string) ($longReadPost['excerpt'] ?? ''));
    $quote = trim(preg_replace('/\s+/', ' ', $excerpt !== ''
        ? $excerpt
        : strip_tags((string) ($longReadPost['content'] ?? ''))));

    if (mb_strlen($quote) > 220) {
        $cut = mb_substr($quote, 0, 220);
        $space = mb_strrpos($cut, ' ');
        $quote = ($space !== false ? mb_substr($cut, 0, $space) : $cut).'…';
    }

    // Fade the last word or two into muted weight so the quote ends on a quieter beat.
    $words = preg_split('/\s+/', $quote);
    $tailLen = count($words) >= 4 ? 2 : 1;
    // Not $head: template globals share this scope, and `head` is the i18n one
    // the layout reads for canonical, hreflang and the language switcher.
    $quoteHead = e(implode(' ', array_slice($words, 0, -$tailLen)));
    $quoteTail = e(implode(' ', array_slice($words, -$tailLen)));
    ?>
<section class="longread">
  <div class="container">
    <div class="grid-12">
      <div class="longread-eyebrow reveal">
        <h3<?= $wording->attrs('folio::home.longRead') ?>><?= $wording->html('folio::home.longRead') ?></h3>
        <?php $lrLine = '<span'.$wording->attrs('folio::home.featuredLine').'>'.$wording->html('folio::home.featuredLine').'</span>'; ?>
        <span class="eyebrow"><?= translate_html('folio::home.featured', ['line' => $lrLine, 'count' => (int) $lrMinutes]) ?></span>
      </div>

      <blockquote class="longread-quote reveal">
        &ldquo;<?= $quoteHead ?> <span class="accent"><?= $quoteTail ?></span>&rdquo;
      </blockquote>

      <div class="longread-author reveal">
        <strong><?= $lrAuthor ?></strong>
        <span><?= e($t('folio::home.postMinutes', ['count' => $lrMinutes])) ?></span>
      </div>

      <div class="longread-body reveal">
        <?php $lrContinue = ['title' => '<em>'.$lrTitle.'</em>']; ?>
        <p<?= $wording->attrs('folio::home.continue', $lrContinue) ?>><?= $wording->html('folio::home.continue', $lrContinue) ?></p>
        <a href="<?= $lrUrl ?>"<?= $wording->attrs('folio::home.readFull') ?>><?= $wording->html('folio::home.readFull') ?></a>
      </div>
    </div>
  </div>
</section>
<?php } ?>

<section class="colophon" id="colophon">
  <div class="container">
    <div class="grid-12">
      <?php
        // The label can be a year or a short word; words render smaller so
        // the display size doesn't overflow the column.
        $aboutLabel = !empty($settings['founded_year']) ? (string) $settings['founded_year'] : date('Y');
  $aboutLabelClass = mb_strlen($aboutLabel) > 6 ? ' colophon-number--word' : '';
  ?>
      <p class="colophon-number<?= $aboutLabelClass ?> reveal"><span<?= edit_text_attrs('founded_year', date('Y')) ?>><?= e($aboutLabel) ?></span>.</p>
      <div class="colophon-body">
        <span class="colophon-eyebrow reveal"<?= $wording->attrs('folio::home.aboutEyebrow') ?>><?= $wording->html('folio::home.aboutEyebrow') ?></span>
        <?php $aboutDefault = !empty($settings['subtitle']) ? (string) $settings['subtitle'] : $t('folio::home.aboutDefault', ['blog' => $blog['blog_name'] ?? 'FOLIO']); ?>
        <p class="colophon-statement reveal"<?= edit_text_attrs('about_text', e($aboutDefault)) ?>><?= e(!empty($settings['about_text']) ? $settings['about_text'] : $aboutDefault) ?></p>
        <span class="colophon-sign reveal"><?= e($user['display_name_cached'] ?? $user['handle']) ?></span>
      </div>
    </div>
  </div>
</section>

<section class="newsletter" id="newsletter">
  <div class="container">
    <div class="grid-12">
      <div class="news-copy">
        <span class="eyebrow reveal"<?= $wording->attrs('folio::home.newsEyebrow') ?>><?= $wording->html('folio::home.newsEyebrow') ?></span>
        <?php $newsHeadingDefault = translate_html('folio::home.newsHeading', ['blog' => $blogTitle]); ?>
        <h2 class="reveal"<?= edit_text_attrs('newsletter_heading', $newsHeadingDefault) ?>><?= !empty($settings['newsletter_heading']) ? e($settings['newsletter_heading']) : $newsHeadingDefault ?></h2>
        <p class="reveal"<?= edit_text_attrs('newsletter_text', $t('folio::home.newsText')) ?>><?= !empty($settings['newsletter_text']) ? e($settings['newsletter_text']) : e($t('folio::home.newsText')) ?></p>
      </div>

      <form class="news-form reveal" action="<?= e('/blog/'.rawurlencode((string) $blog['blog_slug']).'/subscribe') ?>" method="post">
        <?= csrf_field_lazy() ?>
        <label for="email-sub"><?= e($t('folio::emailLabel')) ?></label>
        <div class="news-input">
          <input id="email-sub" name="email" type="email" placeholder="reader@quiet-room.co"
            value="<?= e(auth()->check() ? (string) (auth()->user()['email'] ?? '') : '') ?>"
            autocomplete="email" maxlength="255" required />
          <button type="submit">
            <?= e($t('folio::home.subscribe')) ?>
            <svg class="arrow" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </div>
        <span class="news-fine"<?= $wording->attrs('folio::home.newsFine') ?>><?= $wording->html('folio::home.newsFine') ?></span>
      </form>
    </div>
  </div>
</section>
{% include "partials/public/_edit_texts.lex.php" %}
{% endblock %}

{% block scripts %}
	<script defer src="<?= $asset('js/blog.js') ?>"></script>
{% endblock %}
