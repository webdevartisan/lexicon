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
        levelText.className = 'lx-meter-level';
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

/* Explore toolbar auto-scroll: when the page URL carries a filter, tab or
   page-change query string, jump the viewport to the toolbar on load so the
   user lands on their filter change rather than the hero slider above it.
   Runs only on pages that actually have the toolbar. */
(function () {
    var toolbar = document.querySelector('.lx-explore-toolbar');
    if (!toolbar) return;

    var params = new URLSearchParams(window.location.search);
    var triggered = params.has('tab') || params.has('q') || params.has('page');
    if (!triggered) return;

    // rAF so layout is settled before we measure — otherwise the initial
    // scroll can land short on Chrome when web fonts shift the toolbar.
    window.requestAnimationFrame(function () {
        toolbar.scrollIntoView({ behavior: 'auto', block: 'start' });
    });
})();

/* Featured-blogs slider: native scroll-snap under the hood, arrows step one
   card, dots reflect the real position. Seamless loop is faked by cloning
   the first and last slides at the opposite ends — when the user (or autoplay)
   crosses onto a clone, we snap back to the corresponding real slide with no
   animation, so it feels continuous instead of rewinding across the page.
   Autoplay pauses on hover/focus/tab-hidden, off entirely under reduced motion. */
(function () {
    var sliders = document.querySelectorAll('[data-slider]');
    if (!sliders.length) return;

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var SMOOTH_MS = 650; // give the smooth scroll enough time to settle before we teleport.

    sliders.forEach(function (root) {
        var rail = root.querySelector('[data-slider-rail]');
        var realSlides = rail ? Array.prototype.slice.call(rail.querySelectorAll('[data-slider-slide]')) : [];
        if (!rail || realSlides.length < 2) return;

        var realCount = realSlides.length;

        // Clone the last slide before the first, and the first slide after the
        // last. Rail order becomes: [cloneLast, real0, real1, ..., realN-1, cloneFirst].
        var cloneLast = realSlides[realCount - 1].cloneNode(true);
        var cloneFirst = realSlides[0].cloneNode(true);
        cloneLast.setAttribute('aria-hidden', 'true');
        cloneFirst.setAttribute('aria-hidden', 'true');
        cloneLast.setAttribute('data-slider-clone', 'true');
        cloneFirst.setAttribute('data-slider-clone', 'true');
        rail.insertBefore(cloneLast, realSlides[0]);
        rail.appendChild(cloneFirst);

        var allSlides = Array.prototype.slice.call(rail.querySelectorAll('[data-slider-slide]'));
        // Real slides now sit at DOM indices 1..realCount. Clones at 0 and realCount+1.
        var realOffset = 1;

        var prev = root.querySelector('[data-slider-prev]');
        var next = root.querySelector('[data-slider-next]');
        var dotsWrap = root.querySelector('[data-slider-dots]');
        var current = 0; // logical index into real slides, 0..realCount-1.
        var isLooping = false;

        var dots = [];
        if (dotsWrap) {
            for (var i = 0; i < realCount; i++) {
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'lx-slider-dot';
                dot.setAttribute('aria-label', 'Slide ' + (i + 1) + ' of ' + realCount);
                (function (idx) {
                    dot.addEventListener('click', function () { jumpTo(idx); });
                })(i);
                dotsWrap.appendChild(dot);
                dots.push(dot);
            }
        }

        function updateDots() {
            dots.forEach(function (d, idx) {
                d.classList.toggle('is-active', idx === current);
                d.setAttribute('aria-current', idx === current ? 'true' : 'false');
            });
        }

        function scrollToDom(domIdx, instant) {
            var target = allSlides[domIdx];
            if (!target) return;
            var railRect = rail.getBoundingClientRect();
            var targetRect = target.getBoundingClientRect();
            var delta = targetRect.left - railRect.left;
            // 'instant' beats the CSS `scroll-behavior: smooth` we set on the
            // rail; 'auto' would fall back to it and re-animate the teleport,
            // which is exactly the "long rewind" we're trying to hide.
            rail.scrollTo({
                left: rail.scrollLeft + delta,
                behavior: (reducedMotion || instant) ? 'instant' : 'smooth',
            });
        }

        // Direct jump used by dots — no seamless-loop dance because dots are
        // arbitrary destinations, not next/prev.
        function jumpTo(realIdx) {
            if (isLooping) return;
            current = ((realIdx % realCount) + realCount) % realCount;
            scrollToDom(current + realOffset, false);
            updateDots();
        }

        // Step one card in either direction; handles the seamless loop when
        // we cross a real→clone boundary.
        function step(dir) {
            if (isLooping) return;
            var nextReal = current + dir;

            if (nextReal >= realCount) {
                // Animate forward onto the cloned first-slide, then teleport
                // back to the real first slide once the smooth-scroll settles.
                isLooping = true;
                scrollToDom(realCount + realOffset, false);
                current = 0;
                updateDots();
                setTimeout(function () {
                    scrollToDom(realOffset, true);
                    isLooping = false;
                }, SMOOTH_MS);
            } else if (nextReal < 0) {
                // Same trick going backward: animate onto the prepended clone
                // of the last slide, then teleport to the real last slide.
                isLooping = true;
                scrollToDom(0, false);
                current = realCount - 1;
                updateDots();
                setTimeout(function () {
                    scrollToDom(realCount - 1 + realOffset, true);
                    isLooping = false;
                }, SMOOTH_MS);
            } else {
                current = nextReal;
                scrollToDom(current + realOffset, false);
                updateDots();
            }
        }

        if (prev) prev.addEventListener('click', function () { step(-1); });
        if (next) next.addEventListener('click', function () { step(1); });

        // Manual scroll (swipe / trackpad) updates the active dot after
        // a short debounce; suppressed while a loop teleport is in progress.
        var scrollTimer;
        rail.addEventListener('scroll', function () {
            if (isLooping) return;
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function () {
                var width = rail.clientWidth;
                if (width <= 0) return;
                var domIdx = Math.round(rail.scrollLeft / width);
                // Map DOM index back to real; clones show as their neighbouring real slide.
                if (domIdx <= 0) current = 0;
                else if (domIdx >= realCount + 1) current = realCount - 1;
                else current = domIdx - realOffset;
                updateDots();
            }, 120);
        });

        // Land on the real first slide, past the prepended clone, before the
        // user sees anything. Waits for layout so widths are correct.
        window.requestAnimationFrame(function () {
            scrollToDom(realOffset, true);
            updateDots();
        });

        // Autoplay — quiet when the user is interacting or prefers reduced motion.
        var autoplay = null;
        function startAutoplay() {
            if (reducedMotion || autoplay) return;
            autoplay = setInterval(function () { step(1); }, 6000);
        }
        function stopAutoplay() {
            if (autoplay) { clearInterval(autoplay); autoplay = null; }
        }
        root.addEventListener('mouseenter', stopAutoplay);
        root.addEventListener('mouseleave', startAutoplay);
        root.addEventListener('focusin', stopAutoplay);
        root.addEventListener('focusout', startAutoplay);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stopAutoplay(); else startAutoplay();
        });

        startAutoplay();
    });
})();
