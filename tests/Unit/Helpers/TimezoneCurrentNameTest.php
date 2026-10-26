<?php

declare(strict_types=1);

use App\Helpers\TimezoneHelper;

test('a zone PHP lists comes back as it is', function (string $zone) {
    expect(TimezoneHelper::currentName($zone))->toBe($zone);
})->with(['America/New_York', 'Asia/Nicosia', 'UTC']);

test('an older name a browser still reports gets the name used today', function (string $old, string $current) {
    expect(TimezoneHelper::currentName($old))->toBe($current);
})->with([
    'India' => ['Asia/Calcutta', 'Asia/Kolkata'],
    'Ukraine' => ['Europe/Kiev', 'Europe/Kyiv'],
    'Vietnam' => ['Asia/Saigon', 'Asia/Ho_Chi_Minh'],
    'Argentina' => ['America/Buenos_Aires', 'America/Argentina/Buenos_Aires'],
    'Nepal' => ['Asia/Katmandu', 'Asia/Kathmandu'],
    'Myanmar' => ['Asia/Rangoon', 'Asia/Yangon'],
]);

test('a zone with no place behind it is refused', function (string $zone) {
    expect(TimezoneHelper::currentName($zone))->toBeNull();
})->with([
    'a fixed offset' => 'Etc/GMT+5',
    'an unknown zone' => 'Mars/Base',
    'nothing' => '',
]);
