{% extends "back.lex.php" %}

{% block title %}Authors · Insights{% endblock %}
{% block subtitle %}Every writer on the platform: what they published, how it was read, and who started publishing lately.{% endblock %}

{% block head %}
<link rel="stylesheet" href="/cp-assets/css/vendors/flatpickr.css">
{% endblock %}

{% block body %}
{% include "partials/dashboard/insights/_page_top.lex.php" %}
<?php
$authorPostHref = static fn (array $topPost): ?string => $topPost['title'] === null
    ? null
    : $postInsightsHref($topPost);
?>
  {% include "partials/dashboard/insights/_authors.lex.php" %}

  <section class="card mb-0" aria-labelledby="insights-new-writers">
    <div class="card-body">
      <h2 id="insights-new-writers" class="text-15 font-semibold text-slate-800 dark:text-zink-50">New writers</h2>
      <p class="mt-1 mb-3 text-xs text-slate-500 dark:text-zink-300">People whose first post went out in this range, and how many views it had in its first week.</p>
      <?php if ($report['newWriters'] === []) { ?>
      <p class="py-4 text-sm text-slate-500 dark:text-zink-300">Nobody published for the first time in this range.</p>
      <?php } else { ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="ltr:text-left rtl:text-right text-xs uppercase text-slate-500 dark:text-zink-300">
            <tr class="border-b border-slate-200 dark:border-zink-500">
              <th scope="col" class="px-3 py-2 font-semibold"><?= e($t('analytics.authors.author')) ?></th>
              <th scope="col" class="px-3 py-2 font-semibold">First post</th>
              <th scope="col" class="px-3 py-2 font-semibold">Blog</th>
              <th scope="col" class="px-3 py-2 font-semibold">Published</th>
              <th scope="col" class="px-3 py-2 font-semibold ltr:text-right rtl:text-left">First week</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($report['newWriters'] as $writer) { ?>
            <tr class="border-b border-slate-100 dark:border-zink-600 last:border-b-0">
              <th scope="row" class="px-3 py-2 font-normal ltr:text-left rtl:text-right text-slate-800 dark:text-zink-100" dir="auto"><?= e($writer['name'] ?? $t('analytics.breakdowns.unknown')) ?></th>
              <td class="px-3 py-2">
                <a href="<?= e($postInsightsHref($writer)) ?>"
                   class="text-slate-700 hover:text-custom-500 dark:text-zink-200" dir="auto"><?= e($writer['title']) ?></a>
              </td>
              <td class="px-3 py-2 text-slate-500 dark:text-zink-300" dir="auto"><?= e((string) ($writer['blog_name'] ?? '')) ?></td>
              <td class="px-3 py-2 whitespace-nowrap text-slate-500 dark:text-zink-300"><?= e($present->date($writer['published'])) ?></td>
              <td class="px-3 py-2 ltr:text-right rtl:text-left tabular-nums"><?= e($present->number($writer['first_week'])) ?></td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      <?php } ?>
    </div>
  </section>

<?php
$aboutParagraphs = [
    'Posts count for the person who wrote them, whichever blog they are on. Published counts posts that went out in the range. Daily visitors are counted per blog, so they are left out here.',
    'The numbers use each blog\'s own days, the same as its owner sees them.',
];
?>
{% include "partials/dashboard/insights/_page_bottom.lex.php" %}
{% endblock %}

{% block scripts %}
{% include "partials/dashboard/insights/_page_scripts.lex.php" %}
{% endblock %}
