/**
 * Tells the server which zone this device is in when the session has not
 * heard it yet. Accounts on Automatic read dates on that clock from the next
 * page on.
 */
(function () {
  'use strict';

  var tag = document.querySelector('meta[name="device-zone"]');
  var zone = Intl.DateTimeFormat().resolvedOptions().timeZone;
  if (!tag || !zone || zone === tag.content) return;

  fetch(tag.dataset.url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-CSRF-TOKEN': tag.dataset.token, Accept: 'application/json' },
    body: new URLSearchParams({ zone: zone })
  }).catch(function () {});
})();
