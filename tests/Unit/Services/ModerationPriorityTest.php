<?php

declare(strict_types=1);

use App\Services\ModerationPriority;

/**
 * The queue order: severity first, then the capped signals within a band.
 */
it('ranks one critical report above a dozen spam reports', function () {
    $critical = ModerationPriority::score('critical', 1, 0, 1, 0, false);
    $spam = ModerationPriority::score('low', 12, 0, 12, 0, false);

    expect($critical)->toBeGreaterThan($spam);
});

it('adds weight for counted reports, a recent burst, a track record, and a waiting proposal', function () {
    $base = ModerationPriority::score('medium', 1, 0, 1, 0, false);

    expect(ModerationPriority::score('medium', 2, 0, 1, 0, false))->toBe($base + 5)
        ->and(ModerationPriority::score('medium', 1, 0, 3, 0, false))->toBe($base + 10)
        ->and(ModerationPriority::score('medium', 1, 0, 1, 1, false))->toBe($base + 15)
        ->and(ModerationPriority::score('medium', 1, 0, 1, 0, true))->toBe($base + 20);
});

it('caps every signal so no amount of reporting lifts a case into the next band', function () {
    $everySignalMaxed = fn (string $severity) => ModerationPriority::score($severity, 500, 500, 500, 50, true);
    $bare = fn (string $severity) => ModerationPriority::score($severity, 0, 0, 0, 0, false);

    expect(ModerationPriority::score('low', 500, 0, 1, 0, false))->toBe(ModerationPriority::score('low', 10, 0, 1, 0, false))
        ->and($everySignalMaxed('low'))->toBeLessThan($bare('medium'))
        ->and($everySignalMaxed('medium'))->toBeLessThan($bare('high'))
        ->and($everySignalMaxed('high'))->toBeLessThan($bare('critical'));
});

it('treats a case with no known severity as low', function () {
    expect(ModerationPriority::score(null, 0, 1, 1, 0, false))->toBe(ModerationPriority::score('low', 0, 1, 1, 0, false));
});
