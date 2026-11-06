{% extends "base.lex.php" %}
{% block title %}<?= e($meta['title']) ?>{% endblock %}

{% block content %}
<?php
$cards = [];
foreach ($stops as $index => $stop) {
    $cards[] = $stop + ['label' => $t('journey-map::page.stopNumber', ['number' => $index + 1])];
}
?>
<section class="journey-map-page">
  <header class="journey-map-head">
    <h1><?= e($t('journey-map::page.title')) ?></h1>
    <p><?= e($blog['blog_name'] ?? '') ?></p>
  </header>

  <?php if ($stops === []) { ?>
    <p class="journey-map-empty"><?= e($t('journey-map::page.empty')) ?></p>
  <?php } else { ?>
    <?php if ($map !== null) { ?>
      <div class="journey-map-box journey-map-route" data-journey-map="journey"
        data-map="<?= e(\Modules\JourneyMap\Partial::json($map)) ?>"
        data-read-label="<?= e($t('journey-map::page.readStop')) ?>"
        role="region" aria-label="<?= e($t('journey-map::map.label')) ?>"></div>
      <script type="application/json" data-journey-map-stops><?= \Modules\JourneyMap\Partial::json($cards) ?></script>
    <?php } ?>

    <h2 class="journey-map-list-heading"><?= e($t('journey-map::page.listHeading')) ?></h2>
    <ol class="journey-map-list">
      <?php foreach ($cards as $card) { ?>
        <li class="journey-map-stop">
          <?php if ($card['cover'] !== null) { ?>
            <img src="<?= e($card['cover']) ?>" alt="" loading="lazy" decoding="async">
          <?php } ?>
          <div>
            <p class="journey-map-stop-meta"><?= e($card['label']) ?> · <?= e($card['place']) ?></p>
            <h3><a href="<?= e($card['url']) ?>"><?= e($card['title']) ?></a></h3>
            <?php if ($card['summary'] !== '') { ?>
              <p><?= e($card['summary']) ?></p>
            <?php } ?>
          </div>
        </li>
      <?php } ?>
    </ol>
  <?php } ?>
</section>
{% endblock %}

{% block scripts %}
  <?php foreach ($scripts as $src) { ?>
    <script src="<?= e($src) ?>" defer></script>
  <?php } ?>
{% endblock %}
