<?php
/*
 * Search box and category select filtering rows marked data-search and
 * data-category inside [data-filter-rows]. Works on the page as rendered, so
 * there is no round trip; ?q= and ?category= prefill it for shareable links.
 */
?>
<script nonce="<?= csp_nonce() ?>">
(function () {
    var search = document.querySelector('input[type="search"]');
    var category = document.querySelector('select[id$="-category"]');
    var empty = document.querySelector('[data-filter-empty]');

    function apply() {
        var term = search ? search.value.trim().toLowerCase() : '';
        var cat = category ? category.value : '';
        var shown = 0;

        document.querySelectorAll('[data-filter-rows] [data-search]').forEach(function (row) {
            var match = (term === '' || row.getAttribute('data-search').indexOf(term) !== -1)
                && (cat === '' || row.getAttribute('data-category') === cat);
            row.hidden = !match;
            shown += match ? 1 : 0;
        });

        // Group headings hide when every row under them is filtered out.
        document.querySelectorAll('[data-filter-group]').forEach(function (group) {
            group.hidden = group.querySelector('[data-search]:not([hidden])') === null;
        });

        if (empty) {
            empty.hidden = shown !== 0;
        }
    }

    if (search) { search.addEventListener('input', apply); }
    if (category) { category.addEventListener('change', apply); }
    apply();
})();
</script>
