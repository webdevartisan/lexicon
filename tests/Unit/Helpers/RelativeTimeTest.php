<?php

declare(strict_types=1);

/**
 * relative_time() under a minute used to count seconds, so a comment posted a
 * moment ago read "0 seconds ago". Within a minute it now says so in words.
 */
$secondsFromNow = static fn (int $offset): string => gmdate('Y-m-d H:i:s', time() + $offset);

test('something from the last minute happened just now', function () use ($secondsFromNow) {
    expect(relative_time($secondsFromNow(0)))->toBe('just now')
        ->and(relative_time($secondsFromNow(-45)))->toBe('just now')
        ->and(relative_time($secondsFromNow(-45), true))->toBe('now');
});

test('something due within a minute is in under a minute', function () use ($secondsFromNow) {
    expect(relative_time($secondsFromNow(30)))->toBe('in under a minute')
        ->and(relative_time($secondsFromNow(30), true))->toBe('in <1m');
});

test('past a minute it counts minutes again', function () use ($secondsFromNow) {
    expect(relative_time($secondsFromNow(-120)))->toBe('2 minutes ago')
        ->and(relative_time($secondsFromNow(-120), true))->toBe('2m ago');
});
