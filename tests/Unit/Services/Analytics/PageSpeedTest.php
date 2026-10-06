<?php

declare(strict_types=1);

use App\Services\Analytics\PageSpeed;

test('a measure is good up to its first line, poor past its second, and needs work between', function (string $column, float $value, string $band) {
    expect(PageSpeed::band($column, $value))->toBe($band);
})->with([
    'LCP right on the good line' => ['lcp_ms', 2500, 'good'],
    'LCP between the lines' => ['lcp_ms', 3200, 'improve'],
    'LCP right on the poor line' => ['lcp_ms', 4000, 'improve'],
    'LCP past it' => ['lcp_ms', 4001, 'poor'],
    'INP' => ['inp_ms', 180, 'good'],
    'CLS' => ['cls', 0.3, 'poor'],
    'TTFB' => ['ttfb_ms', 900, 'improve'],
]);

test('a measure without bands is a mistake, not a band', function () {
    PageSpeed::band('fcp_ms', 100);
})->throws(InvalidArgumentException::class);
