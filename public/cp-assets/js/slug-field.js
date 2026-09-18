/**
 * Keeps a slug field in step with the field it is built from, and tells the
 * writer at once whether the address is usable.
 *
 * SlugField.bind({ source: 'name', target: 'slug', min: 2, max: 50 })
 *
 * slugify() here and slugify() in src/Framework/Core/helpers.php must agree,
 * and valid() must match Validator::validateSlug(), or a slug that looks fine
 * while typing would still be refused on save.
 */
(function () {
    var PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

    var UNDECOMPOSABLE = { 'ß': 'ss', 'ẞ': 'ss', 'æ': 'ae', 'Æ': 'ae', 'œ': 'oe', 'Œ': 'oe', 'ø': 'o', 'Ø': 'o', 'đ': 'd', 'Đ': 'd', 'ł': 'l', 'Ł': 'l', 'þ': 'th', 'Þ': 'th' };

    function slugify(value) {
        return value
            .trim()
            .replace(/[ßẞæÆœŒøØđĐłŁþÞ]/g, function (ch) { return UNDECOMPOSABLE[ch]; })
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function problemWith(slug, sourceValue, opts) {
        if (slug === '') {
            return sourceValue.trim() !== ''
                ? 'This name has no letters or numbers to build an address from. Type an address here.'
                : 'An address is required.';
        }
        if (!PATTERN.test(slug)) {
            return 'Use lowercase letters, numbers and single hyphens, with no hyphen at the start or end.';
        }
        if (slug.length < opts.min) {
            return 'The address needs at least ' + opts.min + ' characters.';
        }
        if (slug.length > opts.max) {
            return 'The address can be at most ' + opts.max + ' characters.';
        }
        return null;
    }

    function bind(opts) {
        var source = document.getElementById(opts.source);
        var target = document.getElementById(opts.target);
        if (!source || !target) return;

        opts.min = opts.min || 2;
        opts.max = opts.max || 100;

        var feedback = document.createElement('p');
        feedback.id = opts.target + '_feedback';
        feedback.className = 'mt-1 text-sm';
        feedback.setAttribute('aria-live', 'polite');
        feedback.hidden = true;
        var anchor = target.parentNode.classList.contains('flex') ? target.parentNode : target;
        anchor.parentNode.insertBefore(feedback, anchor.nextSibling);

        // A slug the writer typed is theirs, so stop overwriting it. Emptying the
        // field hands it back: the next edit to the source refills it.
        var typedByHand = target.value !== '' && target.value !== slugify(source.value);

        function show() {
            // The server message described the value that was submitted, not this one.
            var serverError = document.getElementById(opts.target + '_error');
            if (serverError) serverError.remove();

            var problem = problemWith(target.value, source.value, opts);
            var bad = problem !== null;

            target.classList.toggle('!border-red-500', bad);
            target.classList.toggle('!border-green-500', !bad);
            target.setAttribute('aria-invalid', bad ? 'true' : 'false');
            target.setAttribute('aria-describedby', feedback.id);
            target.setCustomValidity(bad ? problem : '');

            feedback.hidden = false;
            feedback.textContent = bad ? problem : 'This address is valid.';
            feedback.className = 'mt-1 text-sm ' + (bad ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400');
        }

        source.addEventListener('input', function () {
            if (typedByHand) return;
            target.value = slugify(source.value);
            show();
        });

        target.addEventListener('input', function () {
            typedByHand = target.value !== '';
            show();
        });

        target.addEventListener('blur', function () {
            if (typedByHand) show();
        });
    }

    window.SlugField = { bind: bind, slugify: slugify };
})();
