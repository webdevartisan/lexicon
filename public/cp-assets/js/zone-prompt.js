(function () {
  const prompt = document.querySelector('[data-zone-prompt]');
  const device = Intl.DateTimeFormat().resolvedOptions().timeZone;
  if (!prompt || !device || !window.ZoneClock) return;

  const account = prompt.dataset.accountZone;
  const pair = account + '|' + device;
  const KEPT = 'lexicon:zone-kept';

  function kept() {
    try {
      return localStorage.getItem(KEPT);
    } catch (e) {
      return null;
    }
  }

  if (window.ZoneClock.sameClock(account, device) || kept() === pair) return;

  prompt.querySelectorAll('[data-zone-device]').forEach(function (slot) {
    slot.textContent = device.replace(/_/g, ' ');
  });
  prompt.querySelector('[data-zone-field]').value = device;
  prompt.hidden = false;

  // Remembered for this pair only, so moving again or changing the account zone asks afresh.
  prompt.querySelector('[data-zone-keep]').addEventListener('click', function () {
    try {
      localStorage.setItem(KEPT, pair);
    } catch (e) {}
    prompt.hidden = true;
  });
})();
