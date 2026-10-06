<?php
/**
 * Posts ranked by views, each opening its own Insights page. Overview shows a few
 * with a link to Content ($topPostsMore); Content shows them all with an export
 * and, on a blog, each post's shape. The control panel adds which blog each is on.
 */
$topPostsMore ??= null;
$performance ??= ['posts' => []];
$showShape = $topPostsMore === null && !$isAdmin;
?>
  <section class="card mb-0" aria-labelledby="insights-top-posts">
    <div class="card-body">
      <div class="flex items-center justify-between gap-2 mb-3">
        <h2 id="insights-top-posts" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.topPosts.title')) ?></h2>
        <?php if ($topPostsMore !== null) { ?>
        <a href="<?= e($topPostsMore) ?>" data-insights-page-link class="text-xs font-medium text-custom-500 hover:underline"><?= e($t('analytics.pages.seeAll')) ?></a>
        <?php } elseif ($report['topPosts'] !== []) { ?>
        <a href="<?= e(lurl($basePath.'/export').'?'.http_build_query($range->query() + ['dimension' => 'posts'])) ?>"
           class="inline-flex items-center gap-1 text-xs text-slate-500 hover:text-custom-500 dark:text-zink-300"
           data-tooltip data-tooltip-content="<?= e($t('analytics.topPosts.exportHint')) ?>" data-tooltip-placement="top">
          {% cache 'lucide:download:insights-export' ttl=31536000 %}<i data-lucide="download" class="size-3.5" aria-hidden="true"></i>{% endcache %}
          <?= e($downloadLabel) ?>
        </a>
        <?php } ?>
      </div>
      <?php if ($report['topPosts'] === []) { ?>
      <p class="py-6 text-center text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.topPosts.empty')) ?></p>
      <?php } else { ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('analytics.topPosts.post')) ?></th>
              <?php if ($isAdmin) { ?>
              <th scope="col" class="px-3 py-2 font-semibold">Blog</th>
              <?php } ?>
              <?php if ($showShape) { ?>
              <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('analytics.topPosts.shape')) ?></th>
              <?php } ?>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.views')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.visitors')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.readRatio')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.avgRead')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['topPosts'] as $row) {
                $views = (int) $row['views'];
                $engaged = (int) $row['engaged_views'];
                $postPerformance = $performance['posts'][(int) $row['post_id']] ?? null; ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0 hover:bg-slate-50 dark:hover:bg-zink-600/50">
              <td class="px-3 py-2">
                <?php if ($row['title'] === null) { ?>
                <span class="text-slate-400 dark:text-zink-400"><?= e($t('analytics.topPosts.deleted')) ?></span>
                <?php } else { ?>
                <a href="<?= e($postInsightsHref($row)) ?>"
                   class="text-slate-800 hover:text-custom-500 dark:text-zink-100" dir="auto"><?= e((string) $row['title']) ?></a>
                <?php } ?>
              </td>
              <?php if ($isAdmin) { ?>
              <td class="px-3 py-2 text-slate-500 dark:text-zink-300" dir="auto"><?= e((string) ($row['blog_name'] ?? '')) ?></td>
              <?php } ?>
              <?php if ($showShape) { ?>
              <td class="px-3 py-2">
                <?php if (($postPerformance['label'] ?? null) !== null) { ?>
                {% include "partials/dashboard/insights/_shape_badge.lex.php" %}
                <?php } ?>
              </td>
              <?php } ?>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($views)) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number((int) $row['visitors'])) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent($views > 0 ? (int) $row['read_views'] / $views : null) ?? $none) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->duration($engaged > 0 ? (int) $row['engaged_seconds'] / $engaged : null) ?? $none) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php } ?>
    </div>
  </section>
