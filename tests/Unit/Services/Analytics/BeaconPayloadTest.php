<?php

declare(strict_types=1);

use App\Services\Analytics\BeaconPayload;

const VIEW_ID = '0123456789abcdef0123456789abcdef';

test('a well formed view is read', function () {
    $payload = BeaconPayload::view(json_encode([
        'v' => VIEW_ID, 'p' => '/en/blog/demo/hello', 'r' => 'https://www.google.com/',
        'us' => 'newsletter', 'um' => 'email', 'uc' => 'spring',
    ]));

    expect($payload)->not->toBeNull()
        ->and($payload->path)->toBe('/en/blog/demo/hello')
        ->and($payload->utmMedium)->toBe('email')
        ->and(strlen($payload->viewIdBytes()))->toBe(16);
});

test('a dashed UUID is accepted as a view id', function () {
    $payload = BeaconPayload::view(json_encode(['v' => '01234567-89ab-cdef-0123-456789abcdef', 'p' => '/en/blog/demo']));

    expect($payload?->viewId)->toBe(VIEW_ID);
});

test('ids in the body are never read, only the path', function () {
    $payload = BeaconPayload::view(json_encode(['v' => VIEW_ID, 'p' => '/en/blog/demo', 'blog_id' => 99, 'post_id' => 7]));

    expect(get_object_vars($payload))->not->toHaveKeys(['blog_id', 'post_id']);
});

test('malformed views are refused', function (string $body) {
    expect(BeaconPayload::view($body))->toBeNull();
})->with([
    'empty' => [''],
    'not json' => ['hello'],
    'a list' => ['["a","b"]'],
    'bad view id' => ['{"v":"xyz","p":"/en/blog/demo"}'],
    'missing path' => ['{"v":"'.VIEW_ID.'"}'],
    'relative path' => ['{"v":"'.VIEW_ID.'","p":"blog/demo"}'],
    'path too long' => ['{"v":"'.VIEW_ID.'","p":"/'.str_repeat('a', 300).'"}'],
    'nested too deep' => ['{"v":"'.VIEW_ID.'","p":"/x","r":{"a":{"b":1}}}'],
]);

test('free text is trimmed to its limit', function () {
    $payload = BeaconPayload::view(json_encode(['v' => VIEW_ID, 'p' => '/x', 'uc' => str_repeat('c', 500)]));

    expect(mb_strlen((string) $payload->utmCampaign))->toBe(100);
});

test('a leave ping is clamped to sane values', function () {
    $payload = BeaconPayload::engagement(json_encode(['v' => VIEW_ID, 's' => 99999, 'd' => 250]), 3600);

    expect($payload->seconds)->toBe(3600)
        ->and($payload->scrollDepth)->toBe(100);
});

test('a leave ping with text numbers is refused', function () {
    expect(BeaconPayload::engagement('{"v":"'.VIEW_ID.'","s":"30","d":10}', 3600))->toBeNull();
});

test('a view can say it landed on a missing page and what was searched', function () {
    $payload = BeaconPayload::view(json_encode(['v' => VIEW_ID, 'p' => '/en/discover', 'nf' => 1, 'q' => ' gardens ']));

    expect($payload?->notFound)->toBeTrue()
        ->and($payload?->searchTerm)->toBe('gardens');
});

test('the not-found flag is only taken as a plain 1', function () {
    expect(BeaconPayload::view(json_encode(['v' => VIEW_ID, 'p' => '/en/discover', 'nf' => 'yes']))?->notFound)->toBeFalse();
});

test('an event needs a view and a plain name, and leaves its props to the registry', function (array $body, bool $accepted) {
    expect(BeaconPayload::event(json_encode($body)) !== null)->toBe($accepted);
})->with([
    'a share' => [['v' => VIEW_ID, 'n' => 'share', 'p' => ['network' => 'x']], true],
    'no props' => [['v' => VIEW_ID, 'n' => 'outbound'], true],
    'a name with other characters' => [['v' => VIEW_ID, 'n' => 'Share!'], false],
    'props that are not fields' => [['v' => VIEW_ID, 'n' => 'share', 'p' => 'x'], false],
    'no view' => [['n' => 'share', 'p' => ['network' => 'x']], false],
]);

test('a view says how many results a search found and whether a related link led to it', function () {
    $payload = BeaconPayload::view(json_encode(['v' => VIEW_ID, 'p' => '/en/discover', 'q' => 'gardens', 'sr' => 0, 'via' => 'related']));
    $odd = BeaconPayload::view(json_encode(['v' => VIEW_ID, 'p' => '/en/discover', 'sr' => -1, 'via' => 'somewhere']));

    expect([$payload?->searchResults, $payload?->via])->toBe([0, 'related'])
        ->and([$odd?->searchResults, $odd?->via])->toBe([null, null]);
});

test('page speed comes with the leave ping, and a value out of range is dropped on its own', function () {
    $payload = BeaconPayload::engagement(json_encode([
        'v' => VIEW_ID, 's' => 30, 'd' => 50, 'lcp' => 2100, 'inp' => 90000, 'cls' => 0.123456, 'ttfb' => '300',
    ]), 3600);

    expect($payload?->vitals)->toBe(['lcp_ms' => 2100, 'cls' => 0.1235])
        ->and($payload?->seconds)->toBe(30);
});
