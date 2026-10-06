{% extends "back.lex.php" %}

{% block title %}SEO · Insights{% endblock %}
{% block subtitle %}<?= e($scope === 'site' ? 'Readers who came from search engines across the site: how many, from which engine, and where they landed.' : 'Readers who reached the platform\'s own pages from a search engine.') ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}

  {% include "partials/dashboard/insights/pages/_seo.lex.php" %}

  <?php if (($report['site'] ?? null) !== null) {
      $site = $report['site']; ?>
  <section class="card mb-0" aria-labelledby="insights-site-indexable">
    <div class="card-body">
      <h2 id="insights-site-indexable" class="text-15 font-semibold text-slate-800 dark:text-zink-50">Offered to search engines</h2>
      <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-800 dark:text-zink-50">
        <?= e($present->number($site['indexable'])) ?> of <?= e($present->number($site['published'])) ?> published posts
      </p>
      <p class="mt-1 text-xs text-slate-500 dark:text-zink-300">
        The rest are noindex, unlisted or private, point their canonical address elsewhere, or sit on a blog hidden from search engines.
        Each blog's SEO page lists its own posts and what to fix.
      </p>
      <?php if ($site['beyondSitemap'] > 0) { ?>
      <p class="mt-3 px-3 py-2 text-xs rounded-md border border-yellow-200 bg-yellow-50 text-yellow-800 dark:bg-yellow-500/10 dark:border-yellow-500/30 dark:text-yellow-300" role="status">
        The sitemap lists the newest <?= e($present->number($site['sitemapLimit'])) ?> of them, so <?= e($present->number($site['beyondSitemap'])) ?> older posts are only found through links.
      </p>
      <?php } ?>
    </div>
  </section>
  <?php } ?>

<?php
$aboutParagraphs = ['Only what happens on this site: there is no data from search engines themselves, so no positions, impressions or clicks in search results.', 'Days are UTC.'];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
