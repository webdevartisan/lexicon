<?php

declare(strict_types=1);

/*
 * Analytics behind Insights. Settings an admin changes at runtime live in AnalyticsSettings.
 */
return [
    // A reader reloading or bouncing back to the same page inside this window counts once.
    'dedupe_minutes' => 30,

    // When a reader's id changes mid-visit, their views this recent move to the new id.
    'visit_minutes' => 30,

    // The leave ping can only update a view this recent.
    'engagement_window_minutes' => 360,

    // Longer than this reads as a tab left open, not reading.
    'max_engaged_seconds' => 3600,

    // A view with at least this much engaged time is a read.
    'read_seconds' => 30,

    // A visit with this much engaged time is engaged even with one page view (GA4 uses 10 seconds).
    'engaged_visit_seconds' => 10,

    'max_body_bytes' => 2048,

    // First-party visitor id, set only for readers who accepted analytics.
    // 13 months is the ceiling the CNIL gives for audience measurement cookies.
    'cookie' => [
        'name' => 'lx_vid',
        'lifetime_days' => 395,
    ],

    // DB-IP Lite country database (CC BY 4.0, credit "IP Geolocation by DB-IP").
    // Replaced monthly by analytics:update-geo. Without the file, countries stay empty.
    'geo' => [
        'path' => 'storage/geo/dbip-country-lite.mmdb',
        'download_url' => 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz',
    ],

    // DB-IP Lite ASN database, same licence and schedule. Views from these networks are
    // servers, not readers. Lower-case substrings of the network's registered name.
    // Akamai, Cloudflare, Fastly and Google stay off the list: iCloud Private Relay,
    // WARP and Google Fi send real people out through them.
    'networks' => [
        'path' => 'storage/geo/dbip-asn-lite.mmdb',
        'download_url' => 'https://download.db-ip.com/free/dbip-asn-lite-%s.mmdb.gz',
        'hosting' => [
            'amazon', 'digitalocean', 'ovh', 'hetzner', 'linode', 'vultr', 'choopa', 'contabo', 'alibaba',
            'tencent', 'oracle', 'scaleway', 'leaseweb', 'm247', 'datacamp', 'hostinger', 'ionos', 'kamatera',
            'upcloud', 'netcup', 'hostroyale', 'colocrossing', 'psychz', 'quadranet', 'servers.com', 'zenlayer',
            'gcore',
        ],
    ],

    // A visitor with this many views in a day and not one leave ping is a script.
    'script_min_views' => 20,

    // A click on a same-site link to one of these is a download.
    'download_extensions' => [
        'pdf', 'epub', 'mobi', 'zip', 'gz', 'rar', '7z', 'csv', 'xlsx', 'xls', 'docx', 'doc', 'pptx', 'odt',
        'mp3', 'm4a', 'wav', 'flac', 'mp4', 'mov', 'webm', 'dmg', 'exe', 'apk', 'txt', 'json',
    ],

    // Crawlers get served normally but never counted. Case-insensitive substrings.
    'bot_patterns' => [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'apis-google', 'feedfetcher',
        'google-inspectiontool', 'lighthouse', 'pagespeed', 'ptst', 'gtmetrix', 'pingdom',
        'uptime', 'monitor', 'headless', 'phantomjs', 'puppeteer', 'playwright', 'selenium',
        'python-requests', 'python-urllib', 'curl/', 'wget/', 'go-http-client', 'java/',
        'okhttp', 'axios', 'node-fetch', 'httpclient', 'facebookexternalhit', 'ia_archiver',
        'preview', 'feedly', 'scrapy', 'ahrefs', 'semrush', 'mj12', 'dataprovider',
    ],

    // Spam referrers. A view from one of these is dropped.
    'spam_referrers' => [
        'semalt.com', 'buttons-for-website.com', 'buttons-for-your-website.com', 'darodar.com',
        'best-seo-offer.com', 'best-seo-solution.com', 'free-social-buttons.com', 'get-free-traffic-now.com',
        'hulfingtonpost.com', 'ilovevitaly.com', 'priceg.com', 'savetubevideo.com', 'screentoolkit.com',
    ],

    // Known referrer hosts, matched after www., m., l. and lm. are stripped.
    // Pattern => [display name, channel]. First match wins.
    'sources' => [
        // Mail first: the Google search pattern would also take mail.google.com.
        '/^mail\.google\.com$/' => ['Gmail', 'email'],
        '/^com\.google\.android\.gm$/' => ['Gmail', 'email'],
        '/^outlook\.(live|office|office365)\.com$/' => ['Outlook', 'email'],
        '/^mail\.yahoo\.com$/' => ['Yahoo Mail', 'email'],
        '/^mail\.proton\.me$/' => ['Proton Mail', 'email'],

        '/(^|\.)google\.[a-z.]+$/' => ['Google', 'search'],
        '/(^|\.)bing\.com$/' => ['Bing', 'search'],
        '/(^|\.)duckduckgo\.com$/' => ['DuckDuckGo', 'search'],
        '/^search\.yahoo\.com$/' => ['Yahoo', 'search'],
        '/(^|\.)yandex\.[a-z.]+$/' => ['Yandex', 'search'],
        '/(^|\.)baidu\.com$/' => ['Baidu', 'search'],
        '/(^|\.)ecosia\.org$/' => ['Ecosia', 'search'],
        '/^search\.brave\.com$/' => ['Brave Search', 'search'],
        '/(^|\.)startpage\.com$/' => ['Startpage', 'search'],
        '/(^|\.)qwant\.com$/' => ['Qwant', 'search'],
        '/(^|\.)kagi\.com$/' => ['Kagi', 'search'],
        '/^com\.google\.android\.googlequicksearchbox$/' => ['Google', 'search'],

        '/(^|\.)facebook\.com$/' => ['Facebook', 'social'],
        '/^fb\.me$/' => ['Facebook', 'social'],
        '/(^|\.)instagram\.com$/' => ['Instagram', 'social'],
        '/^(t\.co|twitter\.com|x\.com)$/' => ['X', 'social'],
        '/^(linkedin\.com|lnkd\.in)$/' => ['LinkedIn', 'social'],
        '/(^|\.)reddit\.com$/' => ['Reddit', 'social'],
        '/^news\.ycombinator\.com$/' => ['Hacker News', 'social'],
        '/(^|\.)pinterest\.[a-z.]+$/' => ['Pinterest', 'social'],
        '/^(youtube\.com|youtu\.be)$/' => ['YouTube', 'social'],
        '/^threads\.(net|com)$/' => ['Threads', 'social'],
        '/^bsky\.app$/' => ['Bluesky', 'social'],
        '/(^|\.)mastodon\.[a-z.]+$/' => ['Mastodon', 'social'],
        '/^tiktok\.com$/' => ['TikTok', 'social'],
        '/^(t\.me|web\.telegram\.org)$/' => ['Telegram', 'social'],
        '/^(web\.)?whatsapp\.com$/' => ['WhatsApp', 'social'],
        '/^discord\.com$/' => ['Discord', 'social'],
    ],

    // With no referrer at all, these utm_medium values still mean the reader came from a mail.
    'email_mediums' => ['email', 'e-mail', 'newsletter', 'mail'],

    /*
     * Every event that can be stored. Anything not listed, and any prop not listed
     * under its event, is refused. EventRegistry reads this.
     *
     * - source: beacon (sent by the page script) or server (written by a controller)
     * - props: name => [type, ...] with string => max length, int => [min, max], enum => allowed values
     * - needs_view: a beacon event only counts on a page view counted in the engagement window
     * - once_per_visit: switching it on again in the same visit, on the same post, counts once
     * - kept: rolled into analytics_daily_events, so it outlives the raw events
     * - breakdowns: the daily rows kept besides the total; post, channel, source and the
     *   utm fields come from the event's columns, the rest from its props
     */
    'events' => [
        'page_view' => [
            'source' => 'beacon',
            'props' => [
                'q' => ['string', 100],
                'search_results' => ['int', 0, 100000],
                'via' => ['enum', ['related']],
            ],
            'kept' => false,
        ],
        'like' => ['source' => 'server', 'props' => [], 'once_per_visit' => true, 'kept' => true, 'breakdowns' => ['post', 'channel', 'source', 'utm_campaign']],
        'dislike' => ['source' => 'server', 'props' => [], 'once_per_visit' => true, 'kept' => true, 'breakdowns' => ['post', 'channel', 'source', 'utm_campaign']],
        'save' => ['source' => 'server', 'props' => [], 'once_per_visit' => true, 'kept' => true, 'breakdowns' => ['post', 'channel', 'source', 'utm_campaign']],
        'comment' => ['source' => 'server', 'props' => [], 'kept' => true, 'breakdowns' => ['post', 'channel', 'source', 'utm_campaign']],
        'subscribe' => ['source' => 'server', 'props' => [], 'kept' => true, 'breakdowns' => ['channel', 'source', 'utm_campaign']],
        'share' => [
            'source' => 'beacon',
            'props' => ['network' => ['enum', ['copy', 'x', 'facebook', 'linkedin']]],
            'needs_view' => true,
            'kept' => true,
            'breakdowns' => ['network', 'post', 'channel', 'source'],
        ],
        'signup' => [
            'source' => 'server',
            'props' => ['came_from' => ['string', 60]],
            'kept' => true,
            'breakdowns' => ['channel', 'source', 'came_from', 'utm_source', 'utm_medium', 'utm_campaign'],
        ],
        'outbound' => ['source' => 'beacon', 'props' => ['host' => ['string', 191]], 'needs_view' => true, 'kept' => true, 'breakdowns' => ['host', 'post']],
        'download' => ['source' => 'beacon', 'props' => ['file' => ['string', 191]], 'needs_view' => true, 'kept' => true, 'breakdowns' => ['file', 'post']],
        // Paths and hosts here come from strangers, so these stay raw and go with them.
        'not_found' => ['source' => 'beacon', 'props' => ['referrer_host' => ['string', 100]], 'kept' => false],
        'server_error' => ['source' => 'server', 'props' => ['status' => ['int', 500, 599]], 'kept' => false],
    ],
];
