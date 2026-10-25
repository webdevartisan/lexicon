<?php

declare(strict_types=1);

use App\Presenters\PostActionPresenter;

test('a publisher on a submission in review approves and publishes in one step', function () {
    $primary = PostActionPresenter::for('pending', true, true, false, 'in_review')['primary'];

    expect($primary['intent'])->toBe('publish')
        ->and($primary['label'])->toBe('Approve & publish');
});

test('a publisher on an approved submission just publishes it', function () {
    $primary = PostActionPresenter::for('pending', true, true, false, 'approved')['primary'];

    expect($primary['intent'])->toBe('publish')
        ->and($primary['label'])->toBe('Publish');
});

test('a publisher on a dated submission schedules it', function () {
    $primary = PostActionPresenter::for('pending', true, true, true, 'in_review')['primary'];

    expect($primary['intent'])->toBe('schedule');
});

test('a writer sent back for changes resubmits', function () {
    $actions = PostActionPresenter::for('pending', false, true, false, 'needs_changes');

    expect($actions['primary']['label'])->toBe('Resubmit for review')
        ->and($actions['secondary']['label'])->toBe('Withdraw to draft');
});

test('a publisher on an archived post restores it as a draft, with publishing and saving as it is in the menu', function () {
    $actions = PostActionPresenter::for('archived', true, false);

    expect($actions['primary']['intent'])->toBe('save_draft')
        ->and($actions['primary']['label'])->toBe('Restore as draft')
        ->and($actions['secondary'])->toBeNull()
        ->and(array_column($actions['menu'], 'intent'))->toBe(['publish', 'update'])
        ->and(array_column($actions['menu'], 'label'))->toBe(['Publish', 'Save, keep archived']);
});

test('someone who cannot publish only saves an archived post', function () {
    $actions = PostActionPresenter::for('archived', false, true);

    expect($actions['primary']['intent'])->toBe('update')
        ->and($actions['menu'])->toBe([]);
});
