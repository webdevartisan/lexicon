<?php

declare(strict_types=1);

use App\Models\TrafficSql;
use App\Services\Traffic\TrafficReportService;
use App\ValueObjects\TrafficScope;

test('a blog is rising at half again its earlier views, from 50 views', function (int $now, int $before, bool $rising) {
    expect(TrafficReportService::isRising($now, $before))->toBe($rising);
})->with([
    'grew by half' => [150, 100, true],
    'grew a little' => [120, 100, false],
    'too small to say' => [40, 10, false],
    'new and busy' => [100, 0, true],
    'new and quiet' => [60, 0, false],
]);

test('a spike is three times the usual day, from 50 views', function (int $today, float $usual, bool $spike) {
    expect(TrafficReportService::isSpike($today, $usual))->toBe($spike);
})->with([
    'three times' => [300, 100.0, true],
    'twice' => [200, 100.0, false],
    'a quiet blog waking up' => [60, 4.0, true],
    'busy but small' => [30, 1.0, false],
]);

test('filters are kept only for breakdowns the scope has and a row can be narrowed to', function () {
    $blog = TrafficScope::blog(5);
    $site = TrafficScope::site();
    $asked = ['source' => 'Google', 'category' => '3', 'hour' => '9', 'country' => TrafficReportService::OTHER, 'device' => ''];

    expect(TrafficReportService::filtersFrom($asked, $blog))->toBe(['source' => 'Google', 'category' => '3'])
        ->and(TrafficReportService::filtersFrom($asked, $site))->toBe(['source' => 'Google'])
        ->and(TrafficReportService::filtersFrom('source=Google', $blog))->toBe([]);
});

test('narrowing by a post attribute is a subquery, so no view counts twice', function () {
    expect(TrafficSql::match(TrafficScope::BLOG, 'tag'))->toContain('IN (SELECT post_id FROM post_tags')
        ->and(TrafficSql::match(TrafficScope::SITE, 'channel'))->toBe("IF(h.channel = 'lexicon', 'internal', h.channel) = ?")
        ->and(TrafficSql::match(TrafficScope::BLOG, 'source'))->toContain("h.channel <> 'lexicon'");
});

test('a breakdown that is not a row of views cannot be a filter', function () {
    TrafficSql::match(TrafficScope::BLOG, 'exit');
})->throws(InvalidArgumentException::class);
