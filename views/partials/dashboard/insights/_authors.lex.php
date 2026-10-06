<?php
/**
 * Each author in the range: what they published and how it was read, their
 * most read posts, and how often each one published over the last months. Shared
 * by a blog's Authors page and the control panel's, which links posts by blog.
 *
 * $authorPostHref turns a top-post row into a link, or null for plain text.
 */
$authorRows = $report['authors'];
$months = [];
$monthStart = (new \DateTimeImmutable($range->toDate()))->modify('first day of this month');
for ($i = 5; $i >= 0; $i--) {
    $months[] = $monthStart->modify("-{$i} months");
}
$showVisitors = $scope === 'blog';
?>
<section class="card mb-0" aria-labelledby="insights-authors">
  <div class="card-body">
    <h2 id="insights-authors" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.authors.title')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.authors.hint')) ?></p>
    <?php if ($authorRows === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.authors.empty')) ?></p>
    <?php } else { ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
          <tr class="border-b border-slate-200 dark:border-zink-500">
            <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('analytics.authors.author')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.authors.published')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.views')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.authors.viewsPerPost')) ?></th>
            <?php if ($showVisitors) { ?>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.visitors')) ?></th>
            <?php } ?>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.metrics.readRatio')) ?></th>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left"><?= e($t('analytics.goals.title')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($authorRows as $author) {
              $views = (int) $author['views'];
              $postsRead = (int) $author['posts_read']; ?>
          <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
            <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right">
              <span class="block text-slate-800 dark:text-zink-100" dir="auto"><?= e($author['name'] ?? $t('analytics.breakdowns.unknown')) ?></span>
            </th>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number((int) $author['published'])) ?></td>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($views)) ?></td>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($postsRead > 0 ? $present->number((int) round($views / $postsRead)) : $none) ?></td>
            <?php if ($showVisitors) { ?>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number((int) $author['visitors'])) ?></td>
            <?php } ?>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->percent($views > 0 ? (int) $author['read_views'] / $views : null) ?? $none) ?></td>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number((int) $author['goals'])) ?></td>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>
</section>

<section class="card mb-0" aria-labelledby="insights-author-posts">
  <div class="card-body">
    <h2 id="insights-author-posts" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.authors.topPosts')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.authors.topPostsHint')) ?></p>
    <?php $readAuthors = array_filter($authorRows, static fn (array $author): bool => $author['top_posts'] !== []); ?>
    <?php if ($readAuthors === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.topPosts.empty')) ?></p>
    <?php } else { ?>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
      <?php foreach ($readAuthors as $author) { ?>
      <div>
        <h3 class="mb-1 text-sm font-medium text-slate-800 dark:text-zink-100" dir="auto"><?= e($author['name'] ?? $t('analytics.breakdowns.unknown')) ?></h3>
        <ol class="flex flex-col gap-1 text-sm">
          <?php foreach ($author['top_posts'] as $topPost) {
              $topHref = $authorPostHref($topPost);
              $topTitle = (string) ($topPost['title'] ?? $t('analytics.topPosts.deleted')); ?>
          <li class="flex items-baseline justify-between gap-3">
            <?php if ($topHref !== null) { ?>
            <a href="<?= e($topHref) ?>" class="min-w-0 truncate text-slate-600 hover:text-custom-500 dark:text-zink-200" dir="auto"><?= e($topTitle) ?></a>
            <?php } else { ?>
            <span class="min-w-0 truncate text-slate-600 dark:text-zink-200" dir="auto"><?= e($topTitle) ?></span>
            <?php } ?>
            <span class="shrink-0 tabular-nums text-slate-500 dark:text-zink-300"><?= e($present->number((int) $topPost['views'])) ?></span>
          </li>
          <?php } ?>
        </ol>
      </div>
      <?php } ?>
    </div>
    <?php } ?>
  </div>
</section>

<section class="card mb-0" aria-labelledby="insights-publishing">
  <div class="card-body">
    <h2 id="insights-publishing" class="text-15 font-semibold text-slate-800 dark:text-zink-50"><?= e($t('analytics.authors.publishing')) ?></h2>
    <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300"><?= e($t('analytics.authors.publishingHint')) ?></p>
    <?php if ($report['publishing'] === []) { ?>
    <p class="py-4 text-sm text-slate-500 dark:text-zink-300"><?= e($t('analytics.authors.noPublishing')) ?></p>
    <?php } else { ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
          <tr class="border-b border-slate-200 dark:border-zink-500">
            <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('analytics.authors.author')) ?></th>
            <?php foreach ($months as $month) { ?>
            <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left whitespace-nowrap"><?= e($present->point($month->format('Y-m-d'), 'month')) ?></th>
            <?php } ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($authorRows as $author) {
              $byMonth = $report['publishing'][(int) $author['author_id']] ?? [];
              if ($byMonth === []) {
                  continue;
              } ?>
          <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
            <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right text-slate-700 dark:text-zink-100" dir="auto"><?= e($author['name'] ?? $t('analytics.breakdowns.unknown')) ?></th>
            <?php foreach ($months as $month) {
                $count = $byMonth[$month->format('Y-m')] ?? 0; ?>
            <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums <?= $count === 0 ? 'text-slate-400 dark:text-zink-400' : '' ?>"><?= e($present->number($count)) ?></td>
            <?php } ?>
          </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </div>
</section>
