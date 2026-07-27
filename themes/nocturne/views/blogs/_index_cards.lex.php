<?php
// Index rows shared by the landing and the AJAX category swap
// (rendered standalone by BlogController::indexFeed).
$blogSlug = urlencode($blog['blog_slug'] ?? '');
$ownerNameRaw = $user['display_name_cached'] ?? $user['username'] ?? 'The Author';
$ownerName = e($ownerNameRaw);
$validImg = '#^(https?://|/|data:)#i';

foreach (($cards ?? []) as $i => $post) {
    $cover = (string) ($post['featured_image'] ?? '');
    $img = preg_match($validImg, $cover) ? e($cover) : 'https://picsum.photos/seed/nocturne-row-'.($i + 1).'/900/1080';
    $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
    $title = e($post['title'] ?? 'Untitled');
    $cat = trim((string) ($post['category'] ?? 'Post'));
    $catSlug = (string) ($post['category_slug'] ?? '');
    $date = e(local_datetime($post['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
    $author = profile_link($post['author_name'] ?? $ownerNameRaw, $user['public_profile_slug'] ?? null);
    $minutes = reading_time($post['content'] ?? '');
    $tags = is_array($post['tags'] ?? null) ? $post['tags'] : [];
    ?>
  <article class="idx-row reveal" data-category="<?= e($catSlug !== '' ? $catSlug : strtolower($cat)) ?>" data-cover="<?= $img ?>" data-card-link="<?= e($url) ?>">
    <span class="idx-num"><?= sprintf('%02d', $i + 1) ?></span>
    <h3 class="idx-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
    <div class="idx-thumb" aria-hidden="true">
      <img src="<?= $img ?>" alt="" loading="lazy" />
    </div>
    <div class="idx-meta">
      <span class="cat"><?= e($cat) ?></span>
      <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
      <span><?= $author ?> &middot; <?= $minutes ?> Min</span>
    </div>
    <?php if (!empty($tags)) { ?>
    <div class="idx-tags">
      <?php foreach (array_slice($tags, 0, 2) as $t) {
          $tUrl = lurl('/blog/'.$blogSlug.'/tag/'.urlencode((string) ($t['slug'] ?? '')));
          ?>
      <a class="idx-tag" href="<?= $tUrl ?>">#<?= e((string) ($t['name'] ?? '')) ?></a>
      <?php } ?>
    </div>
    <?php } ?>
  </article>
<?php } ?>
