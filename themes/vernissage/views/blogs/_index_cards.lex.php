<?php
// Framed works for "The Collection". Shared by the landing and the AJAX room
// swap (rendered standalone by BlogController::indexFeed). Each work carries
// its category in a data attribute so the room filter can rehang the wall.
$blogSlug = urlencode($blog['blog_slug'] ?? '');
$ownerNameRaw = $user['display_name_cached'] ?? $user['username'] ?? 'The Curator';
$ownerName = e($ownerNameRaw);
$validImg = '#^(https?://|/|data:)#i';

foreach (($cards ?? []) as $i => $post) {
    $cover = (string) ($post['featured_image'] ?? '');
    $img = preg_match($validImg, $cover) ? $cover : 'https://picsum.photos/seed/vernissage-work-'.($i + 1).'/900/720';
    $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
    $title = e($post['title'] ?? 'Untitled');
    $cat = trim((string) ($post['category'] ?? 'Work'));
    $catSlug = (string) ($post['category_slug'] ?? '');
    $date = e(local_datetime($post['published_at'] ?? null, 'j M Y', blog_timezone((int) ($blog['id'] ?? 0))));
    $author = profile_link($post['author_name'] ?? $ownerNameRaw, $user['public_profile_slug'] ?? null);
    $minutes = reading_time($post['content'] ?? '');
    $tags = is_array($post['tags'] ?? null) ? $post['tags'] : [];

    $year = local_datetime($post['published_at'] ?? null, 'Y', blog_timezone((int) ($blog['id'] ?? 0))) ?: date('Y');
    ?>
  <article class="work reveal" data-category="<?= e($catSlug !== '' ? $catSlug : strtolower($cat)) ?>" onclick="location.href='<?= $url ?>'">
    <a href="<?= $url ?>" class="frame work-frame" aria-hidden="true" tabindex="-1">
      <span class="frame-img"><img src="<?= e($img) ?>" alt="" loading="lazy" /></span>
    </a>

    <div class="work-plaque">
      <span class="plaque"><span class="plaque-no">Cat. <?= sprintf('%02d', $i + 1) ?></span> &mdash; <?= e($cat) ?></span>
      <h3 class="work-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
      <p class="work-attrib"><?= $author ?>, <?= e($year) ?></p>

      <div class="work-meta">
        <?php if ($date) { ?><span><?= $date ?></span><?php } ?>
        <span><?= $minutes ?> Min</span>
        <?php if (!empty($tags)) { ?>
          <?php foreach (array_slice($tags, 0, 2) as $t) {
              $tUrl = lurl('/blog/'.$blogSlug.'/tag/'.urlencode((string) ($t['slug'] ?? '')));
              ?>
          <a class="work-tag" href="<?= $tUrl ?>" onclick="event.stopPropagation();">#<?= e((string) ($t['name'] ?? '')) ?></a>
          <?php } ?>
        <?php } ?>
      </div>
    </div>
  </article>
<?php } ?>
