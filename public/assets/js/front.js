/*
 * Public front-end behaviour: the sticky bar's paper/cover state, the mobile
 * drawer, scroll reveals, and the password show/hide control. Replaces the
 * Editorial theme bundle, so no jQuery is loaded on public pages.
 *
 * Every block no-ops when its markup is absent, which is how the auth layout
 * can load the same file without carrying a masthead or a drawer.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* --------------------------------------------------------------------
     * Password show/hide
     * Labels come from data attributes so the control stays translated.
     * ----------------------------------------------------------------- */
    document.querySelectorAll('[data-password-toggle]').forEach(function (toggle) {
        var field = document.getElementById(toggle.getAttribute('data-password-toggle'));

        if (!field) {
            return;
        }

        toggle.addEventListener('click', function () {
            var reveal = field.type === 'password';

            field.type = reveal ? 'text' : 'password';
            toggle.textContent = toggle.getAttribute(reveal ? 'data-label-hide' : 'data-label-show');
            toggle.setAttribute('aria-label', toggle.getAttribute(reveal ? 'data-aria-hide' : 'data-aria-show'));
            toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');

            // Losing the caret on every toggle is the usual complaint about
            // these controls.
            field.focus();
        });
    });

    /* --------------------------------------------------------------------
     * Password strength meter
     *
     * Deliberately a heuristic, not a checker. The server rule is
     * `password:basic` — six characters, nothing else — so this never blocks
     * anything and never claims a requirement the backend does not enforce.
     * Length dominates the score because that is what actually costs an
     * attacker time; class variety is worth far less than people assume.
     * ----------------------------------------------------------------- */
    var WEAK_RUNS = [
        'abcdefghijklmnopqrstuvwxyz',
        '01234567890',
        'qwertyuiop',
        'asdfghjkl',
        'zxcvbnm'
    ];

    var WEAK_WORDS = [
        'password', 'passwort', 'welcome', 'letmein', 'admin', 'iloveyou',
        'monkey', 'dragon', 'sunshine', 'princess', 'football', 'baseball',
        'qwerty', 'abc123', 'master', 'login', 'lexicon'
    ];

    // A run of four or more keyboard/alphabet neighbours, forwards or back.
    function hasWeakRun(lower) {
        for (var i = 0; i < WEAK_RUNS.length; i++) {
            var row = WEAK_RUNS[i];
            var back = row.split('').reverse().join('');

            for (var j = 0; j + 4 <= row.length; j++) {
                if (lower.indexOf(row.substr(j, 4)) !== -1) {
                    return true;
                }
                if (lower.indexOf(back.substr(j, 4)) !== -1) {
                    return true;
                }
            }
        }

        return false;
    }

    function scorePassword(value, hint) {
        var len = value.length;

        if (len === 0) {
            return -1;
        }

        if (len < 6) {
            return 0;
        }

        var lower = value.toLowerCase();
        var score = 1;

        if (len >= 10) { score++; }
        if (len >= 14) { score++; }
        if (len >= 18) { score++; }

        var classes = 0;
        if (/[a-z]/.test(value)) { classes++; }
        if (/[A-Z]/.test(value)) { classes++; }
        if (/[0-9]/.test(value)) { classes++; }
        if (/[^A-Za-z0-9]/.test(value)) { classes++; }
        if (classes >= 3 && len >= 8) { score++; }

        // Anything a wordlist attack tries first cannot rate above weak,
        // however long it is.
        if (/^(.)\1+$/.test(value) || hasWeakRun(lower)) { score = 1; }

        for (var i = 0; i < WEAK_WORDS.length; i++) {
            if (lower.indexOf(WEAK_WORDS[i]) !== -1) { score = 1; break; }
        }

        // Reusing the email is the single most common mistake here.
        if (hint && hint.length >= 3 && lower.indexOf(hint) !== -1) { score = 1; }

        if (/^\d+$/.test(value) && score > 2) { score = 2; }

        return Math.max(1, Math.min(4, score));
    }

    document.querySelectorAll('[data-password-meter]').forEach(function (meter) {
        var field = document.getElementById(meter.getAttribute('data-password-meter'));
        var label = meter.querySelector('[data-meter-label]');
        var hintField = document.getElementById(meter.getAttribute('data-hint-from') || '');

        if (!field || !label) {
            return;
        }

        var levels = (meter.getAttribute('data-levels') || '').split('|');
        var lastLevel = null;

        // Built once and only ever updated through textContent, so nothing
        // typed into the field can reach the DOM as markup.
        var levelText = document.createElement('span');
        levelText.className = 'lx-meter__level';
        label.appendChild(levelText);

        var render = function () {
            var hint = hintField ? (hintField.value.split('@')[0] || '').toLowerCase() : '';
            var level = scorePassword(field.value, hint);

            if (level < 0) {
                meter.hidden = true;
                meter.removeAttribute('data-level');
                lastLevel = null;

                return;
            }

            meter.hidden = false;
            meter.setAttribute('data-level', String(level));

            // Only write on a change, so the live region announces the new
            // rating instead of chattering on every keystroke.
            if (level !== lastLevel) {
                levelText.textContent = levels[level] || '';
                lastLevel = level;
            }
        };

        field.addEventListener('input', render);

        if (hintField) {
            hintField.addEventListener('input', function () {
                if (field.value) { render(); }
            });
        }

        // A restored value after a failed submit should already be rated.
        if (field.value) { render(); }
    });

    /* --------------------------------------------------------------------
     * Sticky bar
     * On the home page the bar starts transparent over the cover and turns
     * solid once the reader has left it, so the wordmark never sits on the
     * seam between the two backgrounds.
     * ----------------------------------------------------------------- */
    var nav = document.querySelector('.lx-nav');

    if (nav) {
        var threshold = 24;
        var ticking = false;

        var syncNav = function () {
            nav.classList.toggle('is-stuck', window.scrollY > threshold);
            ticking = false;
        };

        window.addEventListener(
            'scroll',
            function () {
                if (!ticking) {
                    window.requestAnimationFrame(syncNav);
                    ticking = true;
                }
            },
            { passive: true }
        );

        syncNav();
    }

    /* --------------------------------------------------------------------
     * Mobile drawer
     * ----------------------------------------------------------------- */
    var drawer = document.getElementById('lx-drawer');
    var openBtn = document.querySelector('[data-lx-drawer-open]');
    var closeBtn = document.querySelector('[data-lx-drawer-close]');

    if (drawer && openBtn) {
        var lastFocused = null;

        var openDrawer = function () {
            lastFocused = document.activeElement;
            drawer.hidden = false;
            // Flush layout so the transition has a start value to run from.
            // A rAF callback would do the same but never fires while the tab
            // is throttled, which would leave the menu stuck invisible.
            void drawer.offsetHeight;
            drawer.classList.add('is-open');
            document.body.classList.add('lx-drawer-open');
            openBtn.setAttribute('aria-expanded', 'true');
            if (closeBtn) {
                closeBtn.focus();
            }
        };

        var closeDrawer = function () {
            drawer.classList.remove('is-open');
            document.body.classList.remove('lx-drawer-open');
            openBtn.setAttribute('aria-expanded', 'false');

            var finish = function () {
                drawer.hidden = true;
            };

            if (reduceMotion) {
                finish();
            } else {
                window.setTimeout(finish, 300);
            }

            if (lastFocused && typeof lastFocused.focus === 'function') {
                lastFocused.focus();
            }
        };

        openBtn.addEventListener('click', openDrawer);

        if (closeBtn) {
            closeBtn.addEventListener('click', closeDrawer);
        }

        drawer.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                closeDrawer();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && drawer.classList.contains('is-open')) {
                closeDrawer();
            }
        });

        // Keep tabbing inside the drawer while it covers the page.
        drawer.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab') {
                return;
            }

            var focusable = drawer.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );

            if (!focusable.length) {
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    /* --------------------------------------------------------------------
     * Scroll reveals
     * ----------------------------------------------------------------- */
    var revealTargets = document.querySelectorAll('[data-reveal]');

    if (!revealTargets.length) {
        return;
    }

    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealTargets.forEach(function (el) {
            el.classList.add('is-in');
        });

        return;
    }

    var observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }

                // A row of siblings reads better arriving in sequence than all
                // at once; the index is set in the markup.
                var delay = Number(entry.target.getAttribute('data-reveal')) || 0;
                entry.target.style.transitionDelay = delay * 80 + 'ms';
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -12% 0px', threshold: 0.08 }
    );

    revealTargets.forEach(function (el) {
        observer.observe(el);
    });
})();
