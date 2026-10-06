{% extends "back.lex.php" %}

{% block title %}Technical · Insights{% endblock %}
{% block subtitle %}<?= e($scope === 'site'
    ? 'What the counter let through and what it left out, across the whole site.'
    : 'Things that get in readers\' way on the platform\'s own pages, such as links to pages that don\'t exist.') ?>{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}

  <?php if ($scope === 'platform') {
      $missingHint = 'Readers who arrived at an address on the platform that isn\'t a page, and the sites that sent them.'; ?>
  {% include "partials/dashboard/insights/_missing.lex.php" %}
  {% include "partials/dashboard/insights/_speed.lex.php" %}
  <?php } else {
      $outcomeLabels = [
          'recorded' => ['Counted', 'Real readers\' page views, stored.'],
          'duplicate' => ['Repeat views', 'The same page again within 30 minutes, counted once.'],
          'bot' => ['Crawlers', 'User agents on the crawler list, built in or added in Settings.'],
          'hosting' => ['Server networks', 'Views from hosting providers\' networks, which people don\'t read from. Network data: IP geolocation by DB-IP.'],
          'opted_out' => ['Asked not to be counted', 'Browsers sending Global Privacy Control or Do Not Track.'],
          'member' => ['Teams and staff', 'Administrators, people acting as someone else, and blog teams on their own blogs.'],
          'prefetch' => ['Prefetches', 'Pages loaded ahead of time that may never be seen.'],
          'excluded_path' => ['Left out by owners', 'Paths a blog owner chose not to count.'],
          'unknown_page' => ['Not a public page', 'Drafts, previews and paths that match no page.'],
          'not_found' => ['Missing pages', 'Readers who reached an address that isn\'t a page.'],
          'spam' => ['Referrer spam', 'Views claiming to come from a known spam site.'],
          'disabled' => ['Counting off', 'Beacons that arrived while counting was switched off.'],
          'cross_site' => ['Other sites', 'Requests that didn\'t come from a page on this site.'],
          'too_large' => ['Too large', 'Request bodies over the size limit.'],
          'invalid' => ['Malformed', 'Bodies that weren\'t a valid page view.'],
      ];
      $allBeacons = array_sum($outcomes);
      $refused = $allBeacons - ($outcomes['recorded'] ?? 0); ?>
  <section class="card mb-0" aria-labelledby="insights-outcomes">
    <div class="card-body">
      <h2 id="insights-outcomes" class="text-15 font-semibold text-slate-800 dark:text-zink-50">What the counter let through</h2>
      <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300">
        Every page view beacon in this range and what happened to it. Script-like views were stored but are left out of every total:
        <?= e($present->number($scriptViews)) ?>, from visitors who opened <?= e($present->number($scriptMinViews)) ?> or more pages in a day without once closing or hiding one.
      </p>
      <?php if ($outcomes === []) { ?>
      <p class="py-4 text-sm text-slate-500 dark:text-zink-300">No beacons in this range.</p>
      <?php } else { ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold">Outcome</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left">Beacons</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left">Share</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($outcomes as $outcome => $count) {
                [$outcomeLabel, $outcomeHint] = $outcomeLabels[$outcome] ?? [$outcome, '']; ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
              <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right">
                <span class="text-slate-800 dark:text-zink-100"><?= e($outcomeLabel) ?></span>
                <span class="block text-xs text-slate-500 dark:text-zink-300"><?= e($outcomeHint) ?></span>
              </th>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($count)) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e((string) $present->percent($count / max(1, $allBeacons))) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <p class="mt-2 text-xs text-slate-500 dark:text-zink-300"><?= e($present->number($refused)) ?> of <?= e($present->number($allBeacons)) ?> beacons were not counted.</p>
      <?php } ?>
    </div>
  </section>

  {% include "partials/dashboard/insights/_speed.lex.php" %}

  <section class="card mb-0" aria-labelledby="insights-server-errors">
    <div class="card-body">
      <h2 id="insights-server-errors" class="text-15 font-semibold text-slate-800 dark:text-zink-50">Server errors</h2>
      <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300">Pages that answered with a 5xx error, most often first. The error log has the details of each one.</p>
      <?php if ($report['serverErrors'] === []) { ?>
      <p class="py-4 text-sm text-slate-500 dark:text-zink-300">No page answered with a server error in this range.</p>
      <?php } else { ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold">Path</th>
              <th scope="col" class="px-3 py-2 font-semibold">Status</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left">Errors</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left">Last seen (UTC)</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['serverErrors'] as $serverError) { ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
              <td class="px-3 py-2"><code class="text-slate-700 dark:text-zink-100" dir="ltr"><?= e($serverError['path']) ?></code></td>
              <td class="px-3 py-2 tabular-nums"><?= (int) $serverError['status'] ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($serverError['errors'])) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums whitespace-nowrap"><?= e(substr($serverError['last_seen'], 0, 16)) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php } ?>
    </div>
  </section>
  <?php } ?>

<?php
$aboutParagraphs = $scope === 'site' ? [
    'Every beacon is checked before it is counted. Those that fail a check are tallied here instead.',
    'Days are UTC.',
] : [
    'Missing pages are kept for 30 days, because their addresses come from strangers. Each blog\'s own missing pages are on that blog\'s Technical page.',
    'Days are UTC.',
];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
