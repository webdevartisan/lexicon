<?php
/**
 * When a post goes live and who can read it. Shown in the publish check when
 * the main button publishes, otherwise with the other post options.
 *
 * Expects: $post, $showScheduling, optional $publishZone, $canSetVisibility.
 */
$fieldLabel = 'mb-1.5 flex items-center gap-1 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zink-300';
$fieldSelect = 'form-select block w-full rounded-md border border-slate-300/80 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-custom-500 focus:ring-1 focus:ring-custom-200 dark:border-zink-600 dark:bg-zink-800 dark:text-zink-100';
?>
<?php if ($showScheduling) { ?>
<div>
  <?php $publishZone = (string) ($publishZone ?? site_timezone()); ?>
  <label for="published_at" class="<?= $fieldLabel ?>">
    <span>When</span>
    <span class="font-normal normal-case tracking-normal text-slate-400 ltr:ml-auto rtl:mr-auto dark:text-zink-400"><?= e(\App\Helpers\TimezoneHelper::label($publishZone)) ?></span>
  </label>
  <?php $publishedAt = old('published_at') ?? $post['published_at'] ?? null; ?>
  <input type="hidden" name="timezone" id="timezone" value="<?= e($publishZone) ?>">
  <input
    id="published_at"
    name="published_at"
    value="<?= e($publishedAt) ?>"
    type="text"
    class="form-input border-slate-200 placeholder:text-slate-400 focus:border-custom-500 focus:outline-none dark:border-zink-500 dark:bg-zink-700 dark:text-zink-100 dark:placeholder:text-zink-200 dark:focus:border-custom-800"
    data-provider="flatpickr"
    data-timezone="<?= e($publishZone) ?>"
    data-min-date="today"
    data-date-format="Y-m-d H:i:S"
    data-alt-format="M j, Y · h:i K"
    data-enable-time=""
    readonly="readonly"
    placeholder="Right away">
  <?php foreach (errors()['published_at'] ?? [] as $dateError) { ?>
  <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= e($dateError) ?></p>
  <?php } ?>
  <div class="mt-1.5 flex items-start justify-between gap-2">
    <p data-schedule-hint class="text-xs leading-relaxed text-slate-500 dark:text-zink-300"></p>
    <button type="button" data-clear-schedule
      class="shrink-0 text-xs font-medium text-slate-500 underline hover:no-underline dark:text-zink-300 <?= $publishedAt ? '' : 'hidden' ?>">
      Clear
    </button>
  </div>
</div>
<?php } ?>

<?php if (!empty($canSetVisibility)) {
    $visibilityChoices = ['public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private'];
    $selectedVisibility = (string) (old('visibility') ?? $post['visibility'] ?? 'public');
    $visibilityHelp = 'Public shows on the blog, in search and in discover. Unlisted needs the link. Private is for the blog team.';
    ?>
<div>
  <label for="visibility" class="<?= $fieldLabel ?>">
    Who can read it
    {% cmp="hint" text="{$visibilityHelp}" %}
  </label>
  <select id="visibility" name="visibility" data-native class="<?= $fieldSelect ?>">
    <?php foreach ($visibilityChoices as $choice => $choiceLabel) { ?>
    <option value="<?= e($choice) ?>" <?= $selectedVisibility === $choice ? 'selected' : '' ?>><?= e($choiceLabel) ?></option>
    <?php } ?>
  </select>
  <?php foreach (errors()['visibility'] ?? [] as $visibilityError) { ?>
  <p class="mt-1 text-sm text-red-600 dark:text-red-400"><?= e($visibilityError) ?></p>
  <?php } ?>
</div>
<?php } ?>
