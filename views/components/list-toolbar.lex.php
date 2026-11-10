<?php
/**
 * The row above a dashboard list: search, a Filters popover, the cards or list switch
 * and the sort menu. The Posts and Blogs pages both use it, so the two always behave alike.
 * It is one GET form, so every control is a plain query parameter.
 *
 * Attributes:
 * toolbar: one array describing the row
 *   action: where the form submits, already localized
 *   q, searchPlaceholder: the search box
 *   filters: list of label, name, options and selected, one per select in the popover
 *   filtersLabel, applyLabel, clearLabel: button texts
 *   clearHref: where "clear" goes
 *   view: current (cards or list), label, and choices keyed by view with icon, label and href
 *   sort: options, selected, and keep (sort and dir to carry along when the menu has no name for the order)
 */
$toolbar = $toolbar ?? [];
$filters = $toolbar['filters'] ?? [];
$view = $toolbar['view'] ?? ['current' => 'cards', 'label' => '', 'choices' => []];
$sort = $toolbar['sort'] ?? ['options' => [], 'selected' => '', 'keep' => null];
$q = (string) ($toolbar['q'] ?? '');
$searchPlaceholder = (string) ($toolbar['searchPlaceholder'] ?? '');
$filtersLabel = (string) ($toolbar['filtersLabel'] ?? '');
$applyLabel = (string) ($toolbar['applyLabel'] ?? '');
$clearLabel = (string) ($toolbar['clearLabel'] ?? '');
$clearHref = (string) ($toolbar['clearHref'] ?? '');
$sortOptions = $sort['options'];
$sortSelected = (string) $sort['selected'];

$activeFilters = 0;
foreach ($filters as $filter) {
    if ((string) $filter['selected'] !== '') {
        $activeFilters++;
    }
}
?>
<form method="GET" action="<?= e((string) ($toolbar['action'] ?? '')) ?>" class="grid grid-cols-2 gap-3 mb-3 md:flex md:flex-wrap md:items-center">
    <div class="col-span-2 md:col-span-1 md:grow md:max-w-sm">
        {% cmp="input" type="search" name="q" value="{$q}" placeholder="{$searchPlaceholder}" %}
    </div>
    <details class="relative col-span-2 md:col-span-1" data-popover>
        <summary class="inline-flex w-full md:w-auto justify-center items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border cursor-pointer select-none list-none bg-white border-slate-200 text-slate-700 hover:bg-slate-50 dark:bg-zink-700 dark:border-zink-500 dark:text-zink-100 dark:hover:bg-zink-600">
            {% cache 'lucide:sliders-horizontal' ttl=31536000 %}<i data-lucide="sliders-horizontal" class="size-4"></i>{% endcache %} <?= e($filtersLabel) ?>
            <?php if ($activeFilters > 0) { ?><span class="pill bg-slate-900 text-white dark:bg-zink-100 dark:text-zink-800"><?= $activeFilters ?></span><?php } ?>
        </summary>
        <div class="absolute z-20 mt-2 card shadow-lg ltr:left-0 rtl:right-0 w-[min(22rem,calc(100vw-2rem))]">
            <div class="card-body grid grid-cols-1 gap-3">
                <?php foreach ($filters as $filter) {
                    $filterLabel = (string) $filter['label'];
                    $filterName = (string) $filter['name'];
                    $filterOptions = $filter['options'];
                    $filterSelected = (string) $filter['selected'];
                    ?>
                {% cmp="select" label="{$filterLabel}" name="{$filterName}" options="{$filterOptions}" selectedKey="{$filterSelected}" %}
                <?php } ?>
                <div class="flex gap-2">
                    {% cmp="btn" type="submit" variant="blue" icon="filter" label="{$applyLabel}" %}
                    <?php if ($activeFilters > 0) { ?>
                    {% cmp="btn" href="{$clearHref}" variant="slate" icon="x" label="{$clearLabel}" %}
                    <?php } ?>
                </div>
            </div>
        </div>
    </details>
    <?php if ($view['current'] === 'list') { ?><input type="hidden" name="view" value="list"><?php } ?>
    <div class="col-span-2 flex items-center justify-between gap-3 md:contents">
        <div class="inline-flex rounded-lg border border-slate-200 p-0.5 md:ml-auto dark:border-zink-500" role="group" aria-label="<?= e((string) $view['label']) ?>">
            <?php foreach ($view['choices'] as $viewKey => $choice) {
                $isCurrent = $view['current'] === $viewKey;
                $viewIcon = (string) $choice['icon'];
                ?>
            <a href="<?= e((string) $choice['href']) ?>"
               aria-pressed="<?= $isCurrent ? 'true' : 'false' ?>"
               title="<?= e((string) $choice['label']) ?>"
               class="inline-flex items-center justify-center size-8 rounded-md transition-colors <?= $isCurrent
                   ? 'bg-slate-900 text-white dark:bg-zink-100 dark:text-zink-800'
                   : 'text-slate-500 hover:bg-slate-100 dark:text-zink-300 dark:hover:bg-zink-600' ?>">
                {% cache 'lucide:view:' . $viewIcon ttl=31536000 %}<i data-lucide="<?= e($viewIcon) ?>" class="size-4" aria-hidden="true"></i>{% endcache %}
                <span class="sr-only"><?= e((string) $choice['label']) ?></span>
            </a>
            <?php } ?>
        </div>
        {% cmp="select" name="sort" options="{$sortOptions}" selectedKey="{$sortSelected}" onchange="this.form.submit()" %}
    </div>
    <?php if (!empty($sort['keep'])) { ?>
    <input type="hidden" name="sort" value="<?= e((string) $sort['keep']['sort']) ?>">
    <input type="hidden" name="dir" value="<?= e((string) $sort['keep']['dir']) ?>">
    <?php } ?>
</form>
