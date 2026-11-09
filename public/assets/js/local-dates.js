/**
 * Moves public post dates onto the reader's own day. A guest's page is cached
 * in UTC with the day before and after written next to each date, so this
 * only picks one of them. The server still does the wording.
 *
 * Loaded in the head without defer, so each date is fixed as the page is read
 * and the UTC day is never painted. Dates a theme adds later are fixed too.
 */
(function () {
  'use strict';

  var zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  if (!zone || !window.MutationObserver) return;

  var readerDay = new Intl.DateTimeFormat('en-US', {
    timeZone: zone, year: 'numeric', month: '2-digit', day: '2-digit'
  });
  var loaded = false;

  function dayOf(date) {
    var parts = {};
    readerDay.formatToParts(date).forEach(function (part) { parts[part.type] = part.value; });
    return parts.year + '-' + parts.month + '-' + parts.day;
  }

  function shift(day, by) {
    var bits = day.split('-');
    return new Date(Date.UTC(+bits[0], bits[1] - 1, +bits[2] + by)).toISOString().slice(0, 10);
  }

  // A <time> can arrive before its text does. Once anything follows it, the text is complete.
  function readPast(el) {
    for (var node = el; node; node = node.parentNode) {
      if (node.nextSibling) return true;
    }
    return false;
  }

  function settle(el) {
    var utcDay = el.getAttribute('data-day');
    var moment = new Date(el.getAttribute('datetime'));
    el.removeAttribute('data-day');
    if (isNaN(moment) || !/^\d{4}-\d{2}-\d{2}$/.test(utcDay)) return;

    var day = dayOf(moment);
    if (day === shift(utcDay, -1)) {
      el.textContent = el.dataset.dayBefore;
    } else if (day === shift(utcDay, 1)) {
      el.textContent = el.dataset.dayAfter;
    }
  }

  function settleAll() {
    var dates = document.querySelectorAll('time[data-day]');
    for (var i = 0; i < dates.length; i++) {
      if (loaded || readPast(dates[i])) settle(dates[i]);
    }
  }

  new MutationObserver(settleAll).observe(document.documentElement, {childList: true, subtree: true});
  document.addEventListener('DOMContentLoaded', function () {
    loaded = true;
    settleAll();
  });
})();
