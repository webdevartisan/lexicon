<?php

declare(strict_types=1);

/*
 * Traffic analytics. Settings an admin changes at runtime live in TrafficSettings.
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

    // A visitor with one view and less engaged time than this bounced.
    'bounce_seconds' => 10,

    'max_body_bytes' => 2048,

    // First-party visitor id, set only for readers who accepted analytics.
    // 13 months is the ceiling the CNIL gives for audience measurement cookies.
    'cookie' => [
        'name' => 'lx_vid',
        'lifetime_days' => 395,
    ],

    // DB-IP Lite country database (CC BY 4.0, credit "IP Geolocation by DB-IP").
    // Replaced monthly by traffic:update-geo. Without the file, countries stay empty.
    'geo' => [
        'path' => 'storage/geo/dbip-country-lite.mmdb',
        'download_url' => 'https://download.db-ip.com/free/dbip-country-lite-%s.mmdb.gz',
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

        '/^mail\.google\.com$/' => ['Gmail', 'email'],
        '/^com\.google\.android\.gm$/' => ['Gmail', 'email'],
        '/^outlook\.(live|office|office365)\.com$/' => ['Outlook', 'email'],
        '/^mail\.yahoo\.com$/' => ['Yahoo Mail', 'email'],
        '/^mail\.proton\.me$/' => ['Proton Mail', 'email'],

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
];
