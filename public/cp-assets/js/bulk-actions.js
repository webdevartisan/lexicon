/**
 * Bulk selection for list pages built with views/components/bulk-actions-bar.lex.php.
 *
 * Nothing is looked up once and kept: table-sort.js replaces the whole table
 * region when a list is sorted, filtered or paged, which throws away the
 * checkboxes, the form and the bar with them. Every handler is delegated from
 * the document and every lookup happens at the moment it is needed, so the
 * page works the same before and after a swap.
 */
(function () {
    'use strict';

    // Spelled out rather than built, so Tailwind's scanner picks them up here.
    var SELECTED_ROW = ['bg-custom-50/70', 'dark:bg-custom-500/10'];

    var mountedBar = null;
    var anchor = null;

    // 'page' acts on the ticked rows, 'filter' on everything the list is
    // currently filtered to, however many pages that runs to.
    var scope = 'page';

    function checkboxes() {
        return Array.prototype.slice.call(document.querySelectorAll('.bulk-checkbox'));
    }

    function selected() {
        return checkboxes().filter(function (box) {
            return box.checked;
        });
    }

    function itemLabel() {
        return (mountedBar && mountedBar.dataset.itemLabel) || 'item';
    }

    /** How many rows the page's filter matches in total, across every page. */
    function matchTotal() {
        return mountedBar ? Number(mountedBar.dataset.matchTotal || 0) : 0;
    }

    /** How many of those are in each state, so the buttons can still count. */
    function matchCounts() {
        try {
            return JSON.parse((mountedBar && mountedBar.dataset.matchCounts) || '{}');
        } catch (e) {
            return {};
        }
    }

    function pluralized(n) {
        // Grouped, because a whole-filter selection can run to five figures and
        // "4415" in a confirmation is harder to read than the count above it.
        return n.toLocaleString() + ' ' + itemLabel() + (n === 1 ? '' : 's');
    }

    /**
     * How many of the selected rows an action can actually act on.
     *
     * An action without a data-applies-to list is assumed to suit everything,
     * which is what the pages that have no per-row state rely on.
     */
    function eligibleFor(button, picked) {
        var states = (button.getAttribute('data-applies-to') || '').split(' ').filter(Boolean);
        var wholeFilter = scope === 'filter';

        if (states.length === 0) {
            return wholeFilter ? matchTotal() : picked.length;
        }

        // Counted from the server's own tally when the selection is the whole
        // filter: the page only holds twenty-five rows and cannot answer for
        // the thousands behind them.
        if (wholeFilter) {
            var counts = matchCounts();

            return states.reduce(function (total, state) {
                return total + Number(counts[state] || 0);
            }, 0);
        }

        return picked.filter(function (box) {
            return states.indexOf(box.getAttribute('data-bulk-state')) !== -1;
        }).length;
    }

    /**
     * Take ownership of the bar the page just rendered.
     *
     * The bar is fixed to the viewport, and an ancestor with a transform would
     * pin it inside the card instead, so it is moved to the end of <body>. That
     * also puts it outside the swapped region: the one left over from the
     * previous render has to be dropped here, or it lingers with a count for
     * rows that no longer exist.
     */
    function mountBar() {
        if (mountedBar) {
            mountedBar.remove();
            mountedBar = null;
        }

        var fresh = document.querySelector('[data-bulk-bar]');

        if (fresh) {
            document.body.appendChild(fresh);
            mountedBar = fresh;
        }

        anchor = null;
        scope = 'page';
        refresh();
    }

    function refresh() {
        var boxes = checkboxes();
        var picked = boxes.filter(function (box) {
            return box.checked;
        });
        var n = picked.length;

        boxes.forEach(function (box) {
            var row = box.closest('[data-bulk-row]');

            if (row) {
                SELECTED_ROW.forEach(function (className) {
                    row.classList.toggle(className, box.checked);
                });
            }
        });

        var selectAll = document.getElementById('select-all');

        if (selectAll) {
            selectAll.checked = n > 0 && n === boxes.length;
            selectAll.indeterminate = n > 0 && n < boxes.length;
        }

        var counter = document.getElementById('selected-count');

        if (counter) {
            counter.textContent = n > 0 ? pluralized(n) + ' selected' : '';
        }

        if (!mountedBar) {
            return;
        }

        mountedBar.classList.toggle('hidden', n === 0);

        var everythingOnPage = n > 0 && n === boxes.length;

        // The offer only makes sense once this page is exhausted and there is
        // more behind it.
        if (!everythingOnPage) {
            scope = 'page';
        }

        var wholeFilter = scope === 'filter';
        var barCount = mountedBar.querySelector('[data-bulk-selected-count]');

        if (barCount) {
            barCount.textContent = wholeFilter
                ? matchTotal().toLocaleString() + ' selected'
                : n + ' selected';
        }

        var scopeField = document.getElementById('bulk-scope');

        if (scopeField) {
            scopeField.value = scope;
        }

        var scopeToggle = mountedBar.querySelector('[data-bulk-scope-toggle]');

        if (scopeToggle) {
            var offer = everythingOnPage && matchTotal() > boxes.length;

            scopeToggle.classList.toggle('hidden', !offer);
            scopeToggle.textContent = wholeFilter
                ? 'Clear selection'
                : 'Select all ' + matchTotal().toLocaleString() + ' matching this filter';
        }

        mountedBar.querySelectorAll('[data-bulk]').forEach(function (button) {
            var eligible = eligibleFor(button, picked);
            var badge = button.querySelector('[data-bulk-eligible-count]');

            button.disabled = eligible === 0;
            button.title = eligible === 0 ? 'None of the selected ' + itemLabel() + 's can take this action' : '';
            button.setAttribute('data-bulk-eligible', String(eligible));

            if (badge) {
                badge.textContent = String(eligible);
                badge.classList.toggle('hidden', !button.hasAttribute('data-applies-to'));
            }
        });
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest ? e.target.closest('[data-bulk-scope-toggle]') : null;

        if (!toggle || !mountedBar || !mountedBar.contains(toggle)) {
            return;
        }

        if (scope === 'filter') {
            scope = 'page';
            checkboxes().forEach(function (box) {
                box.checked = false;
            });
        } else {
            scope = 'filter';
        }

        refresh();
    });

    document.addEventListener('change', function (e) {
        var target = e.target;

        if (target.id === 'select-all') {
            checkboxes().forEach(function (box) {
                box.checked = target.checked;
            });
            anchor = null;
            refresh();

            return;
        }

        if (target.classList && target.classList.contains('bulk-checkbox')) {
            refresh();
        }
    });

    // Shift-click picks the whole run since the last box, the way every other
    // list with checkboxes behaves. Read on click because change carries no
    // modifier keys; by then the box already holds its new state.
    document.addEventListener('click', function (e) {
        var box = e.target.closest ? e.target.closest('.bulk-checkbox') : null;

        if (!box) {
            return;
        }

        var boxes = checkboxes();
        var index = boxes.indexOf(box);

        if (e.shiftKey && anchor !== null && anchor !== index && anchor < boxes.length) {
            for (var i = Math.min(anchor, index); i <= Math.max(anchor, index); i++) {
                boxes[i].checked = box.checked;
            }
            refresh();
        }

        anchor = index;
    });

    document.addEventListener('click', function (e) {
        var button = e.target.closest ? e.target.closest('[data-bulk]') : null;

        if (!button || button.disabled || !mountedBar || !mountedBar.contains(button)) {
            return;
        }

        var form = document.getElementById('bulk-form');
        var action = document.getElementById('bulk-action');

        if (!form || !action) {
            return;
        }

        var template = button.getAttribute('data-confirm-template');

        if (template) {
            var eligible = Number(button.getAttribute('data-bulk-eligible') || 0);
            var question = template.replace('{n}', pluralized(eligible));

            if (scope === 'filter') {
                question += ' This is every one matching the filter on screen, not just this page.';
            } else {
                var skipped = selected().length - eligible;

                if (skipped > 0) {
                    question += ' The other ' + skipped + ' selected will be skipped.';
                }
            }

            if (!window.confirm(question)) {
                return;
            }
        }

        action.value = button.getAttribute('data-bulk');
        form.submit();
    });

    document.addEventListener('table:swapped', mountBar);

    mountBar();
})();
