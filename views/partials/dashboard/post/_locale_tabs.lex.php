<?php
// Language tabs for localized posts. Each tab is its own page: the default
// locale is the regular edit form, every other locale is a translation form.
$tabsPostId = (int) ($post['id'] ?? 0);
$tabsDefault = $defaultLocale ?? 'en';
$tabsActive = $activeLocale ?? $tabsDefault;
$tabsDone = $translations ?? [];

$tabOn = 'bg-custom-500 text-white border-custom-500 dark:bg-custom-600 dark:border-custom-600';
$tabOff = 'bg-white text-slate-600 border-slate-200 hover:border-custom-400 hover:text-custom-600 dark:bg-zink-700 dark:text-zink-200 dark:border-zink-500 dark:hover:border-custom-500';
?>
<?php if (!empty($translationsEnabled) && $tabsPostId > 0) { ?>
<div class="mb-4">
  <nav class="flex flex-wrap items-center gap-1.5" aria-label="Post languages">
    <a href="<?= lurl('/dashboard/post/'.$tabsPostId.'/edit') ?>"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors <?= $tabsActive === $tabsDefault ? $tabOn : $tabOff ?>">
      {{ t('post.translations.original') }} · <?= strtoupper(e($tabsDefault)) ?>
    </a>

    <?php foreach (($availableLocales ?? []) as $loc) { ?>
      <?php if ($loc === $tabsDefault) {
          continue;
      } ?>
      <a href="<?= lurl('/dashboard/post/'.$tabsPostId.'/translations/'.$loc) ?>"
         class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border transition-colors <?= $tabsActive === $loc ? $tabOn : $tabOff ?>">
        <?= strtoupper(e($loc)) ?>
        <?php if (isset($tabsDone[$loc])) { ?>
          <span class="size-1.5 rounded-full <?= $tabsActive === $loc ? 'bg-white' : 'bg-emerald-500' ?>" title="Translated"></span>
        <?php } ?>
      </a>
    <?php } ?>
  </nav>
  <p class="mt-1.5 text-[11px] text-slate-400 dark:text-zink-400">{{ t('post.translations.tabsHint') }}</p>
</div>
<?php } ?>
