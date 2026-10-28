<?php

declare(strict_types=1);

test('a written search title is used as it is', function () {
    expect(post_search_title(['title' => 'Notes', 'meta_title' => '  Field notes from May '], 'Walks'))->toBe('Field notes from May');
});

test('an empty search title becomes the post title and the blog name', function () {
    expect(post_search_title(['title' => 'Notes', 'meta_title' => '   '], 'Walks'))->toBe('Notes'.search_title_suffix('Walks'))
        ->and(post_search_title(['title' => 'Notes'], 'Walks'))->toBe('Notes'.search_title_suffix('Walks'));
});
