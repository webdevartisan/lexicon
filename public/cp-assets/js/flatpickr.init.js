document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-provider]").forEach(item => {
        const provider = item.dataset.provider;

        /* ---------------------------------------------------------
         * FLATPICKR (DATE + OPTIONAL TIME)
         * --------------------------------------------------------- */
        if (provider === "flatpickr") {
            const d = item.dataset;
            const opts = {};

            const now = () => (d.timezone && window.ZoneClock ? window.ZoneClock.now(d.timezone) : new Date());

            // Base date format
            if (d.dateFormat) opts.dateFormat = d.dateFormat;

            // Minimum date (e.g., "today"). A date saved before it, like an old
            // draft's, still shows instead of the field coming up empty.
            if (d.minDate) {
                opts.minDate = d.minDate === "today" ? new Date(now().setHours(0, 0, 0, 0)) : d.minDate;
                opts.allowInvalidPreload = true;
            }

            /* -----------------------------------------------------
             * TIME SUPPORT
             * ----------------------------------------------------- */
            if (item.hasAttribute("data-enable-time")) {
                opts.enableTime = true;
                opts.locale = { amPM: ["am", "pm"] };

                // Disable past dates, allow today, block past times later
                if (d.minDate === "today") {
                    opts.enable = [
                        function(date) {
                            const current = now();

                            // Check if the date is today
                            const isSameDay =
                                date.getFullYear() === current.getFullYear() &&
                                date.getMonth() === current.getMonth() &&
                                date.getDate() === current.getDate();

                            if (isSameDay) {
                                return true; // allow today
                            }

                            // Normalize both dates to midnight
                            const day = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                            const today = new Date(current.getFullYear(), current.getMonth(), current.getDate());

                            return day >= today; // allow today & future
                        }
                    ];
                }

                /* -------------------------------------------------
                 * Restrict past times when selecting today
                 * ------------------------------------------------- */
                opts.onChange = function(selectedDates, dateStr, instance) {
                    const current = now();
                    const selected = selectedDates[0];
                    if (!selected) return;

                    const isToday =
                        selected.getFullYear() === current.getFullYear() &&
                        selected.getMonth() === current.getMonth() &&
                        selected.getDate() === current.getDate();

                    if (isToday) {
                        instance.set("minTime", `${current.getHours()}:${current.getMinutes()}`);
                    } else {
                        instance.set("minTime", null);
                    }
                };
            }

            /* -----------------------------------------------------
             * OTHER OPTIONS
             * ----------------------------------------------------- */
            if (d.altFormat) {
                opts.altInput = true;
                opts.altFormat = d.altFormat;
            }
            if (d.maxDate) opts.maxDate = d.maxDate;
            if (d.defaultDate) opts.defaultDate = d.defaultDate;
            if (d.multipleDate) opts.mode = "multiple";
            if (d.rangeDate) opts.mode = "range";
            if (d.inlineDate) {
                opts.inline = true;
                opts.defaultDate = d.defaultDate;
            }
            if (d.disableDate) opts.disable = d.disableDate.split(",");
            if (d.weekNumber) opts.weekNumbers = true;

            const picker = flatpickr(item, opts);

            // The readable copy is the box people see and click, so the label belongs to it.
            if (picker.altInput && item.id) {
                picker.altInput.id = `${item.id}-shown`;
                document.querySelectorAll(`label[for="${item.id}"]`).forEach(label => {
                    label.htmlFor = picker.altInput.id;
                });
            }
        }

        /* ---------------------------------------------------------
         * TIME-ONLY PICKER
         * --------------------------------------------------------- */
        if (provider === "timepickr") {
            const d = item.dataset;
            const opts = {
                enableTime: true,
                noCalendar: true,
                dateFormat: "H:i"
            };

            if (d.timeHrs) opts.time_24hr = true;
            if (d.minTime) opts.minTime = d.minTime;
            if (d.maxTime) opts.maxTime = d.maxTime;
            if (d.defaultTime) opts.defaultDate = d.defaultTime;
            if (d.timeInline) opts.inline = true;

            flatpickr(item, opts);
        }
    });
});
