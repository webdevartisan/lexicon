<?php
/**
 * Row actions menu: one vertical kebab button per table row that opens a menu of
 * that row's actions. Behaviour lives in /cp-assets/js/row-actions.js, which the
 * back layout loads.
 *
 * Attributes:
 * - title: what the row is, read out as "Actions for {title}"
 * - items: list of actions, each with
 *     label   visible text
 *     icon    lucide icon name
 *     href    link target, for actions that open a page
 *     newTab  open the href in a new tab, announced to screen readers
 *     post    form action, for actions that change something (sent with CSRF)
 *     confirm question asked before a post action is sent
 *     danger  destructive styling, grouped after a divider at the end
 *     can     false leaves the item out, so the menu only offers what the viewer may do
 */
$title = (string) ($title ?? '');
$items = array_values(array_filter($items ?? [], static fn (array $item): bool => ($item['can'] ?? true) !== false));

$safe = array_values(array_filter($items, static fn (array $item): bool => empty($item['danger'])));
$destructive = array_values(array_filter($items, static fn (array $item): bool => !empty($item['danger'])));

$menuId = 'row-actions-'.bin2hex(random_bytes(4));
$itemClass = 'flex w-full items-center gap-2 px-3 py-2 text-left text-sm transition-colors focus:outline-none';
$safeClass = $itemClass.' text-slate-600 hover:bg-slate-50 focus:bg-slate-100 dark:text-zink-200 dark:hover:bg-zink-600 dark:focus:bg-zink-600';
$dangerClass = $itemClass.' text-red-600 hover:bg-red-50 focus:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10 dark:focus:bg-red-500/10';

$renderItem = static function (array $item, string $class): string {
    $icon = '<i data-lucide="'.e((string) ($item['icon'] ?? 'circle')).'" class="size-4 shrink-0" aria-hidden="true"></i>';
    $label = '<span>'.e((string) ($item['label'] ?? '')).'</span>';

    if (!empty($item['post'])) {
        $confirm = empty($item['confirm']) ? '' : ' data-confirm="'.e((string) $item['confirm']).'"';

        return '<form method="POST" action="'.e((string) $item['post']).'" class="m-0" role="none">'
            .csrf_field()
            .'<button type="submit" role="menuitem" tabindex="-1"'.$confirm.' class="'.$class.'">'.$icon.$label.'</button>'
            .'</form>';
    }

    $newTab = empty($item['newTab']) ? '' : ' target="_blank" rel="noopener"';
    $newTabNote = empty($item['newTab']) ? '' : '<span class="sr-only"> (opens in a new tab)</span>';

    return '<a href="'.e((string) ($item['href'] ?? '#')).'" role="menuitem" tabindex="-1"'.$newTab.' class="'.$class.'">'.$icon.$label.$newTabNote.'</a>';
};
?>
<?php if ($items !== []) { ?>
<div class="inline-flex" data-row-actions>
  <button type="button"
          data-row-actions-toggle
          aria-haspopup="menu"
          aria-expanded="false"
          aria-controls="<?= e($menuId) ?>"
          aria-label="<?= e('Actions for '.$title) ?>"
          class="p-2 text-slate-500 rounded-md transition-colors hover:text-custom-500 hover:bg-custom-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-custom-500 dark:text-zink-300 dark:hover:bg-custom-500/10">
    <i data-lucide="more-vertical" class="size-4" aria-hidden="true"></i>
  </button>
  <div id="<?= e($menuId) ?>"
       role="menu"
       aria-label="<?= e('Actions for '.$title) ?>"
       hidden
       data-row-actions-menu
       class="z-[1050] w-48 overflow-hidden rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-zink-500 dark:bg-zink-700">
    <?php foreach ($safe as $item) { ?>
      <?= $renderItem($item, $safeClass) ?>
    <?php } ?>
    <?php if ($safe !== [] && $destructive !== []) { ?>
      <div role="separator" class="my-1 border-t border-slate-200 dark:border-zink-500"></div>
    <?php } ?>
    <?php foreach ($destructive as $item) { ?>
      <?= $renderItem($item, $dangerClass) ?>
    <?php } ?>
  </div>
</div>
<?php } ?>
