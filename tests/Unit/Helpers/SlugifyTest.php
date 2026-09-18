<?php

declare(strict_types=1);

const SLUG_RULE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

test('slugify builds a valid slug from awkward names', function (string $name, string $slug) {
    expect(slugify($name))->toBe($slug)
        ->and(slugify($name))->toMatch(SLUG_RULE);
})->with([
    'leading and trailing hyphens' => ['------hello-------', 'hello'],
    'leading and trailing punctuation' => ['!!!Hello, World???', 'hello-world'],
    'consecutive specials' => ['foo---__..bar', 'foo-bar'],
    'accents' => ['Café Society', 'cafe-society'],
    'letters that do not decompose' => ['Straße Ærø Łódź', 'strasse-aero-lodz'],
    'numbers kept' => ['Top 10 Tips', 'top-10-tips'],
]);

test('slugify returns an empty string when nothing usable is left', function (string $name) {
    expect(slugify($name))->toBe('');
})->with(['🎉🎉', 'Καλημέρα κόσμε', '-----', '   ', '!!!']);
