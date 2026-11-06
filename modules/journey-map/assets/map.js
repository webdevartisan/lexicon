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
    if (!config || !view || !view.stops || view.stops.length === 0) {
      return;
    }

    var map = window.JourneyMap.create(box, config, {
      center: [view.stops[0].lng, view.stops[0].lat],
      zoom: view.zoom,
      cooperative: true
    });
    if (!map) {
      return;
    }

    view.stops.forEach(function (stop) {
      var marker = pin('journey-map-pin');
      marker.title = stop.place;
      new window.maplibregl.Marker({ element: marker }).setLngLat([stop.lng, stop.lat]).addTo(map);
    });
    // Each place carries the road that leads into it, so the next stop takes the road out of the post's last one.
    var leg = [view.previous].concat(view.stops, [view.next]).filter(Boolean);
    if (leg.length < 2) {
      return;
    }

    addNeighbours(map, view, box.dataset.nextLabel || '');
    map.on('load', function () {
      drawRoute(map, leg, themeColor(box, '--module-accent', '#1f4f99'));
      frameLeg(map, view);
    });
  }

  /** The places either side of the post, faint; the next one is a link to its post. */
  function addNeighbours(map, view, nextLabel) {
    if (view.previous) {
      var before = pin('journey-map-neighbour');
      before.title = view.previous.place;
      new window.maplibregl.Marker({ element: before }).setLngLat([view.previous.lng, view.previous.lat]).addTo(map);
    }

    if (view.next) {
      var link = document.createElement('a');
      link.className = 'journey-map-neighbour journey-map-neighbour-next';
      link.href = view.next.url;
      link.setAttribute('aria-label', nextLabel + ': ' + view.next.title + ', ' + view.next.place);
      new window.maplibregl.Marker({ element: link }).setLngLat([view.next.lng, view.next.lat]).addTo(map);
    }
  }

  /**
   * Frame the post's places with the neighbours near enough; far ones stay off the edge with the line
   * pointing at them.
   */
  function frameLeg(map, view) {
    var near = [view.previous, view.next].filter(function (stop) { return stop && stop.framed; });
    var shown = view.stops.concat(near);
    if (shown.length < 2) {
      return;
    }

    var first = [view.stops[0].lng, view.stops[0].lat];
    var bounds = new window.maplibregl.LngLatBounds(first, first);
    shown.forEach(function (stop) { bounds.extend([stop.lng, stop.lat]); });
    map.fitBounds(bounds, { padding: 40, maxZoom: view.zoom, animate: false });
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

  /**
   * The legs up to a stop. A leg follows its road when one was planned, otherwise it runs straight
   * and is marked so the map can dash it.
   */
  function routeData(stops, upTo) {
    var features = [];
    for (var i = 1; i <= upTo && i < stops.length; i++) {
      var road = stops[i].lineIn;
      features.push({
        type: 'Feature',
        properties: { road: Boolean(road) },
        geometry: {
          type: 'LineString',
          coordinates: road || [[stops[i - 1].lng, stops[i - 1].lat], [stops[i].lng, stops[i].lat]]
        }
      });
    }

    return { type: 'FeatureCollection', features: features };
  }

  function drawRoute(map, stops, color) {
    if (stops.length < 2) {
      return;
    }

    map.addSource('journey-route', { type: 'geojson', data: routeData(stops, stops.length - 1) });
    [true, false].forEach(function (road) {
      var paint = { 'line-color': color, 'line-width': 3, 'line-opacity': 0.85 };
      if (!road) {
        paint['line-dasharray'] = [2, 2];
      }
      map.addLayer({
        id: road ? 'journey-route' : 'journey-route-straight',
        type: 'line',
        source: 'journey-route',
        filter: ['==', ['get', 'road'], road],
        layout: { 'line-join': 'round', 'line-cap': road ? 'round' : 'butt' },
        paint: paint
      });
    });
  }

  function addStops(map, stops, readLabel) {
    stops.forEach(function (stop) {
      // A post's further places share its number, so they get a small dot instead of a number of their own.
      var marker = stop.number
        ? pin('journey-map-marker', String(stop.number))
        : pin('journey-map-marker journey-map-marker-more', ' ');
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
      // The replay lives in its own file and picks the map up from here.
      box.dispatchEvent(new CustomEvent('journey-map:ready', {
        detail: { map: map, stops: stops, overview: function () { frame(map, stops); } }
      }));
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

  window.JourneyMap.route = routeData;
  watch(Array.prototype.slice.call(document.querySelectorAll('[data-journey-map]')));
})();
