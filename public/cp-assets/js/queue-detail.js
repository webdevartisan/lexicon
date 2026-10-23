/**
 * List-and-detail behaviour for queue pages (moderation, comments).
 *
 * Items are buttons carrying data-queue-item="<id>"; the matching detail is a
 * [data-queue-panel="<id>"] inside the same .queue. Everything is delegated and
 * looked up on demand, because table-sort.js swaps the whole region when the
 * list is paged or sorted.
 */
(function () {
    'use strict';

    var wide = window.matchMedia('(min-width: 1024px)');

    function select(queue, id, open) {
        var found = false;

        queue.querySelectorAll('[data-queue-item]').forEach(function (item) {
            var on = item.getAttribute('data-queue-item') === id;
            item.setAttribute('aria-selected', on ? 'true' : 'false');
            found = found || on;
        });

        queue.querySelectorAll('[data-queue-panel]').forEach(function (panel) {
            var on = panel.getAttribute('data-queue-panel') === id;
            panel.toggleAttribute('data-active', on);
        });

        if (!found) {
            return;
        }

        if (open) {
            queue.setAttribute('data-open', '');
        }
    }

    function close(queue) {
        queue.removeAttribute('data-open');
    }

    function setup() {
        document.querySelectorAll('.queue').forEach(function (queue) {
            var current = queue.querySelector('[data-queue-item][aria-selected="true"]');
            var first = queue.querySelector('[data-queue-item]');
            var pick = current || (wide.matches ? first : null);

            if (pick) {
                select(queue, pick.getAttribute('data-queue-item'), false);
            }
        });
    }

    document.addEventListener('click', function (e) {
        var item = e.target.closest ? e.target.closest('[data-queue-item]') : null;

        if (item) {
            var queue = item.closest('.queue');
            select(queue, item.getAttribute('data-queue-item'), true);

            return;
        }

        var step = e.target.closest ? e.target.closest('[data-queue-step]') : null;

        if (step) {
            var box = step.closest('.queue');
            var items = Array.prototype.slice.call(box.querySelectorAll('[data-queue-item]'));
            var at = items.findIndex(function (el) {
                return el.getAttribute('aria-selected') === 'true';
            });
            var target = items[at + Number(step.getAttribute('data-queue-step'))];

            if (target) {
                select(box, target.getAttribute('data-queue-item'), true);
                target.scrollIntoView({block: 'nearest'});
            }

            return;
        }

        var back = e.target.closest ? e.target.closest('[data-queue-back]') : null;

        if (back) {
            close(back.closest('.queue'));
        }
    });

    document.addEventListener('keydown', function (e) {
        var item = e.target.closest ? e.target.closest('[data-queue-item]') : null;

        if (!item || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) {
            return;
        }

        var items = Array.prototype.slice.call(item.closest('.queue').querySelectorAll('[data-queue-item]'));
        var next = items[items.indexOf(item) + (e.key === 'ArrowDown' ? 1 : -1)];

        if (next) {
            e.preventDefault();
            next.focus();
            select(next.closest('.queue'), next.getAttribute('data-queue-item'), false);
        }
    });

    document.addEventListener('table:swapped', setup);
    wide.addEventListener('change', setup);
    setup();
})();
