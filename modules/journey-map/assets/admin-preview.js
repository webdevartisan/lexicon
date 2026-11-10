/**
 * The control panel's look at the installed map: the downloaded area, outlined, so the admin
 * sees what readers can zoom into. A world map has no edge, so it just shows the whole map.
 */
(function () {
  'use strict';

  var box = document.querySelector('[data-area-preview]');
  if (!box || !window.JourneyMap) {
    return;
  }

  var area = JSON.parse(box.dataset.bbox || 'null');
  var map = window.JourneyMap.create(box, JSON.parse(box.dataset.areaPreview), { center: [0, 20], zoom: 0 });
  if (!map) {
    return;
  }

  /** The box [west, south, east, north] as a closed ring of points. */
  function ring(edge) {
    return [[edge[0], edge[1]], [edge[2], edge[1]], [edge[2], edge[3]], [edge[0], edge[3]], [edge[0], edge[1]]];
  }

  function outline() {
    var color = window.JourneyMap.token(box, '--module-accent', '#2563eb');
    map.addSource('journey-map-area', {
      type: 'geojson',
      data: { type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [ring(area)] } }
    });
    map.addLayer({
      id: 'journey-map-area-fill',
      type: 'fill',
      source: 'journey-map-area',
      paint: { 'fill-color': color, 'fill-opacity': 0.08 }
    });
    map.addLayer({
      id: 'journey-map-area-line',
      type: 'line',
      source: 'journey-map-area',
      paint: { 'line-color': color, 'line-width': 2 }
    });
  }

  if (area) {
    map.fitBounds([[area[0], area[1]], [area[2], area[3]]], { padding: 24, animate: false });
    map.on('load', outline);
  }
})();
