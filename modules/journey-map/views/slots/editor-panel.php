<?php
/**
 * The Places section. It sits inside the post editor's form, so its fields carry no
 * name and its buttons are plain buttons: saving the post never sends them.
 *
 * @var \Closure $t
 * @var int|null $postId
 * @var list<array<string, mixed>> $stops The post's places in order
 * @var int $maxStops
 * @var string|null $road How the roads this post travels stand, null without a road network
 * @var array{save: string, remove: string, places: string, guide: string} $urls
 * @var string $token
 * @var bool $searchOn Whether the admin has place search switched on
 * @var array<string, string>|null $map
 */
use Modules\JourneyMap\Partial;

$inputClass = implode(' ', [
    'block w-full rounded-md border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 outline-none',
    'focus:border-custom-500 focus:ring-1 focus:ring-custom-200',
    'dark:border-zink-600 dark:bg-zink-800 dark:text-zink-100',
]);
$labelClass = 'mb-1 block text-xs font-medium text-slate-700 dark:text-zink-200';
$hintClass = 'text-[11px] leading-relaxed text-slate-500 dark:text-zink-400';
$names = array_map(static fn (array $stop): string => (string) $stop['place_name'], $stops);
$summary = match (count($names)) {
    0 => $t('editor.summary'),
    1 => $names[0],
    default => $names[0].' → '.$names[count($names) - 1],
};
$places = array_map(static fn (array $stop): array => [
    'place_name' => (string) $stop['place_name'],
    'lat' => (string) $stop['lat'],
    'lng' => (string) $stop['lng'],
    'precision' => (string) $stop['place_precision'],
], $stops);
$messages = [
    'noResults' => $t('editor.noResults'),
    'failed' => $t('editor.failed'),
    'saveFailed' => $t('editor.saveFailed'),
    'placeItem' => $t('editor.placeItem'),
    'errorAt' => $t('editor.errorAt'),
    'nameNeeded' => $t('editor.nameNeeded'),
    'moveUp' => $t('editor.moveUp'),
    'moveDown' => $t('editor.moveDown'),
    'removePlace' => $t('editor.removePlace'),
];
?>
<details class="post-panel rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zink-600 dark:bg-zink-700">
  <summary class="post-panel-head flex cursor-pointer items-center gap-2 border-slate-200 p-4 dark:border-zink-600">
    <i data-lucide="map-pin" class="size-4 shrink-0 text-slate-400 dark:text-zink-400" aria-hidden="true"></i>
    <div>
      <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100"><?= e($t('editor.title')) ?></h3>
      <p class="mt-0.5 text-xs text-slate-500 dark:text-zink-300"><?= e($summary) ?></p>
    </div>
    <i data-lucide="chevron-down" class="post-panel-chevron ml-auto size-4 shrink-0 text-slate-400 dark:text-zink-400"
      aria-hidden="true"></i>
  </summary>
  <div class="space-y-3 p-4 md:p-5">
    <?php if ($postId === null) { ?>
      <p class="<?= $hintClass ?>"><?= e($t('editor.saveFirst')) ?></p>
    <?php } else { ?>
      <div class="journey-map-editor" data-journey-map-editor
        data-save-url="<?= e($urls['save']) ?>"
        data-remove-url="<?= e($urls['remove']) ?>"
        data-places-url="<?= e($urls['places']) ?>"
        data-token="<?= e($token) ?>"
        data-max="<?= (int) $maxStops ?>">
        <script type="application/json" data-journey-map-text><?= Partial::json($messages) ?></script>
        <script type="application/json" data-journey-map-places><?= Partial::json($places) ?></script>

        <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_320px]">
          <div class="min-w-0 space-y-3">
            <?php if ($searchOn) { ?>
              <div>
                <label class="<?= $labelClass ?>" for="journey-map-query"><?= e($t('editor.searchLabel')) ?></label>
                <div class="flex gap-2">
                  <input id="journey-map-query" type="search" autocomplete="off" data-field="query"
                    class="<?= $inputClass ?>">
                  <button type="button" class="journey-map-button" data-action="search">
                    <?= e($t('editor.searchButton')) ?>
                  </button>
                </div>
                <ul class="journey-map-results" data-results hidden></ul>
              </div>
            <?php } ?>

            <?php if ($map !== null) { ?>
              <div class="journey-map-editor-map" data-map="<?= e(Partial::json($map)) ?>"></div>
              <p class="<?= $hintClass ?>"><?= e($t('editor.pinHint')) ?></p>
            <?php } else { ?>
              <p class="<?= $hintClass ?>"><?= e($t('editor.noMap')) ?></p>
            <?php } ?>
          </div>

          <div class="space-y-3">
            <div>
              <p class="<?= $labelClass ?>" id="journey-map-places-label"><?= e($t('editor.placesLabel')) ?></p>
              <ol class="journey-map-places" data-places aria-labelledby="journey-map-places-label"></ol>
              <button type="button" class="journey-map-button journey-map-button-quiet mt-2" data-action="add">
                <?= e($t('editor.addPlace')) ?>
              </button>
              <p class="mt-1 <?= $hintClass ?>"><?= e($t('editor.placesHint')) ?></p>
            </div>

            <div>
              <label class="<?= $labelClass ?>" for="journey-map-name"><?= e($t('editor.placeName')) ?></label>
              <input id="journey-map-name" type="text" maxlength="160" data-field="place_name"
                class="<?= $inputClass ?>">
            </div>

            <div class="grid grid-cols-2 gap-2">
              <input type="text" inputmode="decimal" data-field="lat" aria-label="<?= e($t('editor.lat')) ?>"
                class="<?= $inputClass ?>">
              <input type="text" inputmode="decimal" data-field="lng" aria-label="<?= e($t('editor.lng')) ?>"
                class="<?= $inputClass ?>">
            </div>

            <div>
              <label class="<?= $labelClass ?>" for="journey-map-precision"><?= e($t('editor.precision')) ?></label>
              <select id="journey-map-precision" data-field="precision" class="<?= $inputClass ?>">
                <?php foreach (['exact' => 'precisionExact', 'area' => 'precisionArea'] as $option => $label) { ?>
                  <option value="<?= $option ?>"><?= e($t('editor.'.$label)) ?></option>
                <?php } ?>
              </select>
            </div>

            <div>
              <label class="<?= $labelClass ?>" for="journey-map-number"><?= e($t('editor.stopNumber')) ?></label>
              <input id="journey-map-number" type="number" min="1" max="9999" data-field="stop_number"
                class="<?= $inputClass ?>" value="<?= e((string) ($stops[0]['stop_number'] ?? '')) ?>">
              <p class="mt-1 <?= $hintClass ?>"><?= e($t('editor.stopNumberHint')) ?></p>
            </div>

            <div class="flex flex-wrap gap-2">
              <button type="button" class="journey-map-button" data-action="save"><?= e($t('editor.save')) ?></button>
              <button type="button" class="journey-map-button journey-map-button-quiet" data-action="remove">
                <?= e($t('editor.remove')) ?>
              </button>
            </div>
            <p class="<?= $hintClass ?>" data-status role="status" aria-live="polite"></p>
            <?php if ($road !== null) { ?>
              <p class="<?= $hintClass ?>" data-road><?= e($road) ?></p>
            <?php } ?>
          </div>
        </div>
      </div>
    <?php } ?>
    <a class="inline-block text-xs text-custom-500 underline underline-offset-2 hover:decoration-2"
      href="<?= e($urls['guide']) ?>"><?= e($t('editor.guideLink')) ?></a>
  </div>
</details>
