/**
 * Replies: clear the badge once the reader has actually seen the page.
 *
 * The mutation is a POST either way. This only saves the reader a click; with
 * scripting off the same form is right there and does the same thing, which is
 * why there is one code path here and not a scripted branch plus a fallback.
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-reader-markread]');
    if (!form) {
        return;
    }

    var token = form.querySelector('input[name="_token"]');
    var ids = Array.prototype.map.call(
        form.querySelectorAll('input[name="ids[]"]'),
        function (input) { return input.value; }
    );

    if (!token || ids.length === 0) {
        return;
    }

    // Post when there is something to settle: unread rows on this page, or a
    // badge still showing. The second case is the reader whose only unread
    // replies point at comments that have since gone -- they never appear in
    // the list, so without this their badge would have no way to clear.
    // Everything read and no badge means nothing to do, and no write.
    var hasUnreadRows = document.querySelector('.lx-reader-row.is-unread');
    var hasBadge = document.querySelector('[data-reader-badge], [data-reader-dot]');
    if (!hasUnreadRows && !hasBadge) {
        return;
    }

    var body = new URLSearchParams();
    body.append('_token', token.value);
    ids.forEach(function (id) { body.append('ids[]', id); });

    fetch(form.getAttribute('action'), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token.value
        },
        body: body.toString()
    })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (payload) {
            if (!payload || !payload.data) {
                return;
            }

            // The list keeps its "unread" marks: the reader arrived to see what
            // was new, and repainting it out from under them loses that. Only
            // the badge, which answers "is there anything new", moves.
            var count = Number(payload.data.unread) || 0;
            document.querySelectorAll('[data-reader-badge]').forEach(function (badge) {
                if (count === 0) {
                    badge.remove();

                    return;
                }

                badge.textContent = count > 99 ? '99+' : String(count);
            });

            // The dot on the avatar says "something is new" and nothing more,
            // so it only ever appears or goes away.
            if (count === 0) {
                document.querySelectorAll('[data-reader-dot]').forEach(function (dot) {
                    dot.remove();
                });
            }
        })
        .catch(function () {
            // The visible button is still there. Failing quietly beats an error
            // about something the reader never asked for.
        });
}());
