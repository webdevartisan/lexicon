<?php
/**
 * A table header that sorts the table when clicked.
 *
 * Renders a real link, so sorting works with JavaScript switched off. table-sort.js
 * upgrades it in place: it intercepts the click, fetches the same URL, and swaps the
 * table region instead of reloading the page.
 *
 * {% cmp="sortable-th" sort="{$sort}" base="{$basePath}" sortKey="name" label="Name" %}
 */
$sort = $sort ?? null;
$sortKey = $sortKey ?? '';
$label = $label ?? '';
$base = $base ?? '';
$align = $align ?? 'left';

$alignClass = $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : '');
$thClass = 'px-3.5 py-2.5 font-semibold '.$alignClass;

// No sort object, or a column the caller never whitelisted: fall back to a plain
// header rather than a link that would silently do nothing.
$sortable = $sort instanceof \App\ValueObjects\TableSort && $sortKey !== '' && $sort->has($sortKey);

if ($sortable) {
    $isOn = $sort->isOn($sortKey);
    $nextDir = $sort->nextDirection($sortKey);
    $icon = $isOn
        ? ($sort->direction() === 'asc' ? 'arrow-up' : 'arrow-down')
        : 'chevrons-up-down';

    // Screen readers get the state; the arrow alone only works if you can see it.
    $ariaSort = $isOn ? ($sort->direction() === 'asc' ? 'ascending' : 'descending') : 'none';
    $justify = $align === 'right' ? 'justify-end' : ($align === 'center' ? 'justify-center' : '');
}
?>
<?php if (!$sortable) { ?>
<th class="<?= $thClass ?>"><?= e($label) ?></th>
<?php } else { ?>
<th class="<?= $thClass ?>" aria-sort="<?= e($ariaSort) ?>">
    <a href="<?= e($sort->urlFor($base, $sortKey)) ?>"
       data-sort-link
       title="Sort by <?= e($label) ?> (<?= e($nextDir === 'asc' ? 'ascending' : 'descending') ?>)"
       class="inline-flex items-center gap-1 <?= $justify ?> transition-colors hover:text-custom-500 <?= $isOn ? 'text-custom-500' : '' ?>">
        <span><?= e($label) ?></span>
        {% cache 'lucide:sort:' . $icon ttl=31536000 %}<i data-lucide="<?= e($icon) ?>" class="size-3.5 <?= $isOn ? '' : 'opacity-40' ?>" aria-hidden="true"></i>{% endcache %}
    </a>
</th>
<?php } ?>
