/**
 * The Journey page tells the trip as the reader scrolls: the route grows to the stop being read
 * and the map glides there. Readers who prefer less motion keep the whole route, standing still.
 */
(function () {
  'use strict';

  var ACTIVE = 'is-current';


  /** Mark one list entry as the stop being read, or none. */
  function mark(items, index) {
    items.forEach(function (item) {
      item.classList.toggle(ACTIVE, Number(item.dataset.journeyStop) === index);
    });
  }

  /**
   * Follow the stops through the list. A stop counts as read once it crosses the middle of the
   * screen; scrolling back above the list shows the whole route again.
   */
  function replay(detail, items) {
    var map = detail.map;
    var current = -1;

    function show(index) {
      if (index === current) {
        return;
      }
      current = index;
      mark(items, index);

      if (index < 0) {
        map.getSource('journey-route').setData(window.JourneyMap.route(detail.stops, detail.stops.length - 1));
        detail.overview();

        return;
      }

      var stop = detail.stops[index];
      map.getSource('journey-route').setData(window.JourneyMap.route(detail.stops, index));
      map.easeTo({ center: [stop.lng, stop.lat], zoom: Math.max(map.getZoom(), 7), duration: 900 });
    }

    var observer = new IntersectionObserver(function () {
      var reached = items.filter(function (item) {
        return item.getBoundingClientRect().top < window.innerHeight / 2;
      });
      show(reached.length === 0 ? -1 : Number(reached[reached.length - 1].dataset.journeyStop));
    }, { rootMargin: '0px 0px -50% 0px', threshold: [0, 1] });

    items.forEach(function (item) { observer.observe(item); });
  }

  var box = document.querySelector('[data-journey-map="journey"]');
  if (!box || !('IntersectionObserver' in window) || !window.JourneyMap || window.JourneyMap.calm()) {
    return;
  }

  box.addEventListener('journey-map:ready', function (event) {
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-journey-stop]'));
    if (items.length > 1 && event.detail.map.getSource('journey-route')) {
      replay(event.detail, items);
    }
  }, { once: true });
})();
