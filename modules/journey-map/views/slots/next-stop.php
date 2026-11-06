<?php
/**
 * @var \Closure $t
 * @var string $title
 * @var string $url
 */
?>
<p class="journey-map-next"><a href="<?= e($url) ?>"><?= e($t('post.nextStop', ['title' => $title])) ?></a></p>
