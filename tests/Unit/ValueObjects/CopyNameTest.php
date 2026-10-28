<?php

declare(strict_types=1);

use App\ValueObjects\CopyName;

/**
 * A copy is named after its original with the first free "(copy)" number, and
 * copying a copy counts on from the original instead of stacking marks.
 */
test('the first copy is marked as a copy', function () {
    expect(CopyName::title('Field notes', []))->toBe('Field notes (copy)');
});

test('a taken copy title moves on to the next number', function () {
    expect(CopyName::title('Field notes', ['Field notes', 'field notes (COPY)', 'Field notes (copy 2)']))
        ->toBe('Field notes (copy 3)');
});

test('copying a copy starts from the original title', function () {
    expect(CopyName::title('Field notes (copy 2)', ['Field notes (copy)', 'Field notes (copy 2)']))
        ->toBe('Field notes (copy 3)');
});

test('a long title is shortened so the mark still fits', function () {
    $title = CopyName::title(str_repeat('a', 100), []);

    expect(mb_strlen($title))->toBe(100)
        ->and($title)->toEndWith(' (copy)');
});

test('the address gets one copy ending, whatever the original ended with', function (string $slug, string $expected) {
    expect(CopyName::slug($slug))->toBe($expected);
})->with([
    'plain' => ['field-notes', 'field-notes-copy'],
    'already a copy' => ['field-notes-copy', 'field-notes-copy'],
    'numbered copy' => ['field-notes-copy-4', 'field-notes-copy'],
]);

test('a long address is shortened before the ending without leaving a hyphen behind', function () {
    $slug = CopyName::slug(str_repeat('ab-', 40), 100);

    expect(strlen($slug))->toBeLessThanOrEqual(100)
        ->and($slug)->toEndWith('-copy')
        ->and($slug)->not->toContain('--');
});
