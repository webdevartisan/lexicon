/**
 * The Place panel in the post editor: search, drop a pin, save or remove the post's place.
 *
 * The panel lives inside the post form, so Enter in its fields must never submit the post.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-journey-map-editor]');
  if (!root) {
    return;
  }

  var text = JSON.parse(root.querySelector('[data-journey-map-text]').textContent);
  var results = root.querySelector('[data-results]');
  var status = root.querySelector('[data-status]');
  var map = null;
  var marker = null;

  function field(name) {
    return root.querySelector('[data-field="' + name + '"]');
  }

  function say(message, isError) {
    status.textContent = message || '';
    status.classList.toggle('journey-map-error', Boolean(isError));
  }

  /** Fetch with the session's CSRF token; always resolves to { ok, body }. */
  function send(url, options) {
    options.credentials = 'same-origin';
    options.headers = { 'X-CSRF-TOKEN': root.dataset.token, 'Accept': 'application/json' };

    return fetch(url, options).then(function (response) {
      return response.json()
        .catch(function () { return {}; })
        .then(function (body) { return { ok: response.ok, body: body }; });
    });
  }

  function showPoint(lat, lng) {
    if (!map) {
      return;
    }
    if (!marker) {
      marker = new window.maplibregl.Marker({
        color: window.JourneyMap.token(root, '--module-accent', '#2563eb'),
        draggable: true
      });
      marker.on('dragend', function () {
        var spot = marker.getLngLat();
        writePoint(spot.lat, spot.lng);
      });
    }
    marker.setLngLat([lng, lat]).addTo(map);
    map.jumpTo({ center: [lng, lat], zoom: Math.max(map.getZoom(), 8) });
  }

  function writePoint(lat, lng) {
    field('lat').value = lat.toFixed(6);
    field('lng').value = lng.toFixed(6);
  }

  function setPoint(lat, lng) {
    writePoint(lat, lng);
    showPoint(lat, lng);
  }

  function choose(place) {
    field('place_name').value = place.name.split(',')[0].trim();
    setPoint(place.lat, place.lng);
    results.hidden = true;
  }

  function showResults(places) {
    results.replaceChildren();
    results.hidden = places.length === 0;
    if (places.length === 0) {
      say(text.noResults);
      return;
    }

    places.forEach(function (place) {
      var item = document.createElement('li');
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = place.name;
      button.addEventListener('click', function () { choose(place); });
      item.appendChild(button);
      results.appendChild(item);
    });
  }

  function search() {
    var query = field('query').value.trim();
    if (query.length < 3) {
      return;
    }

    say('');
    send(root.dataset.placesUrl + '?' + new URLSearchParams({ q: query }), { method: 'GET' })
      .then(function (result) {
        if (result.ok) {
          showResults(result.body.places || []);
        } else {
          say(result.body.error || text.failed, true);
        }
      })
      .catch(function () { say(text.failed, true); });
  }

  function save() {
    var body = new URLSearchParams();
    ['place_name', 'lat', 'lng', 'precision', 'stop_number'].forEach(function (name) {
      body.append(name, field(name).value.trim());
    });

    send(root.dataset.saveUrl, { method: 'POST', body: body })
      .then(function (result) {
        if (result.ok) {
          say(result.body.message);
          return;
        }
        var errors = result.body.errors ? Object.values(result.body.errors).join(' ') : text.saveFailed;
        say(errors, true);
      })
      .catch(function () { say(text.saveFailed, true); });
  }

  function remove() {
    send(root.dataset.removeUrl, { method: 'POST', body: new URLSearchParams() })
      .then(function (result) {
        say(result.ok ? result.body.message : text.saveFailed, !result.ok);
        if (result.ok) {
          ['place_name', 'lat', 'lng', 'stop_number'].forEach(function (name) { field(name).value = ''; });
          if (marker) {
            marker.remove();
          }
        }
      })
      .catch(function () { say(text.saveFailed, true); });
  }

  function startMap() {
    var box = root.querySelector('[data-map]');
    if (!box || !window.JourneyMap) {
      return;
    }

    var lat = parseFloat(field('lat').value);
    var lng = parseFloat(field('lng').value);
    var placed = !isNaN(lat) && !isNaN(lng);
    map = window.JourneyMap.create(box, JSON.parse(box.dataset.map), {
      center: placed ? [lng, lat] : [0, 20],
      zoom: placed ? 8 : 1
    });
    if (!map) {
      return;
    }

    map.on('click', function (event) { setPoint(event.lngLat.lat, event.lngLat.lng); });
    if (placed) {
      showPoint(lat, lng);
    }
  }

  root.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter' || event.target.tagName !== 'INPUT') {
      return;
    }
    event.preventDefault();
    if (event.target === field('query')) {
      search();
    }
  });
  // The search row is left out while the admin has place search switched off.
  var searchButton = root.querySelector('[data-action="search"]');
  if (searchButton) {
    searchButton.addEventListener('click', search);
  }
  root.querySelector('[data-action="save"]').addEventListener('click', save);
  root.querySelector('[data-action="remove"]').addEventListener('click', remove);
  startMap();
})();
