<?php

declare(strict_types=1);

function structuredDataOf(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*)</script>#s', $html, $found);

    return json_decode($found[1], true);
}

test('a post is described as a blog posting', function () {
    $data = structuredDataOf(structured_data_tags([
        'og_type' => 'article',
        'og_title' => 'A quiet morning',
        'og_description' => 'Notes on waking early.',
        'canonical' => 'https://example.test/en/blog/b/a-quiet-morning',
        'article_published_time' => '2026-07-20T10:40:06+00:00',
        'article_author' => 'Sam',
        'site_name' => 'Slow Notes',
    ]));

    expect($data['@type'])->toBe('BlogPosting')
        ->and($data['headline'])->toBe('A quiet morning')
        ->and($data['author']['name'])->toBe('Sam')
        ->and($data['publisher']['name'])->toBe('Slow Notes')
        ->and($data['dateModified'])->toBe('2026-07-20T10:40:06+00:00')
        ->and($data['mainEntityOfPage']['@id'])->toBe('https://example.test/en/blog/b/a-quiet-morning');
});

test('a blog home is described as a blog', function () {
    $data = structuredDataOf(structured_data_tags([
        'og_type' => 'website',
        'title' => 'Slow Notes',
        'site_name' => 'Slow Notes',
        'canonical' => 'https://example.test/en/blog/b',
    ]));

    expect($data['@type'])->toBe('Blog')
        ->and($data['name'])->toBe('Slow Notes')
        ->and($data)->not->toHaveKey('description');
});

test('a page kept out of search engines gets no structured data', function () {
    expect(structured_data_tags([
        'og_type' => 'article',
        'canonical' => 'https://example.test/en/blog/b/p',
        'robots' => 'noindex',
    ]))->toBe('');
});

test('a closing script tag in a title cannot end the block', function () {
    $html = structured_data_tags([
        'og_type' => 'article',
        'og_title' => 'Why </script> matters',
        'canonical' => 'https://example.test/en/blog/b/p',
    ]);

    expect(substr_count($html, '</script>'))->toBe(1)
        ->and(structuredDataOf($html)['headline'])->toBe('Why </script> matters');
});
