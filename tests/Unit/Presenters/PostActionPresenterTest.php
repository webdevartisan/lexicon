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
