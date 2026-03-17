<?php
/**
 * Pagination Controls
 *
 * Reusable pagination component that preserves all query parameters
 * and updates only the specific page parameter for the current tab.
 *
 * Required variables:
 *
 * @var array $pagination - pagination metadata from model
 * @var string $pageParam - the query parameter name for this pagination (e.g., 'publishedPage', 'draftPage')
 * @var string $query - optional search query to preserve
 */
$pagination = $pagination ?? [];
$pageParam = $pageParam ?? 'page';
$query = $query ?? '';
?>
{% set paginationLabel = t('components.paginator.ariaLabels.pagination') %}
{% set previousPageLabel = t('components.paginator.ariaLabels.previousPage') %}
{% set previousUnavailableLabel = t('components.paginator.ariaLabels.previousUnavailable') %}
{% set nextPageLabel = t('components.paginator.ariaLabels.nextPage') %}
{% set nextUnavailableLabel = t('components.paginator.ariaLabels.nextUnavailable') %}
{% set currentPageLabel = t('components.paginator.ariaLabels.currentPage') %}
{% set goToPageLabel = t('components.paginator.ariaLabels.goToPage') %}
{% set showingText = t('components.paginator.info.showing') %}
{% set toText = t('components.paginator.info.to') %}
{% set ofText = t('components.paginator.info.of') %}
{% set postSingular = t('components.paginator.info.postSingular') %}
{% set postPlural = t('components.paginator.info.postPlural') %}

<?php if (!empty($pagination) && $pagination['total_pages'] > 1) { ?>
    <nav class="pagination" aria-label="{{ paginationLabel }}">
        <?php
        /**
         * Build pagination URL with preserved parameters.
         *
         * centralize URL building to follow DRY principle and ensure
         * consistent parameter handling across all pagination links.
         *
         * @param  int  $page  Page number to link to
         * @return string Complete URL with query string
         */
        $buildPaginationUrl = function (int $page) use ($pageParam, $query): string {
            // preserve all existing query parameters
            $params = $_GET ?? [];

            // update the specific page parameter for this tab
            $params[$pageParam] = $page;

            // add search query if exists
            if (!empty($query)) {
                $params['query'] = $query;
            }

            return '/dashboard?'.http_build_query($params);
        };

    /**
     * Calculate the range of page numbers to display.
     *
     * show up to 5 page numbers with the current page centered when possible,
     * adjusting the range when near the beginning or end of the page list.
     */
    $startPage = max(1, $pagination['current_page'] - 2);
    $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);

    // adjust range if we're near the beginning
    if ($pagination['current_page'] <= 3) {
        $endPage = min(5, $pagination['total_pages']);
    }

    // adjust range if we're near the end
    if ($pagination['current_page'] > $pagination['total_pages'] - 2) {
        $startPage = max(1, $pagination['total_pages'] - 4);
    }
    ?>

        <ul class="flex flex-wrap items-center gap-2 justify-center">
            <!-- Previous Button -->
            <li>
                <?php if ($pagination['has_previous']) { ?>
                    <a href="<?= e($buildPaginationUrl($pagination['current_page'] - 1)) ?>"
                       class="inline-flex items-center justify-center bg-white size-8 dark:bg-zink-700 transition-all duration-150 ease-linear border rounded-full border-slate-200 dark:border-zink-500 text-slate-500 dark:text-zink-200 hover:text-custom-500 dark:hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10 focus:bg-custom-50 dark:focus:bg-custom-500/10 focus:text-custom-500 dark:focus:text-custom-500"
                       aria-label="<?= e($previousPageLabel) ?>">
                        {% cache 'lucide:chevron-left' ttl=3600 %}<i class="size-4 rtl:rotate-180" data-lucide="chevron-left"></i>{% endcache %}
                    </a>
                <?php } else { ?>
                    <!-- disable the previous button on first page -->
                    <span class="inline-flex items-center justify-center bg-white size-8 dark:bg-zink-700 transition-all duration-150 ease-linear border rounded-full border-slate-200 dark:border-zink-500 text-slate-400 dark:text-zink-300 cursor-auto disabled"
                          aria-disabled="true"
                          aria-label="<?= e($previousUnavailableLabel) ?>">
                        {% cache 'lucide:chevron-left' ttl=3600 %}<i class="size-4 rtl:rotate-180" data-lucide="chevron-left"></i>{% endcache %}
                    </span>
                <?php } ?>
            </li>

            <!-- Page Numbers -->
            <?php for ($i = $startPage; $i <= $endPage; $i++) { ?>
                <li>
                    <?php if ($i === $pagination['current_page']) { ?>
                        <!-- apply the "active" class to the current page for visual distinction -->
                        <span class="inline-flex items-center justify-center bg-white size-8 dark:bg-zink-700 transition-all duration-150 ease-linear border rounded-full border-slate-200 dark:border-zink-500 text-slate-500 dark:text-zink-200 hover:text-custom-500 dark:hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10 focus:bg-custom-50 dark:focus:bg-custom-500/10 focus:text-custom-500 dark:focus:text-custom-500 [&.active]:text-custom-50 dark:[&.active]:text-custom-50 [&.active]:bg-custom-500 dark:[&.active]:bg-custom-500 [&.active]:border-custom-500 dark:[&.active]:border-custom-500 active"
                              aria-current="page"
                              aria-label="<?= e($currentPageLabel) ?> <?= $i ?>">
                            <?= $i ?>
                        </span>
                    <?php } else { ?>
                        <a href="<?= e($buildPaginationUrl($i)) ?>"
                           class="inline-flex items-center justify-center bg-white size-8 dark:bg-zink-700 transition-all duration-150 ease-linear border rounded-full border-slate-200 dark:border-zink-500 text-slate-500 dark:text-zink-200 hover:text-custom-500 dark:hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10 focus:bg-custom-50 dark:focus:bg-custom-500/10 focus:text-custom-500 dark:focus:text-custom-500 [&.active]:text-custom-50 dark:[&.active]:text-custom-50 [&.active]:bg-custom-500 dark:[&.active]:bg-custom-500 [&.active]:border-custom-500 dark:[&.active]:border-custom-500"
                           aria-label="<?= e($goToPageLabel) ?> <?= $i ?>">
                            <?= $i ?>
                        </a>
                    <?php } ?>
                </li>
            <?php } ?>

            <!-- Next Button -->
            <li>
                <?php if ($pagination['has_next']) { ?>
                    <a href="<?= e($buildPaginationUrl($pagination['current_page'] + 1)) ?>"
                       class="inline-flex items-center justify-center bg-white size-8 dark:bg-zink-700 transition-all duration-150 ease-linear border rounded-full border-slate-200 dark:border-zink-500 text-slate-500 dark:text-zink-200 hover:text-custom-500 dark:hover:text-custom-500 hover:bg-custom-50 dark:hover:bg-custom-500/10 focus:bg-custom-50 dark:focus:bg-custom-500/10 focus:text-custom-500 dark:focus:text-custom-500"
                       aria-label="<?= e($nextPageLabel) ?>">
                        {% cache 'lucide:chevron-right' ttl=3600 %}<i class="size-4 rtl:rotate-180" data-lucide="chevron-right"></i>{% endcache %}
                    </a>
                <?php } else { ?>
                    <!-- disable the next button on last page -->
                    <span class="inline-flex items-center justify-center bg-white size-8 dark:bg-zink-700 transition-all duration-150 ease-linear border rounded-full border-slate-200 dark:border-zink-500 text-slate-400 dark:text-zink-300 cursor-auto disabled"
                          aria-disabled="true"
                          aria-label="<?= e($nextUnavailableLabel) ?>">
                        {% cache 'lucide:chevron-right' ttl=3600 %}<i class="size-4 rtl:rotate-180" data-lucide="chevron-right"></i>{% endcache %}
                    </span>
                <?php } ?>
            </li>
        </ul>

        <!-- Pagination Info -->
        <div class="flex justify-center mt-3 text-sm text-slate-500 dark:text-zink-200">
            <?= e($showingText) ?>
            <span class="font-semibold mx-1">
                <?= (($pagination['current_page'] - 1) * $pagination['per_page']) + 1 ?>
            </span>
            <?= e($toText) ?>
            <span class="font-semibold mx-1">
                <?= min($pagination['current_page'] * $pagination['per_page'], $pagination['total_records']) ?>
            </span>
            <?= e($ofText) ?>
            <span class="font-semibold mx-1">
                <?= $pagination['total_records'] ?>
            </span>
            <?= e($pagination['total_records'] === 1 ? $postSingular : $postPlural) ?>
        </div>
    </nav>
<?php } ?>
