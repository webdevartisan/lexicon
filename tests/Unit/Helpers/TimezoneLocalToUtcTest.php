<?php

declare(strict_types=1);

use App\Helpers\TimezoneHelper;

test('a date typed in the writer timezone is stored in UTC', function () {
    expect(TimezoneHelper::localToUtc('25.12.26 09:30', 'Europe/Athens'))->toBe('2026-12-25 07:30:00')
        ->and(TimezoneHelper::localToUtc('25.12.26 09:30', 'UTC'))->toBe('2026-12-25 09:30:00');
});

test('dates that do not exist are refused instead of rolling over', function (string $value) {
    expect(TimezoneHelper::localToUtc($value, 'UTC'))->toBeNull();
})->with(['31.02.26 10:00', '18.09.26 25:99', 'tomorrow', '2026-12-25 09:30', '']);

test('an unknown timezone is refused', function () {
    expect(TimezoneHelper::localToUtc('25.12.26 09:30', 'Mars/Base'))->toBeNull();
});
