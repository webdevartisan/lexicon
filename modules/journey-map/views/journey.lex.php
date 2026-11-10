{% extends "base.lex.php" %}
{% block title %}<?= e($meta['title']) ?>{% endblock %}

{% block content %}
<?php
// The map gets every place; the list gets one entry per post, reaching to the post's last place.
$places = [];
$cards = [];
foreach ($stops as $index => $stop) {
    $last = $cards === [] ? null : array_key_last($cards);
    if ($last !== null && $cards[$last]['post_id'] === $stop['post_id']) {
        $cards[$last]['place'] = $cards[$last]['first_place'].' → '.$stop['place'];
        $cards[$last]['upTo'] = $index;
        $places[] = $stop + ['label' => $cards[$last]['label'], 'number' => null];
        continue;
    }

    $number = count($cards) + 1;
    $label = $t('journey-map::page.stopNumber', ['number' => $number]);
    $cards[] = $stop + ['label' => $label, 'first_place' => $stop['place'], 'upTo' => $index];
    $places[] = $stop + ['label' => $label, 'number' => $number];
}
?>
<section class="journey-map-page">
  <header class="journey-map-head">
    <h1><?= e($t('journey-map::page.title')) ?></h1>
    <p><?= e($blog['blog_name'] ?? '') ?></p>
    <?php if ($total['km'] > 0) { ?>
      <p class="journey-map-total"><?= e($t(
          'journey-map::page.'.($total['approximate'] ? 'totalAbout' : 'total'),
          ['stops' => count($cards), 'km' => \Modules\JourneyMap\Distance::rounded($total['km'])]
      )) ?></p>
    <?php } ?>
  </header>

  <?php if ($stops === []) { ?>
    <p class="journey-map-empty"><?= e($t('journey-map::page.empty')) ?></p>
  <?php } else { ?>
    <div class="journey-map-story">
      <?php if ($map !== null) { ?>
        <div class="journey-map-stage">
          <div class="journey-map-box journey-map-route" data-journey-map="journey"
            data-map="<?= e(\Modules\JourneyMap\Partial::json($map)) ?>"
            data-read-label="<?= e($t('journey-map::page.readStop')) ?>"
            role="region" aria-label="<?= e($t('journey-map::map.label')) ?>"></div>
          <script type="application/json" data-journey-map-stops>
            <?= \Modules\JourneyMap\Partial::json($places) ?>
          </script>
        </div>
      <?php } ?>

      <div class="journey-map-stops">
        <h2 class="journey-map-list-heading"><?= e($t('journey-map::page.listHeading')) ?></h2>
        <ol class="journey-map-list">
          <?php foreach ($cards as $index => $card) { ?>
            <li class="journey-map-stop" data-journey-stop="<?= (int) $card['upTo'] ?>">
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
      </div>
    </div>
  <?php } ?>
</section>
{% endblock %}

{% block scripts %}
  <?php foreach ($scripts as $src) { ?>
    <script src="<?= e($src) ?>" defer></script>
  <?php } ?>
{% endblock %}
