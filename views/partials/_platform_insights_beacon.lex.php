<?php if (isset($current_path) && \App\Services\Analytics\PlatformPages::counts((string) $current_path) && app(\App\Services\Analytics\AnalyticsSettings::class)->enabled()) {
    // Discover tells the beacon how many results a search found, so searches that found nothing can be listed.
    $searchResults = ($searchQuery ?? '') !== '' && isset($pagination['total']) ? (int) $pagination['total'] : null; ?>
<script src="/assets/js/insights.js"<?= $searchResults !== null ? ' data-search-results="'.$searchResults.'"' : '' ?> defer></script>
<?php } ?>
