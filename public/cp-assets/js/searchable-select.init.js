// Turn every .searchable-select into a Choices.js dropdown with search.
// Load after choices.min.js.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('select.searchable-select').forEach((el) => {
        new Choices(el, {
            shouldSort: true,
            allowHTML: false,
            searchEnabled: true,
            itemSelectText: '',
            placeholder: true,
            placeholderValue: el.dataset.placeholder || 'Choose...',
        });
    });
});
