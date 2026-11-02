<?php

declare(strict_types=1);

/**
 * The Overview and the review page show a post's status and review state as
 * words. A value without a label prints its translation key instead, so every
 * value the posts table allows needs one. LocaleParityTest carries the keys
 * into the other languages.
 */
function postsColumnValues(string $column): array
{
    $schema = (string) file_get_contents(ROOT_PATH.'/database/schema.sql');
    preg_match('/CREATE TABLE IF NOT EXISTS posts \((.*?)\n\s*\) ENGINE/s', $schema, $table);
    preg_match('/^\s*'.$column.' ENUM\(([^)]*)\)/m', $table[1] ?? '', $enum);
    preg_match_all("/'([a-z_]+)'/", $enum[1] ?? '', $values);

    return $values[1];
}

function englishLabels(string $path): array
{
    $labels = json_decode((string) file_get_contents(ROOT_PATH.'/locales/en.json'), true);
    foreach (explode('.', $path) as $key) {
        $labels = $labels[$key] ?? [];
    }

    return array_keys($labels);
}

test('every post status has a label', function () {
    $statuses = postsColumnValues('status');

    expect($statuses)->toContain('draft', 'pending', 'published')
        ->and(array_diff($statuses, englishLabels('components.postCard.status')))->toBe([]);
});

test('every review state has a label', function () {
    $states = postsColumnValues('workflow_state');

    expect($states)->toContain('draft', 'in_review')
        ->and(array_diff($states, englishLabels('dashboard.reviewQueue.states')))->toBe([]);
});
