/**
 * Shared map setup for the editor panel and the reader pages.
 *
 * Tiles come from one OpenStreetMap file on this site, read in pieces by pmtiles,
 * so no visitor request leaves the site. Colours come from the theme's module tokens.
 */
(function () {
  'use strict';

  var protocolAdded = false;

  var ATTRIBUTION = '<a href="https://www.openstreetmap.org/copyright">&copy; OpenStreetMap</a>, '
    + '<a href="https://protomaps.com">Protomaps</a>';

  /** A theme token read from the element, or the fallback when the theme does not set it. */
  function token(element, name, fallback) {
    var value = getComputedStyle(element).getPropertyValue(name).trim();

    return value || fallback;
  }

  function ready() {
    return Boolean(window.maplibregl && window.pmtiles && window.basemaps);
  }

  /** The page's language, as the map labels understand it. */
  function language() {
    return (document.documentElement.lang || 'en').slice(0, 2);
  }

  function style(config, flavor) {
    return {
      version: 8,
      glyphs: config.glyphs,
      sprite: config.sprite + '/' + flavor,
      sources: {
        protomaps: { type: 'vector', url: config.tiles, attribution: ATTRIBUTION }
      },
      layers: window.basemaps.layers('protomaps', window.basemaps.namedFlavor(flavor), { lang: language() })
    };
  }

  /**
   * A map in the container, centred on view.center ([lng, lat]) at view.zoom.
   * Returns null when the map scripts did not load, so callers keep their plain list.
   */
  function create(container, config, view) {
    if (!ready()) {
      return null;
    }

    if (!protocolAdded) {
      window.maplibregl.addProtocol('pmtiles', new window.pmtiles.Protocol().tile);
      window.maplibregl.setWorkerUrl(config.worker);
      protocolAdded = true;
    }

    var flavor = token(container, '--module-map-flavor', 'light') === 'dark' ? 'dark' : 'light';

    return new window.maplibregl.Map({
      container: container,
      style: style(config, flavor),
      center: view.center,
      zoom: view.zoom,
      attributionControl: { compact: true },
      cooperativeGestures: Boolean(view.cooperative)
    });
  }

  /** True when the reader asked for less motion, so maps jump instead of fly. */
  function calm() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  window.JourneyMap = { create: create, token: token, calm: calm };
})();
