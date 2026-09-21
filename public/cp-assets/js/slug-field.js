/**
 * Keeps a slug field in step with the field it is built from, and tells the
 * writer whether the address is usable while they type.
 *
 * SlugField.bind({ source: 'name', target: 'slug', min: 2, max: 50, checkUrl: '/dashboard/slug-check?type=blog' })
 *
 * slugify() here and slugify() in src/Framework/Core/helpers.php must agree,
 * and the format check must match Validator::validateSlug(), or a slug that looks
 * fine while typing would still be refused on save.
 */
(function () {
    var PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
    var CHECK_DELAY = 600;

    var UNDECOMPOSABLE = { 'ß': 'ss', 'ẞ': 'ss', 'æ': 'ae', 'Æ': 'ae', 'œ': 'oe', 'Œ': 'oe', 'ø': 'o', 'Ø': 'o', 'đ': 'd', 'Đ': 'd', 'ł': 'l', 'Ł': 'l', 'þ': 'th', 'Þ': 'th' };

    var TONES = {
        error: { border: '!border-red-500', text: 'text-red-600 dark:text-red-400' },
        ok: { border: '!border-green-500', text: 'text-green-600 dark:text-green-400' },
        notice: { border: '!border-amber-500', text: 'text-amber-600 dark:text-amber-400' },
        pending: { border: '', text: 'text-slate-500 dark:text-zink-300' },
    };

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

    function formatProblem(slug, sourceValue, opts) {
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
        feedback.setAttribute('aria-live', 'polite');
        feedback.hidden = true;
        var anchor = target.parentNode.classList.contains('flex') ? target.parentNode : target;
        anchor.parentNode.insertBefore(feedback, anchor.nextSibling);

        // A slug the writer typed is theirs, so stop overwriting it. Emptying the
        // field hands it back: the next edit to the source refills it.
        var typedByHand = target.value !== '' && target.value !== slugify(source.value);
        var timer = null;
        var latest = 0;

        function paint(tone, message, blocks) {
            Object.keys(TONES).forEach(function (name) {
                if (TONES[name].border) target.classList.remove(TONES[name].border);
            });
            if (TONES[tone].border) target.classList.add(TONES[tone].border);

            target.setAttribute('aria-invalid', tone === 'error' ? 'true' : 'false');
            target.setAttribute('aria-describedby', feedback.id);
            target.setCustomValidity(blocks ? message : '');

            feedback.hidden = false;
            feedback.textContent = message;
            feedback.className = 'mt-1 text-sm ' + TONES[tone].text;
        }

        function askServer(slug) {
            var request = ++latest;
            var url = opts.checkUrl + (opts.checkUrl.indexOf('?') === -1 ? '?' : '&') + 'slug=' + encodeURIComponent(slug);

            fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                .then(function (r) {
                    return r.json().then(function (body) { return { status: r.status, body: body }; });
                })
                .then(function (res) {
                    if (request !== latest || target.value !== slug) return;

                    if (res.status !== 200) {
                        throw new Error(res.body && res.body.error ? res.body.error : 'status ' + res.status);
                    }
                    if (res.body.available) {
                        paint('ok', 'This address is available.', false);
                    } else if (res.body.saved_as) {
                        paint('notice', 'This address is already in use, so it will be saved as ' + res.body.saved_as + '.', false);
                    } else {
                        paint('error', res.body.message || 'This address cannot be used.', true);
                    }
                })
                .catch(function (err) {
                    if (request !== latest) return;
                    console.error('Slug check failed:', err);
                    paint('pending', 'Could not check the address right now. It will be checked when you save.', false);
                });
        }

        function show() {
            // The server message described the value that was submitted, not this one.
            var serverError = document.getElementById(opts.target + '_error');
            if (serverError) serverError.remove();

            clearTimeout(timer);
            latest++;

            var problem = formatProblem(target.value, source.value, opts);
            if (problem !== null) {
                paint('error', problem, true);
                return;
            }

            if (!opts.checkUrl) {
                paint('ok', 'This address is valid.', false);
                return;
            }

            paint('pending', 'Checking the address…', false);
            var slug = target.value;
            timer = setTimeout(function () { askServer(slug); }, CHECK_DELAY);
        }

        function lock(savedSlug) {
            clearTimeout(timer);
            latest++;
            target.value = savedSlug;
            target.readOnly = true;
            paint('ok', 'Saved as ' + savedSlug + '. The address is fixed once the draft exists.', false);
        }

        source.addEventListener('input', function () {
            if (typedByHand || target.readOnly) return;
            target.value = slugify(source.value);
            show();
        });

        target.addEventListener('input', function () {
            typedByHand = target.value !== '';
            show();
        });

        target.addEventListener('slug:saved', function (e) {
            if (e.detail && e.detail.slug) lock(e.detail.slug);
        });

        if (target.value !== '') show();
    }

    window.SlugField = { bind: bind, slugify: slugify };
})();
