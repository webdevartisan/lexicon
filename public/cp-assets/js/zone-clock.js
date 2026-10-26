(function () {
  /**
   * What a clock in `zone` reads at `instant`. A Date cannot hold a zone, so
   * the time sits in its local fields.
   *
   * @param {string} zone IANA zone, e.g. "Asia/Nicosia"
   * @param {Date} instant
   * @returns {Date}
   */
  function reading(zone, instant) {
    let parts;
    try {
      parts = new Intl.DateTimeFormat('en-US', {
        timeZone: zone,
        hourCycle: 'h23',
        year: 'numeric', month: 'numeric', day: 'numeric',
        hour: 'numeric', minute: 'numeric', second: 'numeric',
      }).formatToParts(instant);
    } catch (e) {
      return new Date(instant.getTime());
    }

    const field = {};
    parts.forEach(function (part) { field[part.type] = Number(part.value); });

    return new Date(field.year, field.month - 1, field.day, field.hour, field.minute, field.second);
  }

  /**
   * The wall clock time in `zone` right now.
   *
   * @param {string} zone IANA zone, e.g. "Asia/Nicosia"
   * @returns {Date}
   */
  function now(zone) {
    return reading(zone, new Date());
  }

  /**
   * Whether clocks in the two zones show the same time right now.
   *
   * @param {string} a IANA zone
   * @param {string} b IANA zone
   * @returns {boolean}
   */
  function sameClock(a, b) {
    const instant = new Date();

    return reading(a, instant).getTime() === reading(b, instant).getTime();
  }

  window.ZoneClock = { now: now, sameClock: sameClock };
})();
