<?php

declare(strict_types=1);

use App\ValueObjects\TrafficRange;

/**
 * The Traffic page's date range comes from the query string, so every way of
 * getting it wrong has to land on the default and say so.
 */
function todayIn(string $zone): string
{
    return (new DateTimeImmutable('today', new DateTimeZone($zone)))->format('Y-m-d');
}

test('each preset covers its number of days ending today', function (string $preset, int $days) {
    $range = TrafficRange::fromQuery(['range' => $preset], 'Europe/Athens');

    expect($range->preset)->toBe($preset)
        ->and($range->days())->toBe($days)
        ->and($range->toDate())->toBe(todayIn('Europe/Athens'))
        ->and($range->rejected)->toBeFalse();
})->with([['7d', 7], ['30d', 30], ['90d', 90], ['12m', 365]]);

test('no range at all is the default without a complaint', function () {
    $range = TrafficRange::fromQuery([], 'UTC');

    expect($range->preset)->toBe(TrafficRange::DEFAULT)
        ->and($range->rejected)->toBeFalse();
});

test('a valid custom range is taken as asked', function () {
    $range = TrafficRange::fromQuery(['range' => 'custom', 'from' => '2026-01-10', 'to' => '2026-01-20'], 'UTC');

    expect($range->preset)->toBe('custom')
        ->and($range->fromDate())->toBe('2026-01-10')
        ->and($range->days())->toBe(11)
        ->and($range->query())->toBe(['range' => 'custom', 'from' => '2026-01-10', 'to' => '2026-01-20']);
});

test('a bad range falls back to the default and is flagged', function (array $query) {
    $range = TrafficRange::fromQuery($query, 'UTC');

    expect($range->preset)->toBe(TrafficRange::DEFAULT)
        ->and($range->rejected)->toBeTrue();
})->with([
    'unknown preset' => [['range' => 'forever']],
    'array instead of text' => [['range' => ['7d']]],
    'rolled-over date' => [['range' => 'custom', 'from' => '2026-02-31', 'to' => '2026-03-05']],
    'not a date' => [['range' => 'custom', 'from' => "2026-01-01' OR 1=1", 'to' => '2026-01-05']],
    'start after end' => [['range' => 'custom', 'from' => '2026-03-05', 'to' => '2026-03-01']],
    'longer than a year' => [['range' => 'custom', 'from' => '2024-01-01', 'to' => '2025-06-01']],
    'ends in the future' => [['range' => 'custom', 'from' => '2026-01-01', 'to' => '2999-01-01']],
    'missing end' => [['range' => 'custom', 'from' => '2026-01-01']],
]);

test('the comparison period is the same length immediately before', function () {
    $range = TrafficRange::fromQuery(['range' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-10'], 'UTC');
    $previous = $range->previous();

    expect($previous->fromDate())->toBe('2026-02-19')
        ->and($previous->toDate())->toBe('2026-02-28')
        ->and($previous->days())->toBe(10);
});

test('dates lists every day oldest first', function () {
    $range = TrafficRange::fromQuery(['range' => 'custom', 'from' => '2026-03-30', 'to' => '2026-04-02'], 'UTC');

    expect($range->dates())->toBe(['2026-03-30', '2026-03-31', '2026-04-01', '2026-04-02']);
});

test('calendar ranges follow the calendar', function (string $preset, string $from, string $to) {
    $range = TrafficRange::fromQuery(['range' => $preset], 'UTC');
    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));

    expect($range->fromDate())->toBe($today->modify($from)->format('Y-m-d'))
        ->and($range->toDate())->toBe($today->modify($to)->format('Y-m-d'))
        ->and($range->rejected)->toBeFalse();
})->with([
    'today' => ['today', 'today', 'today'],
    'yesterday' => ['yesterday', '-1 day', '-1 day'],
    'this month' => ['month', 'first day of this month', 'today'],
    'last month' => ['last_month', 'first day of last month', 'last day of last month'],
]);

test('the comparison can be the same days a year earlier, and links keep it', function () {
    $range = TrafficRange::fromQuery(['range' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-10', 'compare' => 'year'], 'UTC');
    $previous = $range->previous();

    expect([$previous->fromDate(), $previous->toDate()])->toBe(['2025-03-01', '2025-03-10'])
        ->and($range->query())->toBe(['range' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-10', 'compare' => 'year'])
        ->and($range->key())->toBe('2026-03-01:2026-03-10:year');
});

test('an unknown comparison falls back to the days before', function () {
    expect(TrafficRange::fromQuery(['range' => '7d', 'compare' => 'decade'], 'UTC')->compare)->toBe(TrafficRange::COMPARE_PREVIOUS);
});

test('a range is inside raw retention only when its first day still is', function () {
    $today = new DateTimeImmutable('2026-03-31', new DateTimeZone('UTC'));
    $range = TrafficRange::fromQuery(['range' => 'custom', 'from' => '2026-03-02', 'to' => '2026-03-31'], 'UTC');
    $older = TrafficRange::fromQuery(['range' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-31'], 'UTC');

    expect($range->withinRaw(30, $today))->toBeTrue()
        ->and($older->withinRaw(30, $today))->toBeFalse();
});
