/**
 * Reader maps: the small locator beside a post and the whole route on the Journey page.
 *
 * Maps start only when they scroll near the screen, so a long page never loads tiles
 * the reader does not reach. Without the map scripts the page keeps its plain list.
 */
(function () {
  'use strict';

  /** Parse a JSON attribute or script block, or null when it is missing or broken. */
  function readJson(text) {
    try {
      return JSON.parse(text || 'null');
    } catch (error) {
      return null;
    }
  }

  /**
   * A theme colour as rgb(), which the map understands. Themes may write colours
   * in any CSS form, so the browser converts it through a hidden element.
   */
  function themeColor(element, name, fallback) {
    var probe = document.createElement('span');
    probe.style.display = 'none';
    probe.style.color = window.JourneyMap.token(element, name, fallback);
    element.appendChild(probe);
    var color = getComputedStyle(probe).color || fallback;
    probe.remove();

    return color;
  }

  /** A round pin in the theme's accent, used where a marker is only a marker. */
  function pin(className, text) {
    var element = document.createElement(text ? 'button' : 'span');
    element.className = className;
    if (text) {
      element.type = 'button';
      element.textContent = text;
    }

    return element;
  }

  function startLocator(box) {
    var config = readJson(box.dataset.map);
    var view = readJson(box.dataset.view);
    if (!config || !view) {
      return;
    }

    var map = window.JourneyMap.create(box, config, {
      center: [view.lng, view.lat],
      zoom: view.zoom,
      cooperative: true
    });
    if (map) {
      new window.maplibregl.Marker({ element: pin('journey-map-pin') }).setLngLat([view.lng, view.lat]).addTo(map);
    }
  }

  /** Add a text element to the parent, skipping empty text. */
  function appendText(parent, tag, className, text) {
    if (!text) {
      return;
    }

    var element = document.createElement(tag);
    element.className = className;
    element.textContent = text;
    parent.appendChild(element);
  }

  /** The card a stop's pin opens, built from text so no post field is ever read as HTML. */
  function card(stop, readLabel) {
    var article = document.createElement('article');
    article.className = 'journey-map-card';

    if (stop.cover) {
      var image = document.createElement('img');
      image.src = stop.cover;
      image.alt = '';
      image.loading = 'lazy';
      article.appendChild(image);
    }

    appendText(article, 'p', 'journey-map-card-meta', stop.label + ' · ' + stop.place);
    appendText(article, 'h3', '', stop.title);
    appendText(article, 'p', '', stop.summary);

    var link = document.createElement('a');
    link.href = stop.url;
    link.textContent = readLabel;
    article.appendChild(link);

    return article;
  }

  function drawRoute(map, stops, color) {
    if (stops.length < 2) {
      return;
    }

    map.addSource('journey-route', {
      type: 'geojson',
      data: {
        type: 'Feature',
        geometry: { type: 'LineString', coordinates: stops.map(function (stop) { return [stop.lng, stop.lat]; }) }
      }
    });
    map.addLayer({
      id: 'journey-route',
      type: 'line',
      source: 'journey-route',
      layout: { 'line-join': 'round', 'line-cap': 'round' },
      paint: { 'line-color': color, 'line-width': 3, 'line-opacity': 0.85 }
    });
  }

  function addStops(map, stops, readLabel) {
    stops.forEach(function (stop, index) {
      var marker = pin('journey-map-marker', String(index + 1));
      marker.setAttribute('aria-label', stop.label + ': ' + stop.title);

      new window.maplibregl.Marker({ element: marker })
        .setLngLat([stop.lng, stop.lat])
        .setPopup(new window.maplibregl.Popup({ offset: 18, maxWidth: '18rem' }).setDOMContent(card(stop, readLabel)))
        .addTo(map);
    });
  }

  /** Frame every stop, jumping straight there when the reader prefers less motion. */
  function frame(map, stops) {
    if (stops.length === 1) {
      map.jumpTo({ center: [stops[0].lng, stops[0].lat], zoom: 8 });

      return;
    }

    var bounds = new window.maplibregl.LngLatBounds();
    stops.forEach(function (stop) { bounds.extend([stop.lng, stop.lat]); });
    map.fitBounds(bounds, { padding: 56, maxZoom: 9, animate: !window.JourneyMap.calm() });
  }

  function startJourney(box) {
    var config = readJson(box.dataset.map);
    var block = document.querySelector('[data-journey-map-stops]');
    var stops = readJson(block && block.textContent) || [];
    if (!config || stops.length === 0) {
      return;
    }

    var map = window.JourneyMap.create(box, config, { center: [stops[0].lng, stops[0].lat], zoom: 4 });
    if (!map) {
      return;
    }

    addStops(map, stops, box.dataset.readLabel || '');
    map.on('load', function () {
      drawRoute(map, stops, themeColor(box, '--module-accent', '#1f4f99'));
      frame(map, stops);
    });
  }

  function start(box) {
    if (box.dataset.journeyMap === 'journey') {
      startJourney(box);
    } else {
      startLocator(box);
    }
  }

  /** Start each map once it comes near the screen, or all at once where the browser cannot tell. */
  function watch(boxes) {
    if (!('IntersectionObserver' in window)) {
      boxes.forEach(start);

      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          observer.unobserve(entry.target);
          start(entry.target);
        }
      });
    }, { rootMargin: '200px' });

    boxes.forEach(function (box) { observer.observe(box); });
  }

  if (!window.JourneyMap) {
    return;
  }

  watch(Array.prototype.slice.call(document.querySelectorAll('[data-journey-map]')));
})();
