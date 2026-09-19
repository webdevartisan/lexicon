<?php

declare(strict_types=1);

test('a written excerpt is used as it is, without markup', function () {
    expect(post_excerpt(['excerpt' => '  Written <em>by hand</em> ', 'content' => '<p>Body</p>']))->toBe('Written by hand');
});

test('an empty excerpt falls back to the opening of the content', function () {
    $post = ['excerpt' => '', 'content' => '<p>First para.</p><p>Second &amp; <b>bold</b> one.</p>'];

    expect(post_excerpt($post))->toBe('First para. Second & bold one.');
});

test('a missing excerpt behaves like an empty one', function () {
    expect(post_excerpt(['excerpt' => null, 'content' => '<p>Only the body</p>']))->toBe('Only the body');
});

test('long text is cut on a word boundary with an ellipsis', function () {
    $summary = post_excerpt(['content' => str_repeat('word ', 80)], 40);

    expect($summary)->toBe('word word word word word word word word…')
        ->and(mb_strlen($summary))->toBeLessThanOrEqual(41);
});

test('multibyte text is measured in characters, not bytes', function () {
    $greek = str_repeat('λέξη ', 10);

    expect(post_excerpt(['content' => $greek], 100))->toBe(trim($greek));
});

test('a post with no text at all gives an empty summary', function () {
    expect(post_excerpt([]))->toBe('')
        ->and(post_excerpt(['content' => '<img src="/uploads/a.png">']))->toBe('');
});
