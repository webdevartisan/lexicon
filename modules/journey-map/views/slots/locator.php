<?php
/**
 * The post's place: a small map when the map data is installed, and always the place name.
 *
 * @var \Closure $t
 * @var array<string, mixed> $stop
 * @var array<string, string>|null $map
 * @var array{lat: float, lng: float, zoom: int} $view
 */
use Modules\JourneyMap\Partial;

$place = (string) $stop['place_name'];
?>
<figure class="journey-map-locator">
  <?php if ($map !== null) { ?>
    <div class="journey-map-box" data-journey-map="locator" role="img"
      aria-label="<?= e($t('map.locatorLabel', ['place' => $place])) ?>"
      data-map="<?= e(Partial::json($map)) ?>"
      data-view="<?= e(Partial::json($view)) ?>"></div>
  <?php } ?>
  <figcaption class="journey-map-place"><?= e($place) ?></figcaption>
</figure>
