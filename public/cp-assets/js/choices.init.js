document.addEventListener('DOMContentLoaded', () => {
const selectEl = document.querySelector('select[name="timezone"]');
if (!selectEl) {
    console.error('No timezone select');
    return;
}

// initialize Choices once and keep the instance reference.
const tzChoices = new Choices(selectEl, {
    shouldSort: true,
    allowHTML: true,
    searchEnabled: true,
    placeholder: true,
    placeholderValue: 'Select timezone',
});

});