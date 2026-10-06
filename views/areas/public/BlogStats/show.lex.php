{% extends "front.lex.php" %}

{% block title %}<?= e($t('analytics.public.title', ['blog' => (string) $blog['blog_name']])) ?> | <?= e(site_setting('site_name', 'Lexicon')) ?>{% endblock %}

{% block meta %}
<meta name="robots" content="noindex" />
{% endblock %}

{% block body %}
<?php
$present = new \App\Presenters\AnalyticsPresenter(
    \App\Services\LocaleState::get()->chromeLocale,
    $t,
    app(\App\Services\LocaleRegistry::class),
    'blog'
);
$metrics = $report['metrics'];
$breakdowns = $report['breakdowns'];
$blogUrl = lurl('/blog/'.rawurlencode((string) $blog['blog_slug']));
$statsUrl = $blogUrl.'/stats';
$peak = max(1, ...array_map(static fn (array $point): int => $point['views'], $report['series'] ?: [['views' => 1]]));
$figures = [
    ['analytics.metrics.views', $present->number($metrics['views']['value'])],
    ['analytics.metrics.visitors', $present->number($metrics['visitors']['value'])],
    ['analytics.metrics.avgRead', $present->metric('duration', $metrics['avg_read_seconds']['value']) ?? $t('analytics.metrics.none')],
    ['analytics.metrics.readRatio', $present->metric('percent', $metrics['read_ratio']['value']) ?? $t('analytics.metrics.none')],
];
$sources = array_slice($breakdowns['source'], 0, 5);
?>
<section class="lx-wrap lx-section lx-stats">
    <header class="lx-page-head">
        <h1 dir="auto"><?= e($t('analytics.public.title', ['blog' => (string) $blog['blog_name']])) ?></h1>
        <p><?= e($t('analytics.public.intro')) ?> <a href="<?= e($blogUrl) ?>"><?= e($t('analytics.public.backToBlog')) ?></a></p>
    </header>

    <nav class="lx-explore-tabs" aria-label="<?= e($t('analytics.range.label')) ?>">
        <?php foreach ($ranges as $preset) {
            $active = $range->preset === $preset; ?>
        <a href="<?= e($statsUrl.'?range='.$preset) ?>" class="lx-explore-tab <?= $active ? 'active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($t('analytics.range.'.$preset)) ?></a>
        <?php } ?>
    </nav>

    <dl class="lx-profile-stats lx-stats-figures">
        <?php foreach ($figures as [$labelKey, $value]) { ?>
        <div class="lx-profile-stat">
            <dt><?= e($t($labelKey)) ?></dt>
            <dd><?= e($value) ?></dd>
        </div>
        <?php } ?>
    </dl>

    <?php if ($metrics['views']['value'] === 0) { ?>
    <p class="lx-muted"><?= e($t('analytics.chart.empty')) ?></p>
    <?php } else { ?>
    <figure class="lx-stats-chart">
        <figcaption class="lx-stats-caption"><?= e($t('analytics.public.perDay')) ?></figcaption>
        <ol class="lx-stats-bars" aria-hidden="true">
            <?php foreach ($report['series'] as $point) { ?>
            <li style="--lx-bar: <?= round($point['views'] / $peak * 100, 1) ?>%" title="<?= e($present->point($point['date'], $report['interval']).': '.$present->number($point['views'])) ?>"></li>
            <?php } ?>
        </ol>
        <details>
            <summary><?= e($t('analytics.chart.showTable')) ?></summary>
            <table class="lx-stats-table">
                <thead>
                    <tr><th scope="col"><?= e($t('analytics.chart.date')) ?></th><th scope="col"><?= e($t('analytics.chart.views')) ?></th><th scope="col"><?= e($t('analytics.chart.visitors')) ?></th></tr>
                </thead>
                <tbody>
                    <?php foreach (array_reverse($report['series']) as $point) { ?>
                    <tr>
                        <th scope="row"><?= e($present->point($point['date'], $report['interval'])) ?></th>
                        <td><?= e($present->number($point['views'])) ?></td>
                        <td><?= e($present->number($point['visitors'])) ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </details>
    </figure>

    <div class="lx-stats-lists">
        <section aria-labelledby="lx-stats-posts">
            <h2 id="lx-stats-posts"><?= e($t('analytics.topPosts.title')) ?></h2>
            <ol class="lx-stats-list">
                <?php foreach (array_slice($report['topPosts'], 0, 5) as $row) {
                    if ($row['title'] === null || $row['status'] !== 'published') {
                        continue;
                    } ?>
                <li>
                    <a href="<?= e($blogUrl.'/'.rawurlencode((string) $row['slug'])) ?>" dir="auto"><?= e((string) $row['title']) ?></a>
                    <span><?= e($present->number((int) $row['views'])) ?></span>
                </li>
                <?php } ?>
            </ol>
        </section>

        <section aria-labelledby="lx-stats-sources">
            <h2 id="lx-stats-sources"><?= e($t('analytics.breakdowns.source')) ?></h2>
            <?php if ($sources === []) { ?>
            <p class="lx-muted"><?= e($t('analytics.breakdowns.empty')) ?></p>
            <?php } else { ?>
            <ol class="lx-stats-list">
                <?php foreach ($sources as $row) { ?>
                <li><span dir="auto"><?= e((string) $row['value']) ?></span> <span><?= e($present->number($row['views'])) ?></span></li>
                <?php } ?>
            </ol>
            <?php } ?>
        </section>
    </div>
    <?php } ?>

    <p class="lx-muted lx-stats-note"><?= e($t('analytics.public.note')) ?> <a href="<?= e(lurl('/privacy')) ?>"><?= e($t('analytics.publicNoticeLink')) ?></a></p>
</section>
{% endblock %}
