<?php

declare(strict_types=1);

use App\Models\PostModel;

test('STATUSES contains the public lifecycle values and the moderation hide', function () {
    expect(PostModel::STATUSES)->toBe(['draft', 'pending', 'scheduled', 'published', 'archived', 'moderated']);
});

test('no editorial transition leads into or out of a moderation hide', function () {
    $targets = array_merge(...array_values(PostModel::STATUS_TRANSITIONS));

    expect(PostModel::STATUS_TRANSITIONS)->not->toHaveKey('moderated')
        ->and($targets)->not->toContain('moderated');
});

test('STATUSES does not contain legacy values', function () {
    $legacy = ['pending_review', 'approved', 'rejected'];
    foreach ($legacy as $value) {
        expect(PostModel::STATUSES)->not->toContain($value);
    }
});

test('WORKFLOW_STATES contains only pipeline values', function () {
    expect(PostModel::WORKFLOW_STATES)->toBe(['draft', 'in_review', 'needs_changes', 'approved']);
});

test('WORKFLOW_STATES does not contain dead values', function () {
    expect(PostModel::WORKFLOW_STATES)
        ->not->toContain('idea')
        ->not->toContain('ready_to_publish');
});

test('STATUS_TRANSITIONS does not reference legacy values', function () {
    $allTransitions = array_merge(
        array_keys(PostModel::STATUS_TRANSITIONS),
        ...array_values(PostModel::STATUS_TRANSITIONS)
    );
    foreach (['pending_review', 'rejected', 'approved'] as $legacy) {
        expect($allTransitions)->not->toContain($legacy);
    }
});

test('STATUS_TRANSITIONS only references known statuses', function () {
    $allTransitions = array_merge(
        array_keys(PostModel::STATUS_TRANSITIONS),
        ...array_values(PostModel::STATUS_TRANSITIONS)
    );
    foreach ($allTransitions as $status) {
        expect(PostModel::STATUSES)->toContain($status);
    }
});
