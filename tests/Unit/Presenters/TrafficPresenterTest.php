<?php

declare(strict_types=1);

use App\Presenters\TrafficPresenter;
use App\Services\LocaleRegistry;

function trafficPresenter(string $scope): TrafficPresenter
{
    return new TrafficPresenter('en', static fn (string $key, array $values = []): string => $key, LocaleRegistry::instance(), $scope);
}

test('moving around and arriving from elsewhere on Lexicon read right on each page', function (string $scope, string $channel, string $key) {
    expect(trafficPresenter($scope)->label('channel', $channel))->toBe($key);
})->with([
    'a blog, inside it' => ['blog', 'internal', 'traffic.channels.internal'],
    'a blog, from elsewhere on Lexicon' => ['blog', 'lexicon', 'traffic.channels.lexicon'],
    'a post, inside its blog' => ['post', 'internal', 'traffic.channels.internal'],
    'the whole site' => ['site', 'internal', 'traffic.channels.withinSite'],
    'platform pages, between them' => ['platform', 'internal', 'traffic.channels.platformPages'],
    'platform pages, from a blog' => ['platform', 'lexicon', 'traffic.channels.blogs'],
    'an outside channel' => ['platform', 'search', 'traffic.channels.search'],
]);

test('a place on Lexicon is named by its blog when there is one, otherwise by its kind', function () {
    $present = trafficPresenter('blog');

    expect($present->label('lexicon', 'blog:7', 'Field Notes'))->toBe('Field Notes')
        ->and($present->label('lexicon', 'blog:7'))->toBe('traffic.lexicon.blog')
        ->and($present->label('lexicon', 'discover'))->toBe('traffic.lexicon.discover')
        ->and($present->heading('lexicon'))->toBe('traffic.breakdowns.lexicon')
        ->and(trafficPresenter('platform')->heading('lexicon'))->toBe('traffic.breakdowns.fromBlogs');
});

test('the top source is the busiest named place, outside or on Lexicon', function () {
    $present = trafficPresenter('blog');
    $outside = [['value' => 'Google', 'views' => 30, 'visitors' => 20]];
    $onLexicon = [['value' => 'blog:7', 'views' => 40, 'visitors' => 25, 'name' => 'Field Notes']];

    expect($present->topSource(['source' => $outside, 'lexicon' => $onLexicon]))->toBe('Field Notes')
        ->and($present->topSource(['source' => $outside, 'lexicon' => []]))->toBe('Google')
        ->and($present->topSource(['source' => [], 'lexicon' => [], 'channel' => [
            ['value' => 'internal', 'views' => 50, 'visitors' => 10],
            ['value' => 'direct', 'views' => 9, 'visitors' => 9],
        ]]))->toBe('traffic.channels.direct');
});

test('the week starts on Monday, keyed the way MySQL numbers weekdays', function () {
    expect(trafficPresenter('blog')->weekdays())->toBe([2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat', 1 => 'Sun']);
});

test('a chart point is named for its day, week or month', function () {
    $present = trafficPresenter('blog');

    expect($present->point('2026-03-09', 'day'))->toBe('Mar 9, 2026')
        ->and($present->point('2026-03-09', 'week'))->toBe('traffic.chart.weekOf')
        ->and($present->point('2026-03-01', 'month'))->toBe('March 2026')
        ->and($present->hour(7))->toBe('07:00');
});

test('category, tag and author rows show their names', function () {
    expect(trafficPresenter('blog')->label('category', '3', 'Field notes'))->toBe('Field notes')
        ->and(trafficPresenter('blog')->label('author', '9'))->toBe('traffic.breakdowns.unknown');
});
