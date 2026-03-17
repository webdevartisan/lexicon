document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll("[data-provider]").forEach(item => {
        const provider = item.dataset.provider;

        /* ---------------------------------------------------------
         * FLATPICKR (DATE + OPTIONAL TIME)
         * --------------------------------------------------------- */
        if (provider === "flatpickr") {
            const d = item.dataset;
            const opts = {};

            // Base date format
            if (d.dateFormat) opts.dateFormat = d.dateFormat;

            // Minimum date (e.g., "today")
            if (d.minDate) opts.minDate = d.minDate;

            /* -----------------------------------------------------
             * TIME SUPPORT
             * ----------------------------------------------------- */
            if (item.hasAttribute("data-enable-time")) {
                opts.enableTime = true;
                opts.dateFormat = `${d.dateFormat} H:i`;

                // Disable past dates, allow today, block past times later
                if (d.minDate === "today") {
                    opts.enable = [
                        function(date) {
                            const now = new Date();

                            // Check if the date is today
                            const isSameDay =
                                date.getFullYear() === now.getFullYear() &&
                                date.getMonth() === now.getMonth() &&
                                date.getDate() === now.getDate();

                            if (isSameDay) {
                                return true; // allow today
                            }

                            // Normalize both dates to midnight
                            const day = new Date(date.getFullYear(), date.getMonth(), date.getDate());
                            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

                            return day >= today; // allow today & future
                        }
                    ];
                }

                /* -------------------------------------------------
                 * Restrict past times when selecting today
                 * ------------------------------------------------- */
                opts.onChange = function(selectedDates, dateStr, instance) {
                    const now = new Date();
                    const selected = selectedDates[0];
                    if (!selected) return;

                    const isToday =
                        selected.getFullYear() === now.getFullYear() &&
                        selected.getMonth() === now.getMonth() &&
                        selected.getDate() === now.getDate();

                    if (isToday) {
                        instance.set("minTime", `${now.getHours()}:${now.getMinutes()}`);
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

            flatpickr(item, opts);
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
    document.getElementById("timezone").value =
    Intl.DateTimeFormat().resolvedOptions().timeZone;
});
