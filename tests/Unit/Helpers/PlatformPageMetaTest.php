<?php

declare(strict_types=1);

test('a platform page gets a description, share tags and a canonical url', function () {
    $html = platform_page_meta('Privacy Policy', 'How data is handled.', 'https://example.test/en/privacy');

    expect($html)->toContain('<meta name="description" content="How data is handled." />')
        ->and($html)->toContain('property="og:title" content="Privacy Policy"')
        ->and($html)->toContain('property="og:url" content="https://example.test/en/privacy"')
        ->and($html)->toContain('property="og:image" content="https://example.test/assets/icon/android-chrome-512x512.png"')
        ->and($html)->toContain('name="twitter:card" content="summary"');
});

test('a page without a description prints no description tag', function () {
    $html = platform_page_meta('Contact', '', 'https://example.test/en/contact');

    expect($html)->not->toContain('name="description"')
        ->and($html)->toContain('property="og:title" content="Contact"');
});

test('search results can be kept out of the index', function () {
    $html = platform_page_meta('Discover', 'Find writing.', 'https://example.test/en/discover', 'noindex, follow');

    expect($html)->toContain('<meta name="robots" content="noindex, follow" />');
});
