/**
 * Builds a campaign-tagged link on the Traffic page as the owner types, so the
 * readers it brings show up under Campaigns. Nothing is sent to the server.
 */
(function () {
    'use strict';

    var builder = document.querySelector('[data-link-builder]');
    if (!builder) {
        return;
    }

    var fields = {
        url: builder.querySelector('[name="link_url"]'),
        utm_source: builder.querySelector('[name="link_source"]'),
        utm_medium: builder.querySelector('[name="link_medium"]'),
        utm_campaign: builder.querySelector('[name="link_campaign"]')
    };
    var result = builder.querySelector('[data-link-result]');
    var status = builder.querySelector('[data-link-status]');

    // Tags are matched as typed, so spaces and capitals would split one campaign into several.
    function tidy(value) {
        return value.trim().toLowerCase().replace(/\s+/g, '-');
    }

    function build() {
        var url;

        try {
            url = new URL(fields.url.value.trim());
        } catch (error) {
            result.value = '';
            return;
        }

        ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (key) {
            var value = tidy(fields[key].value);

            if (value === '') {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, value);
            }
        });

        result.value = url.toString();
        status.textContent = '';
    }

    Object.keys(fields).forEach(function (key) {
        fields[key].addEventListener('input', build);
    });

    builder.querySelector('[data-link-copy]').addEventListener('click', function () {
        if (result.value === '') {
            return;
        }

        result.select();

        if (navigator.clipboard) {
            navigator.clipboard.writeText(result.value).then(function () {
                status.textContent = builder.getAttribute('data-copied');
            }).catch(function (error) {
                console.debug('Copy failed:', error);
            });
        }
    });

    build();
})();
