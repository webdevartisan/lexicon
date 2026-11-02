<?php

declare(strict_types=1);

use App\Helpers\TimezoneHelper;

test('a date picked in the writer zone is stored in UTC', function () {
    expect(TimezoneHelper::localToUtc('2026-12-25 09:30:00', 'Europe/Athens'))->toBe('2026-12-25 07:30:00')
        ->and(TimezoneHelper::localToUtc('2026-12-25 09:30:42', 'UTC'))->toBe('2026-12-25 09:30:42');
});

test('the date the edit page shows comes back unchanged', function () {
    $shown = TimezoneHelper::utcToLocal('2026-10-01 07:15:42', 'Asia/Nicosia');

    expect($shown)->toBe('2026-10-01 10:15:42')
        ->and(TimezoneHelper::localToUtc($shown, 'Asia/Nicosia'))->toBe('2026-10-01 07:15:42');
});

test('a post with no date shows nothing', function () {
    expect(TimezoneHelper::utcToLocal(null, 'Asia/Nicosia'))->toBe('');
});

test('an untouched date in the hour the clocks repeat keeps its moment', function () {
    // 03:30 happens twice in Nicosia that night, at 00:30 and again at 01:30 UTC.
    $shown = TimezoneHelper::utcToLocal('2027-10-31 00:30:00', 'Asia/Nicosia');

    expect($shown)->toBe('2027-10-31 03:30:00')
        ->and(TimezoneHelper::localToUtc($shown, 'Asia/Nicosia', '2027-10-31 00:30:00'))->toBe('2027-10-31 00:30:00')
        ->and(TimezoneHelper::localToUtc($shown, 'Asia/Nicosia'))->toBe('2027-10-31 01:30:00');
});

test('a changed date is read as picked, whatever was stored before', function () {
    expect(TimezoneHelper::localToUtc('2026-12-25 09:30:00', 'Europe/Athens', '2026-10-01 07:15:42'))->toBe('2026-12-25 07:30:00');
});

test('a time the clocks skip lands an hour later', function () {
    expect(TimezoneHelper::localToUtc('2027-03-28 03:30:00', 'Asia/Nicosia'))->toBe('2027-03-28 01:30:00');
});

test('anything else is refused instead of guessed at', function (string $value) {
    expect(TimezoneHelper::localToUtc($value, 'UTC'))->toBeNull();
})->with([
    'the old field format' => '20.11.26 10:00',
    'a moment with a zone marker' => '2026-11-20T10:00:00.000Z',
    'no seconds' => '2026-11-20 10:00',
    'a day that does not exist' => '2026-02-31 10:00:00',
    'an hour that does not exist' => '2026-11-20 25:99:00',
    'a word' => 'tomorrow',
    'nothing' => '',
]);

test('an unknown zone is refused', function () {
    expect(TimezoneHelper::localToUtc('2026-12-25 09:30:00', 'Mars/Base'))->toBeNull();
});

test('a zone is named the way people write it', function () {
    expect(TimezoneHelper::label('America/New_York'))->toBe('America/New York');
});
