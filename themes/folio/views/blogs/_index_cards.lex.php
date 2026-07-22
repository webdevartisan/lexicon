<?php
// Card grid for "The Index". Shared by the landing and the AJAX category swap
// (rendered standalone by BlogController::indexFeed).
$blogSlug = urlencode($blog['blog_slug'] ?? '');
$ownerNameRaw = $user['display_name_cached'] ?? $user['username'] ?? 'The Author';
$ownerName = e($ownerNameRaw);
$validImg = '#^(https?://|/|data:)#i';

foreach (($cards ?? []) as $i => $post) {
    $cover = (string) ($post['featured_image'] ?? '');
    $img = preg_match($validImg, $cover) ? e($cover) : 'https://picsum.photos/seed/folio-card-'.($i + 1).'/900/1080';
    $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
    $title = e($post['title'] ?? 'Untitled');
    $cat = trim((string) ($post['category'] ?? 'Post'));
    $catSlug = (string) ($post['category_slug'] ?? '');
    $date = e(local_datetime($post['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
    $author = profile_link($post['author_name'] ?? $ownerNameRaw, $user['public_profile_slug'] ?? null);
    $minutes = reading_time($post['content'] ?? '');
    $tags = is_array($post['tags'] ?? null) ? $post['tags'] : [];
    ?>
  <article class="article reveal" data-category="<?= e($catSlug !== '' ? $catSlug : strtolower($cat)) ?>" onclick="location.href='<?= $url ?>'">
    <div class="article-meta">
      <span class="cat"><?= sprintf('%02d', $i + 1) ?> &mdash; <?= e($cat) ?></span>
      <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
    </div>
    <div class="article-image img-mask">
      <img src="<?= $img ?>" alt="<?= $title ?>" loading="lazy" />
      <span class="article-arrow" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 17L17 7M9 7h8v8"/></svg>
      </span>
    </div>
    <h3 class="article-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
    <?php if (!empty($tags)) { ?>
    <div class="article-tags">
      <?php foreach (array_slice($tags, 0, 2) as $t) {
          $tUrl = lurl('/blog/'.$blogSlug.'/tag/'.urlencode((string) ($t['slug'] ?? '')));
          ?>
      <a class="article-tag" href="<?= $tUrl ?>" onclick="event.stopPropagation();">#<?= e((string) ($t['name'] ?? '')) ?></a>
      <?php } ?>
    </div>
    <?php } ?>
    <div class="article-byline">
      <span><?= $author ?></span>
      <span><?= $minutes ?> Min</span>
    </div>
  </article>
<?php } ?>
