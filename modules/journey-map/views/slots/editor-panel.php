<?php
/**
 * The Place panel. It sits inside the post editor's form, so its fields carry no
 * name and its buttons are plain buttons: saving the post never sends them.
 *
 * @var \Closure $t
 * @var int|null $postId
 * @var array<string, mixed>|null $stop
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
$precision = (string) ($stop['place_precision'] ?? 'exact');
$value = static fn (string $column): string => e((string) ($stop[$column] ?? ''));
$messages = [
    'noResults' => $t('editor.noResults'),
    'failed' => $t('editor.failed'),
    'saveFailed' => $t('editor.saveFailed'),
];
?>
<section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zink-600 dark:bg-zink-700">
  <div class="space-y-3 p-4">
    <h3 class="text-sm font-semibold text-slate-900 dark:text-zink-100"><?= e($t('editor.title')) ?></h3>
    <?php if ($postId === null) { ?>
      <p class="<?= $hintClass ?>"><?= e($t('editor.saveFirst')) ?></p>
    <?php } else { ?>
      <div class="journey-map-editor space-y-3" data-journey-map-editor
        data-save-url="<?= e($urls['save']) ?>"
        data-remove-url="<?= e($urls['remove']) ?>"
        data-places-url="<?= e($urls['places']) ?>"
        data-token="<?= e($token) ?>">
        <script type="application/json" data-journey-map-text><?= Partial::json($messages) ?></script>

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

        <div>
          <label class="<?= $labelClass ?>" for="journey-map-name"><?= e($t('editor.placeName')) ?></label>
          <input id="journey-map-name" type="text" maxlength="160" data-field="place_name"
            class="<?= $inputClass ?>" value="<?= $value('place_name') ?>">
        </div>

        <div class="grid grid-cols-2 gap-2">
          <input type="text" inputmode="decimal" data-field="lat" aria-label="<?= e($t('editor.lat')) ?>"
            class="<?= $inputClass ?>" value="<?= $value('lat') ?>">
          <input type="text" inputmode="decimal" data-field="lng" aria-label="<?= e($t('editor.lng')) ?>"
            class="<?= $inputClass ?>" value="<?= $value('lng') ?>">
        </div>

        <div>
          <label class="<?= $labelClass ?>" for="journey-map-number"><?= e($t('editor.stopNumber')) ?></label>
          <input id="journey-map-number" type="number" min="1" max="9999" data-field="stop_number"
            class="<?= $inputClass ?>" value="<?= $value('stop_number') ?>">
          <p class="mt-1 <?= $hintClass ?>"><?= e($t('editor.stopNumberHint')) ?></p>
        </div>

        <div>
          <label class="<?= $labelClass ?>" for="journey-map-precision"><?= e($t('editor.precision')) ?></label>
          <select id="journey-map-precision" data-field="precision" class="<?= $inputClass ?>">
            <?php foreach (['exact' => 'precisionExact', 'area' => 'precisionArea'] as $option => $label) { ?>
              <option value="<?= $option ?>"<?= $precision === $option ? ' selected' : '' ?>>
                <?= e($t('editor.'.$label)) ?>
              </option>
            <?php } ?>
          </select>
        </div>

        <div class="flex flex-wrap gap-2">
          <button type="button" class="journey-map-button" data-action="save"><?= e($t('editor.save')) ?></button>
          <button type="button" class="journey-map-button journey-map-button-quiet" data-action="remove">
            <?= e($t('editor.remove')) ?>
          </button>
        </div>
        <p class="<?= $hintClass ?>" data-status role="status" aria-live="polite"></p>
      </div>
    <?php } ?>
    <a class="inline-block text-xs text-custom-500 underline underline-offset-2 hover:decoration-2"
      href="<?= e($urls['guide']) ?>"><?= e($t('editor.guideLink')) ?></a>
  </div>
</section>
