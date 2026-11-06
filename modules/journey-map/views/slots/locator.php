<?php
/**
 * The post's leg of the journey: a small map when the map data is installed, and always the place line.
 *
 * @var \Closure $t
 * @var string $place
 * @var string $caption
 * @var array<string, string>|null $map
 * @var array<string, mixed> $view
 */
use Modules\JourneyMap\Partial;

?>
<figure class="journey-map-locator">
  <?php if ($map !== null) { ?>
    <div class="journey-map-box journey-map-post" data-journey-map="locator" role="group"
      aria-label="<?= e($t('map.locatorLabel', ['place' => $place])) ?>"
      data-map="<?= e(Partial::json($map)) ?>"
      data-view="<?= e(Partial::json($view)) ?>"
      data-next-label="<?= e($t('map.nextLabel')) ?>"></div>
  <?php } ?>
  <figcaption class="journey-map-place"><?= e($caption) ?></figcaption>
</figure>
