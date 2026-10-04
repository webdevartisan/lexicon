<?php

declare(strict_types=1);

use App\Services\Traffic\ReferrerClassifier;

function referrers(): ReferrerClassifier
{
    $config = require ROOT_PATH.'/config/traffic.php';

    return new ReferrerClassifier($config['sources'], $config['spam_referrers'], $config['email_mediums']);
}

test('known sources get their name and channel, and only the host is kept', function (string $url, string $channel, string $source) {
    $result = referrers()->classify($url, 'lexicon.test', null);

    expect($result['channel'])->toBe($channel)
        ->and($result['source'])->toBe($source)
        ->and($result['host'])->not->toContain('/');
})->with([
    ['https://www.google.co.uk/search?q=private+words', 'search', 'Google'],
    ['https://duckduckgo.com/?q=x', 'search', 'DuckDuckGo'],
    ['https://t.co/abc123', 'social', 'X'],
    ['https://m.facebook.com/story.php?id=1', 'social', 'Facebook'],
    ['https://l.instagram.com/?u=x', 'social', 'Instagram'],
    ['https://news.ycombinator.com/item?id=1', 'social', 'Hacker News'],
    ['https://mail.google.com/mail/u/0/#inbox/abc', 'email', 'Gmail'],
    ['android-app://com.google.android.gm/', 'email', 'Gmail'],
    ['https://some-blog.example/2026/a-post', 'referral', 'some-blog.example'],
]);

test('our own pages are internal, not a source', function () {
    expect(referrers()->classify('https://www.lexicon.test/en/blog/demo', 'lexicon.test', null))
        ->toBe(['channel' => 'internal', 'host' => null, 'source' => null]);
});

test('no referrer is direct, unless the campaign says it came by mail', function () {
    expect(referrers()->classify('', 'lexicon.test', null)['channel'])->toBe('direct')
        ->and(referrers()->classify('', 'lexicon.test', 'Newsletter')['channel'])->toBe('email');
});

test('referrer spam is dropped, subdomains included', function () {
    expect(referrers()->classify('https://semalt.com/', 'lexicon.test', null))->toBeNull()
        ->and(referrers()->classify('https://best.semalt.com/', 'lexicon.test', null))->toBeNull();
});

test('non-web schemes say nothing about where the reader came from', function () {
    expect(referrers()->classify('file:///C:/secret.html', 'lexicon.test', null)['channel'])->toBe('direct');
});
