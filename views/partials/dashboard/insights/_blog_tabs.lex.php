<?php
/**
 * Staff looking at one blog from the control panel: which blog, the way back to
 * every blog, and the blog's pages, since the sidebar there lists the platform's.
 */
$allBlogsHref = lurl('/admin/insights/blogs').'?'.http_build_query($range->query());
$publicBlogHref = lurl('/blog/'.rawurlencode((string) $blog['blog_slug']));
?>
<div class="flex flex-col gap-3 pb-1 border-b border-slate-200 dark:border-zink-500">
  <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
    <a href="<?= e($allBlogsHref) ?>" class="inline-flex items-center gap-1 text-custom-500 hover:underline">
      {% cache 'lucide:arrow-left:insights-all-blogs' ttl=31536000 %}<i data-lucide="arrow-left" class="size-4 rtl:rotate-180" aria-hidden="true"></i>{% endcache %}
      All blogs
    </a>
    <a href="<?= e($publicBlogHref) ?>" target="_blank" rel="noopener"
       class="inline-flex items-center gap-1 text-slate-500 hover:text-custom-500 dark:text-zink-300">
      {% cache 'lucide:external-link:insights-open-blog' ttl=31536000 %}<i data-lucide="external-link" class="size-4" aria-hidden="true"></i>{% endcache %}
      Open the blog
    </a>
  </div>
  <nav class="flex flex-wrap gap-2 pb-2" aria-label="Insights pages for this blog">
    <?php foreach ($staffPages as $staffPage) {
        $tabActive = $staffPage === $page; ?>
    <a href="<?= e($insightsLink($staffPage)) ?>"
       class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-full border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 <?= $tabActive
           ? 'bg-custom-500 border-custom-500 text-white'
           : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-200' ?>"
       <?= $tabActive ? 'aria-current="page"' : '' ?>><?= e($t('navigation.insightsPages.'.$staffPage)) ?></a>
    <?php } ?>
  </nav>
</div>
