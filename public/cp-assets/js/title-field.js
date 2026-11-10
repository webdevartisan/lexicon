/**
 * A title box that wraps and grows with long titles instead of cutting them
 * off, which a one-line field does on a phone. It still holds a single line of
 * text: Enter does nothing and pasted line breaks become spaces.
 */
(function () {
    'use strict';

    function fit(box) {
        box.style.height = 'auto';
        box.style.height = box.scrollHeight + 'px';
    }

    document.querySelectorAll('textarea[data-title-field]').forEach(function (box) {
        box.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') event.preventDefault();
        });
        box.addEventListener('input', function () {
            if (/[\r\n]/.test(box.value)) box.value = box.value.replace(/\s*[\r\n]+\s*/g, ' ');
            fit(box);
        });
        window.addEventListener('resize', function () { fit(box); });
        fit(box);
    });
})();
