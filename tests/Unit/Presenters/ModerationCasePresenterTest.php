<?php

declare(strict_types=1);

use App\Presenters\ModerationCasePresenter;

/**
 * What the queue says about a reported item: deletion is read from the live
 * row, since deleting a post or comment never goes through the case.
 */
function presentableCase(array $overrides = []): array
{
    return array_merge([
        'subject_type' => 'comment',
        'subject_id' => 7,
        'status' => 'in_review',
        'content_status' => 'visible',
        'live_id' => 7,
        'comment_deleted_at' => null,
        'blog_slug' => 'field notes',
        'post_slug' => 'first-light',
    ], $overrides);
}

it('links a comment to its place in the post, with slugs encoded', function () {
    $case = ModerationCasePresenter::present(presentableCase());

    expect($case['public_url'])->toBe('/blog/field%20notes/first-light#comment-7')
        ->and($case['content_state'])->toBe('visible')
        ->and($case['status_label'])->toBe('In review');
});

it('shows moderation-hidden content as hidden', function () {
    expect(ModerationCasePresenter::present(presentableCase(['content_status' => 'hidden']))['content_label'])->toBe('Hidden');
});

it('treats a missing row or a soft-deleted comment as deleted and gives no link', function (array $overrides) {
    $case = ModerationCasePresenter::present(presentableCase($overrides));

    expect($case['content_state'])->toBe('deleted')
        ->and($case['public_url'])->toBeNull();
})->with([
    'row gone' => [['live_id' => null]],
    'comment removed' => [['comment_deleted_at' => '2026-09-21 10:00:00']],
]);

it('gives no link when the post has no blog to live under', function () {
    expect(ModerationCasePresenter::present(presentableCase(['subject_type' => 'post', 'blog_slug' => null]))['public_url'])->toBeNull();
});
