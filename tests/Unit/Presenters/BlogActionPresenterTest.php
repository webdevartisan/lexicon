<?php

declare(strict_types=1);

use App\Presenters\BlogActionPresenter;
use App\Presenters\PostActionPresenter;

test('a draft blog offers publish first and saving as a draft beside it', function () {
    $actions = BlogActionPresenter::for('draft');

    expect($actions['primary']['intent'])->toBe('publish')
        ->and($actions['secondary']['intent'])->toBe('save_draft')
        ->and(array_column($actions['menu'], 'intent'))->toBe(['archive'])
        ->and($actions['pill']['label'])->toBe('Draft');
});

test('a published blog saves with update and keeps unpublish and archive in the menu', function () {
    $actions = BlogActionPresenter::for('published');

    expect($actions['primary']['intent'])->toBe('update')
        ->and($actions['secondary'])->toBeNull()
        ->and(array_column($actions['menu'], 'intent'))->toBe(['unpublish', 'archive']);
});

test('an archived blog is restored as a draft, with publishing and saving as it is in the menu', function () {
    $actions = BlogActionPresenter::for('archived');

    expect($actions['primary']['intent'])->toBe('save_draft')
        ->and($actions['primary']['label'])->toBe('Restore as draft')
        ->and(array_column($actions['menu'], 'intent'))->toBe(['publish', 'update'])
        ->and(array_column($actions['menu'], 'label'))->toBe(['Publish', 'Save, keep archived']);
});

test('intents map to statuses and anything else keeps the current one', function (?string $intent, string $current, string $expected) {
    expect(BlogActionPresenter::statusForIntent($intent, $current))->toBe($expected);
})->with([
    ['publish', 'draft', 'published'],
    ['save_draft', 'published', 'draft'],
    ['unpublish', 'published', 'draft'],
    ['archive', 'published', 'archived'],
    ['update', 'archived', 'archived'],
    [null, 'published', 'published'],
    ['delete_everything', 'draft', 'draft'],
]);

test('it returns the same shape as the post presenter so both share one action bar', function () {
    $blog = BlogActionPresenter::for('draft');
    $post = PostActionPresenter::for('draft', true, false);

    expect(array_keys($blog))->toBe(array_keys($post))
        ->and($post['primary'])->toHaveKeys(array_keys($blog['primary']));
});

test('an archived blog and an archived post offer the same buttons', function () {
    $blog = BlogActionPresenter::for('archived');
    $post = PostActionPresenter::for('archived', true, false);

    expect($blog['primary'])->toBe($post['primary'])
        ->and($blog['menu'])->toBe($post['menu']);
});
