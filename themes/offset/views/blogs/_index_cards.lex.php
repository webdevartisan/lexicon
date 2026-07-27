<?php
// Ledger rows for "The Index". Shared by the landing and the AJAX category swap
// (rendered standalone by BlogController::indexFeed). Each row carries its
// cover in data attributes so the light table can proof it on hover.
$blogSlug = urlencode($blog['blog_slug'] ?? '');
$ownerNameRaw = $user['display_name_cached'] ?? $user['username'] ?? 'The Editor';
$ownerName = e($ownerNameRaw);
$validImg = '#^(https?://|/|data:)#i';

foreach (($cards ?? []) as $i => $post) {
    $cover = (string) ($post['featured_image'] ?? '');
    $img = preg_match($validImg, $cover) ? $cover : 'https://picsum.photos/seed/offset-row-'.($i + 1).'/900/680';
    $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
    $title = e($post['title'] ?? 'Untitled');
    $cat = trim((string) ($post['category'] ?? 'Post'));
    $catSlug = (string) ($post['category_slug'] ?? '');
    $date = e(local_datetime($post['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
    $author = profile_link($post['author_name'] ?? $ownerNameRaw, $user['public_profile_slug'] ?? null);
    $minutes = reading_time($post['content'] ?? '');
    $tags = is_array($post['tags'] ?? null) ? $post['tags'] : [];
    ?>
  <article class="index-row reveal" data-category="<?= e($catSlug !== '' ? $catSlug : strtolower($cat)) ?>"
           data-cover="<?= e($img) ?>" data-cap="<?= $title ?>" data-card-link="<?= e($url) ?>">
    <span class="row-no"><?= sprintf('%02d', $i + 1) ?></span>

    <span class="plate row-thumb" aria-hidden="true">
      <span class="plate-img"><img src="<?= e($img) ?>" alt="" loading="lazy" /></span>
    </span>

    <div class="row-main">
      <h3 class="row-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
      <?php if (!empty($tags)) { ?>
      <div class="row-tags">
        <?php foreach (array_slice($tags, 0, 2) as $t) {
            $tUrl = lurl('/blog/'.$blogSlug.'/tag/'.urlencode((string) ($t['slug'] ?? '')));
            ?>
        <a class="row-tag" href="<?= $tUrl ?>">#<?= e((string) ($t['name'] ?? '')) ?></a>
        <?php } ?>
      </div>
      <?php } ?>
    </div>

    <div class="row-meta">
      <span class="row-cat"><?= e($cat) ?></span>
      <?php if ($date) { ?><span class="row-date"><?= $date ?></span><?php } ?>
      <span class="row-min"><?= $minutes ?> Min</span>
    </div>

    <span class="row-arrow" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M7 17L17 7M9 7h8v8"/></svg>
    </span>
  </article>
<?php } ?>
