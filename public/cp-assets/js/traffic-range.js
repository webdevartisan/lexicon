/**
 * The date range menu on the Traffic page: presets are plain links, and the
 * custom range swaps its two date fields for one inline range calendar.
 */
(function () {
    'use strict';

    var menu = document.querySelector('[data-range-menu]');
    if (!menu) {
        return;
    }

    var summary = menu.querySelector('summary');

    document.addEventListener('click', function (event) {
        if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });

    menu.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && menu.open) {
            menu.open = false;
            summary.focus();
        }
    });

    var form = menu.querySelector('[data-range-form]');
    var calendar = form.querySelector('[data-range-calendar]');
    var fields = form.querySelector('[data-range-fields]');
    var tooLong = form.querySelector('[data-range-too-long]');
    var from = form.elements.from;
    var to = form.elements.to;
    var maxDays = parseInt(form.dataset.maxDays, 10);
    var dayMs = 86400000;

    function spanDays(start, end) {
        return Math.round((end - start) / dayMs) + 1;
    }

    function iso(date) {
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');

        return date.getFullYear() + '-' + month + '-' + day;
    }

    function pageLocale() {
        var lang = document.documentElement.lang || 'en';
        var names = function (options, count, start) {
            var format = new Intl.DateTimeFormat(lang, options);
            var out = [];
            for (var i = 0; i < count; i++) {
                out.push(format.format(start(i)));
            }
            return out;
        };
        // 2023-01-01 was a Sunday, which is where flatpickr starts its weekday lists.
        var weekday = function (i) { return new Date(2023, 0, 1 + i); };
        var month = function (i) { return new Date(2023, i, 1); };
        var firstDay = 1;
        try {
            var info = new Intl.Locale(lang);
            var week = info.getWeekInfo ? info.getWeekInfo() : info.weekInfo;
            if (week && week.firstDay) {
                firstDay = week.firstDay % 7;
            }
        } catch (error) {
            firstDay = 1;
        }

        return {
            weekdays: {
                shorthand: names({ weekday: 'short' }, 7, weekday),
                longhand: names({ weekday: 'long' }, 7, weekday)
            },
            months: {
                shorthand: names({ month: 'short' }, 12, month),
                longhand: names({ month: 'long' }, 12, month)
            },
            firstDayOfWeek: firstDay,
            rangeSeparator: ' / '
        };
    }

    if (typeof flatpickr !== 'function') {
        return;
    }

    var anchor = document.createElement('input');
    anchor.type = 'hidden';
    calendar.appendChild(anchor);

    var picker = flatpickr(anchor, {
        inline: true,
        mode: 'range',
        dateFormat: 'Y-m-d',
        maxDate: form.dataset.maxDate,
        defaultDate: [from.value, to.value],
        locale: pageLocale(),
        onChange: function (dates) {
            if (dates.length !== 2) {
                return;
            }
            from.value = iso(dates[0]);
            to.value = iso(dates[1]);
            tooLong.classList.toggle('hidden', spanDays(dates[0], dates[1]) <= maxDays);
        }
    });

    // flatpickr turns the hidden anchor back into a visible text field.
    picker.input.classList.add('hidden');
    calendar.classList.remove('hidden');
    fields.classList.add('hidden');

    form.addEventListener('submit', function (event) {
        if (!tooLong.classList.contains('hidden')) {
            event.preventDefault();
        }
    });
})();
