<?php
$popularPosts = app(\App\Services\Traffic\PopularPosts::class)->forBlog((int) ($blog['id'] ?? 0), $settings ?? []);
?>
<?php if ($popularPosts !== []) { ?>
<aside class="lx-popular" aria-labelledby="lx-popular-title">
  <h2 id="lx-popular-title" class="lx-popular-title"><?= e($t('traffic.popular.title')) ?></h2>
  <ol class="lx-popular-list">
    <?php foreach ($popularPosts as $popularPost) { ?>
    <li><a href="<?= e(lurl('/blog/'.rawurlencode((string) ($blog['blog_slug'] ?? '')).'/'.rawurlencode($popularPost['slug']))) ?>" dir="auto"><?= e($popularPost['title']) ?></a></li>
    <?php } ?>
  </ol>
</aside>
<?php } ?>
