<?php

declare(strict_types=1);

use App\Presenters\AnalyticsPresenter;
use App\Services\LocaleRegistry;

function analyticsPresenter(string $scope): AnalyticsPresenter
{
    return new AnalyticsPresenter('en', static fn (string $key, array $values = []): string => $key, LocaleRegistry::instance(), $scope);
}

test('moving around and arriving from elsewhere on Lexicon read right on each page', function (string $scope, string $channel, string $key) {
    expect(analyticsPresenter($scope)->label('channel', $channel))->toBe($key);
})->with([
    'a blog, inside it' => ['blog', 'internal', 'analytics.channels.internal'],
    'a blog, from elsewhere on Lexicon' => ['blog', 'lexicon', 'analytics.channels.lexicon'],
    'a post, inside its blog' => ['post', 'internal', 'analytics.channels.internal'],
    'the whole site' => ['site', 'internal', 'analytics.channels.withinSite'],
    'platform pages, between them' => ['platform', 'internal', 'analytics.channels.platformPages'],
    'platform pages, from a blog' => ['platform', 'lexicon', 'analytics.channels.blogs'],
    'an outside channel' => ['platform', 'search', 'analytics.channels.search'],
]);

test('a place on Lexicon is named by its blog when there is one, otherwise by its kind', function () {
    $present = analyticsPresenter('blog');

    expect($present->label('lexicon', 'blog:7', 'Field Notes'))->toBe('Field Notes')
        ->and($present->label('lexicon', 'blog:7'))->toBe('analytics.lexicon.blog')
        ->and($present->label('lexicon', 'discover'))->toBe('analytics.lexicon.discover')
        ->and($present->heading('lexicon'))->toBe('analytics.breakdowns.lexicon')
        ->and(analyticsPresenter('platform')->heading('lexicon'))->toBe('analytics.breakdowns.fromBlogs');
});

test('the top source is the busiest named place, outside or on Lexicon', function () {
    $present = analyticsPresenter('blog');
    $outside = [['value' => 'Google', 'views' => 30, 'visitors' => 20]];
    $onLexicon = [['value' => 'blog:7', 'views' => 40, 'visitors' => 25, 'name' => 'Field Notes']];

    expect($present->topSource(['source' => $outside, 'lexicon' => $onLexicon]))->toBe('Field Notes')
        ->and($present->topSource(['source' => $outside, 'lexicon' => []]))->toBe('Google')
        ->and($present->topSource(['source' => [], 'lexicon' => [], 'channel' => [
            ['value' => 'internal', 'views' => 50, 'visitors' => 10],
            ['value' => 'direct', 'views' => 9, 'visitors' => 9],
        ]]))->toBe('analytics.channels.direct');
});

test('the week starts on Monday, keyed the way MySQL numbers weekdays', function () {
    expect(analyticsPresenter('blog')->weekdays())->toBe([2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat', 1 => 'Sun']);
});

test('a chart point is named for its day, week or month', function () {
    $present = analyticsPresenter('blog');

    expect($present->point('2026-03-09', 'day'))->toBe('Mar 9, 2026')
        ->and($present->point('2026-03-09', 'week'))->toBe('analytics.chart.weekOf')
        ->and($present->point('2026-03-01', 'month'))->toBe('March 2026')
        ->and($present->hour(7))->toBe('07:00');
});

test('category, tag and author rows show their names', function () {
    expect(analyticsPresenter('blog')->label('category', '3', 'Field notes'))->toBe('Field notes')
        ->and(analyticsPresenter('blog')->label('author', '9'))->toBe('analytics.breakdowns.unknown');
});

test('the overview line reads the views card and the top source, and stays quiet with no views', function () {
    $present = new AnalyticsPresenter('en', static fn (string $key, array $values = []): string => $key.'('.implode(', ', $values).')', LocaleRegistry::instance(), 'blog');
    $views = static fn (int $value, ?float $change): array => ['views' => ['value' => $value, 'previous' => 1000, 'change' => $change]];
    $google = ['source' => [['value' => 'Google', 'views' => 30, 'visitors' => 20]]];

    expect($present->summary($views(1200, 0.2), $google, 'last week'))->toBe('analytics.summary.up(1,200, 20%, last week) analytics.summary.source(Google)')
        ->and($present->summary($views(900, -0.1), [], 'last week'))->toBe('analytics.summary.down(900, 10%, last week)')
        ->and($present->summary($views(1001, 0.001), [], 'last week'))->toBe('analytics.summary.flat(1,001, 0.1%, last week)')
        ->and($present->summary($views(40, null), [], 'last week'))->toBe('analytics.summary.views(40, 0%, last week)')
        ->and($present->summary($views(0, null), $google, 'last week'))->toBeNull()
        ->and($present->summary([], $google, 'last week'))->toBeNull();
});
