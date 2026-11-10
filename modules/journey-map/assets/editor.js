/**
 * The Places section in the post editor: the post's places in order, one chosen at a time to search for,
 * pin on the map or edit, then all saved together.
 *
 * The section lives inside the post form, so Enter in its fields must never submit the post.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-journey-map-editor]');
  if (!root) {
    return;
  }

  var text = JSON.parse(root.querySelector('[data-journey-map-text]').textContent);
  var places = JSON.parse(root.querySelector('[data-journey-map-places]').textContent) || [];
  var max = Number(root.dataset.max) || 1;
  var list = root.querySelector('[data-places]');
  var results = root.querySelector('[data-results]');
  var status = root.querySelector('[data-status]');
  var addButton = root.querySelector('[data-action="add"]');
  var placeFields = ['place_name', 'lat', 'lng', 'precision'];
  var chosen = 0;
  var map = null;
  var markers = [];

  function blank() {
    return { place_name: '', lat: '', lng: '', precision: 'exact' };
  }

  function field(name) {
    return root.querySelector('[data-field="' + name + '"]');
  }

  /** Fill {name} gaps in a translated line. */
  function words(template, values) {
    return template.replace(/\{(\w+)\}/g, function (gap, name) {
      return name in values ? values[name] : gap;
    });
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

  function point(place) {
    var lat = parseFloat(place.lat);
    var lng = parseFloat(place.lng);

    return isNaN(lat) || isNaN(lng) ? null : [lng, lat];
  }

  function isBlank(place) {
    return place.place_name.trim() === '' && point(place) === null;
  }

  function iconButton(label, symbol, action) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'journey-map-place-tool';
    button.textContent = symbol;
    button.setAttribute('aria-label', label);
    button.title = label;
    button.addEventListener('click', action);

    return button;
  }

  function renderList() {
    list.replaceChildren();
    places.forEach(function (place, index) {
      var item = document.createElement('li');
      item.className = 'journey-map-place' + (index === chosen ? ' is-chosen' : '');

      var pick = document.createElement('button');
      pick.type = 'button';
      pick.className = 'journey-map-place-pick';
      pick.textContent = (index + 1) + '. ' + (place.place_name.trim() || words(text.placeItem, { number: index + 1 }));
      if (index === chosen) {
        pick.setAttribute('aria-current', 'true');
      }
      pick.addEventListener('click', function () { choose(index); });
      item.appendChild(pick);

      if (index > 0) {
        item.appendChild(iconButton(text.moveUp, '↑', function () { move(index, -1); }));
      }
      if (index < places.length - 1) {
        item.appendChild(iconButton(text.moveDown, '↓', function () { move(index, 1); }));
      }
      if (places.length > 1) {
        item.appendChild(iconButton(text.removePlace, '×', function () { drop(index); }));
      }
      list.appendChild(item);
    });
    addButton.disabled = places.length >= max;
  }

  function fillFields() {
    placeFields.forEach(function (name) { field(name).value = places[chosen][name]; });
  }

  function render() {
    renderList();
    fillFields();
    drawMarkers();
  }

  function choose(index) {
    chosen = index;
    render();
    var spot = point(places[chosen]);
    if (map && spot) {
      map.easeTo({ center: spot, zoom: Math.max(map.getZoom(), 8) });
    }
  }

  function move(index, step) {
    var moved = places.splice(index, 1)[0];
    places.splice(index + step, 0, moved);
    chosen = index + step;
    render();
  }

  function drop(index) {
    places.splice(index, 1);
    chosen = Math.min(chosen, places.length - 1);
    render();
  }

  function add() {
    if (places.length >= max) {
      return;
    }
    places.push(blank());
    chosen = places.length - 1;
    render();
    (field('query') || field('place_name')).focus();
  }

  /** One marker per placed place; only the chosen one is in the accent colour and can be dragged. */
  function drawMarkers() {
    if (!map) {
      return;
    }
    markers.forEach(function (marker) { marker.remove(); });
    markers = [];

    places.forEach(function (place, index) {
      var spot = point(place);
      if (!spot) {
        return;
      }
      var isChosen = index === chosen;
      var marker = new window.maplibregl.Marker({
        color: isChosen ? window.JourneyMap.token(root, '--module-accent', '#2563eb') : '#94a3b8',
        draggable: isChosen
      }).setLngLat(spot).addTo(map);
      if (isChosen) {
        marker.on('dragend', function () {
          var moved = marker.getLngLat();
          writePoint(moved.lat, moved.lng);
        });
      } else {
        marker.getElement().addEventListener('click', function (event) {
          event.stopPropagation();
          choose(index);
        });
      }
      markers.push(marker);
    });
  }

  function writePoint(lat, lng) {
    places[chosen].lat = lat.toFixed(6);
    places[chosen].lng = lng.toFixed(6);
    field('lat').value = places[chosen].lat;
    field('lng').value = places[chosen].lng;
  }

  function setPoint(lat, lng) {
    writePoint(lat, lng);
    drawMarkers();
    if (map) {
      map.jumpTo({ center: [lng, lat], zoom: Math.max(map.getZoom(), 8) });
    }
  }

  function pick(place) {
    places[chosen].place_name = place.name.split(',')[0].trim();
    field('place_name').value = places[chosen].place_name;
    setPoint(place.lat, place.lng);
    renderList();
    results.hidden = true;
  }

  function showResults(found) {
    results.replaceChildren();
    results.hidden = found.length === 0;
    if (found.length === 0) {
      say(text.noResults);
      return;
    }

    found.forEach(function (place) {
      var item = document.createElement('li');
      var button = document.createElement('button');
      button.type = 'button';
      button.textContent = place.name;
      button.addEventListener('click', function () { pick(place); });
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

  /** Each wrong place by its number in the list, or the one message about the list as a whole. */
  function errorText(errors, sent) {
    if (!errors) {
      return text.saveFailed;
    }
    if (typeof errors.stops === 'string') {
      return errors.stops;
    }

    return Object.keys(errors).map(function (index) {
      var messages = Object.values(errors[index]).join(' ');

      return words(text.errorAt, { number: sent[Number(index)] + 1, message: messages });
    }).join(' ');
  }

  /** The first pinned place still without a name, or -1. */
  function unnamed() {
    for (var i = 0; i < places.length; i++) {
      if (point(places[i]) && places[i].place_name.trim() === '') {
        return i;
      }
    }

    return -1;
  }

  function save() {
    var missing = unnamed();
    if (missing >= 0) {
      choose(missing);
      field('place_name').focus();
      say(words(text.nameNeeded, { number: missing + 1 }), true);
      return;
    }

    var body = new URLSearchParams();
    var sent = [];
    body.append('stop_number', field('stop_number').value.trim());
    places.forEach(function (place, index) {
      // An empty row the writer added and never filled in is left behind rather than refused.
      if (isBlank(place) && places.length > 1) {
        return;
      }
      placeFields.forEach(function (name) {
        body.append('stops[' + sent.length + '][' + name + ']', String(place[name]).trim());
      });
      sent.push(index);
    });

    send(root.dataset.saveUrl, { method: 'POST', body: body })
      .then(function (result) {
        if (!result.ok) {
          say(errorText(result.body.errors, sent), true);
          return;
        }
        places = sent.map(function (index) { return places[index]; });
        chosen = Math.min(chosen, places.length - 1);
        render();
        say(result.body.message);
      })
      .catch(function () { say(text.saveFailed, true); });
  }

  function removeAll() {
    send(root.dataset.removeUrl, { method: 'POST', body: new URLSearchParams() })
      .then(function (result) {
        say(result.ok ? result.body.message : text.saveFailed, !result.ok);
        if (result.ok) {
          places = [blank()];
          chosen = 0;
          field('stop_number').value = '';
          render();
        }
      })
      .catch(function () { say(text.saveFailed, true); });
  }

  /** Open on every placed place, or on the world when there are none yet. */
  function startMap() {
    var box = root.querySelector('[data-map]');
    if (!box || !window.JourneyMap) {
      return;
    }

    var spots = places.map(point).filter(Boolean);
    map = window.JourneyMap.create(box, JSON.parse(box.dataset.map), {
      center: spots[0] || [0, 20],
      zoom: spots.length > 0 ? 8 : 1
    });
    if (!map) {
      return;
    }

    map.on('click', function (event) { setPoint(event.lngLat.lat, event.lngLat.lng); });
    if (spots.length > 1) {
      var bounds = new window.maplibregl.LngLatBounds(spots[0], spots[0]);
      spots.forEach(function (spot) { bounds.extend(spot); });
      map.fitBounds(bounds, { padding: 40, maxZoom: 9, animate: false });
    }
    var section = root.closest('details');
    if (section) {
      // A map drawn inside a closed section has no size until the section opens.
      section.addEventListener('toggle', function () {
        if (section.open) {
          map.resize();
        }
      });
    }
  }

  placeFields.forEach(function (name) {
    field(name).addEventListener('input', function () {
      places[chosen][name] = field(name).value;
      if (name === 'place_name') {
        renderList();
      } else {
        drawMarkers();
      }
    });
  });

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
  addButton.addEventListener('click', add);
  root.querySelector('[data-action="save"]').addEventListener('click', save);
  root.querySelector('[data-action="remove"]').addEventListener('click', removeAll);

  if (places.length === 0) {
    places.push(blank());
  }
  startMap();
  render();
})();
