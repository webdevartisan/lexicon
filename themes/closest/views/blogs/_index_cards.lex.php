<?php
// Waypoint rows for "The route". Shared by the landing and the AJAX category
// swap (rendered standalone by BlogController::indexFeed). Rows are numbered
// from W-02 because W-01 is the nearest post pinned above the route, and the
// gap labels state the real publication gap between neighbouring waypoints.
$blogSlug = urlencode($blog['blog_slug'] ?? '');
$ownerName = e($user['display_name_cached'] ?? $user['username'] ?? 'The Surveyor');
$validImg = '#^(https?://|/|data:)#i';

$prevStamp = null;

foreach (($cards ?? []) as $i => $post) {
    $cover = (string) ($post['featured_image'] ?? '');
    $img = preg_match($validImg, $cover) ? $cover : 'https://picsum.photos/seed/closest-wp-'.($i + 2).'/900/680';
    $url = lurl('/blog/'.$blogSlug.'/'.urlencode($post['slug'] ?? ''));
    $title = e($post['title'] ?? 'Untitled');
    $cat = trim((string) ($post['category'] ?? 'Field note'));
    $catSlug = (string) ($post['category_slug'] ?? '');
    $author = e($post['author_name'] ?? $ownerName);
    $minutes = reading_time($post['content'] ?? '');
    $tags = is_array($post['tags'] ?? null) ? $post['tags'] : [];

    $stamp = strtotime((string) ($post['published_at'] ?? ''));
    $date = $stamp ? e(date('j M Y', $stamp)) : '';

    // Rows run newest to oldest, so the gap is measured back down the trail.
    $gapLabel = '';
    if ($prevStamp && $stamp) {
        $days = (int) floor(($prevStamp - $stamp) / 86400);
        if ($days <= 0) {
            $gapLabel = 'Same day';
        } elseif ($days === 1) {
            $gapLabel = '1 day back';
        } elseif ($days < 60) {
            $gapLabel = $days.' days back';
        } else {
            $gapLabel = round($days / 30).' months back';
        }
    }
    $prevStamp = $stamp ?: $prevStamp;
    ?>
  <?php if ($gapLabel !== '') { ?>
  <div class="wp-gap" aria-hidden="true"><span><?= e($gapLabel) ?></span></div>
  <?php } ?>

  <article class="waypoint reveal" data-category="<?= e($catSlug !== '' ? $catSlug : strtolower($cat)) ?>" onclick="location.href='<?= $url ?>'">
    <span class="wp-node" aria-hidden="true"></span>
    <span class="wp-no">W-<?= sprintf('%02d', $i + 2) ?></span>

    <span class="inset wp-thumb" aria-hidden="true">
      <span class="inset-img"><img src="<?= e($img) ?>" alt="" loading="lazy" /></span>
    </span>

    <div class="wp-main">
      <h3 class="wp-title"><a href="<?= $url ?>"><?= $title ?></a></h3>
      <span class="wp-author">By <?= $author ?></span>
      <?php if (!empty($tags)) { ?>
      <div class="wp-tags">
        <?php foreach (array_slice($tags, 0, 2) as $t) {
            $tUrl = lurl('/blog/'.$blogSlug.'/tag/'.urlencode((string) ($t['slug'] ?? '')));
            ?>
        <a class="wp-tag" href="<?= $tUrl ?>" onclick="event.stopPropagation();">#<?= e((string) ($t['name'] ?? '')) ?></a>
        <?php } ?>
      </div>
      <?php } ?>
    </div>

    <div class="wp-meta">
      <span class="wp-chip"><?= e($cat) ?></span>
      <?php if ($date) { ?><span class="wp-date"><?= $date ?></span><?php } ?>
      <span class="wp-min"><?= $minutes ?> Min</span>
    </div>

    <span class="wp-arrow" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </span>
  </article>
<?php } ?>
