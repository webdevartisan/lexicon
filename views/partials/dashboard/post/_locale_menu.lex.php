<?php
/**
 * Language switch for a post that can be translated, shown as one small menu.
 * Each language is its own page: the blog's main language is the regular
 * editor, every other one is a translation form.
 *
 * Expects: $post, $translationsEnabled, $availableLocales, $translations,
 * $defaultLocale, optional $activeLocale.
 */
$menuPostId = (int) ($post['id'] ?? 0);
$menuDefault = $defaultLocale ?? 'en';
$menuActive = $activeLocale ?? $menuDefault;
$menuDone = $translations ?? [];
$menuItem = 'flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors hover:bg-slate-50 dark:hover:bg-zink-500';
$menuItemOn = 'font-semibold text-custom-600 dark:text-custom-300';
$menuItemOff = 'text-slate-600 dark:text-zink-200';
?>
<?php if (!empty($translationsEnabled) && $menuPostId > 0) { ?>
<details data-actionbar-menu data-locale-menu class="relative shrink-0">
  <summary
    class="flex h-9 cursor-pointer list-none items-center gap-1 rounded-md border border-slate-200 px-2.5 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-zink-500 dark:text-zink-200 dark:hover:bg-zink-600"
    aria-haspopup="menu"
    title="Post languages">
    <i data-lucide="languages" class="size-4" aria-hidden="true"></i>
    <?= e(strtoupper($menuActive)) ?>
    <span class="sr-only">Post languages</span>
  </summary>
  <div role="menu"
    class="absolute top-full z-50 mt-1 w-64 overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg ltr:right-0 rtl:left-0 dark:border-zink-500 dark:bg-zink-600">
    <a href="<?= e(lurl('/dashboard/post/'.$menuPostId.'/edit')) ?>" role="menuitem"
       class="<?= $menuItem ?> <?= $menuActive === $menuDefault ? $menuItemOn : $menuItemOff ?>"
       <?= $menuActive === $menuDefault ? 'aria-current="page"' : '' ?>>
      <span class="w-7 shrink-0 text-xs font-semibold"><?= e(strtoupper($menuDefault)) ?></span>
      <span class="grow">{{ t('post.translations.original') }}</span>
    </a>
    <?php foreach (($availableLocales ?? []) as $menuLocale) { ?>
      <?php if ($menuLocale === $menuDefault) {
          continue;
      } ?>
    <a href="<?= e(lurl('/dashboard/post/'.$menuPostId.'/translations/'.$menuLocale)) ?>" role="menuitem"
       class="<?= $menuItem ?> <?= $menuActive === $menuLocale ? $menuItemOn : $menuItemOff ?>"
       <?= $menuActive === $menuLocale ? 'aria-current="page"' : '' ?>>
      <span class="w-7 shrink-0 text-xs font-semibold"><?= e(strtoupper($menuLocale)) ?></span>
      <?php if (isset($menuDone[$menuLocale])) { ?>
      <span class="grow">Translated</span>
      <span class="size-1.5 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
      <?php } else { ?>
      <span class="grow text-slate-400 dark:text-zink-300">{{ t('post.translations.notTranslated') }}</span>
      <?php } ?>
    </a>
    <?php } ?>
    <p class="mt-1 border-t border-slate-200 px-3 py-2 text-[11px] leading-relaxed text-slate-500 dark:border-zink-500 dark:text-zink-300">{{ t('post.translations.tabsHint') }}</p>
  </div>
</details>
<?php } ?>
