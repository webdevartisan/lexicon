<?php
$viewCount = (int) (($postViews ?? [])[$pid]['views'] ?? 0);
?>
<?php if ($viewCount > 0) { ?>
<a href="<?= e(lurl('/dashboard/blog/'.(int) $blog['id'].'/analytics/traffic/posts/'.$pid)) ?>"
   class="inline-flex items-center gap-1 hover:text-custom-500" title="<?= e($t('traffic.topPosts.details')) ?>">
  {% cache 'lucide:bar-chart-3:traffic-post-views' ttl=31536000 %}<i data-lucide="bar-chart-3" class="size-3" aria-hidden="true"></i>{% endcache %}
  <?= e($t($viewCount === 1 ? 'traffic.viewsOne' : 'traffic.viewsMany', ['count' => number_format($viewCount)])) ?>
</a>
<?php } ?>
