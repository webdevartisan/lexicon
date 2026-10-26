(function () {
  /**
   * The wall clock time in `zone` right now. A Date cannot hold a zone, so the
   * time sits in its local fields.
   *
   * @param {string} zone IANA zone, e.g. "Asia/Nicosia"
   * @returns {Date}
   */
  function now(zone) {
    let parts;
    try {
      parts = new Intl.DateTimeFormat('en-US', {
        timeZone: zone,
        hourCycle: 'h23',
        year: 'numeric', month: 'numeric', day: 'numeric',
        hour: 'numeric', minute: 'numeric', second: 'numeric',
      }).formatToParts(new Date());
    } catch (e) {
      return new Date();
    }

    const field = {};
    parts.forEach(function (part) { field[part.type] = Number(part.value); });

    return new Date(field.year, field.month - 1, field.day, field.hour, field.minute, field.second);
  }

  window.ZoneClock = { now: now };
})();
