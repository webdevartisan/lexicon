<?php

declare(strict_types=1);

use App\Presenters\ActivityPresenter;

test('a code that reads badly as words gets its written label', function () {
    expect(ActivityPresenter::label('auth.login'))->toBe('Signed in')
        ->and(ActivityPresenter::label('transition_workflow'))->toBe('Review state changed')
        ->and(ActivityPresenter::label('moderation.reporter_warned'))->toBe('Warned about unfounded reports');
});

test('any other code is turned into words', function () {
    expect(ActivityPresenter::label('blog.invite_sent'))->toBe('Blog invite sent')
        ->and(ActivityPresenter::label('user.sessions_ended'))->toBe('User sessions ended');
});

test('a target names the record type and its id', function () {
    expect(ActivityPresenter::target('blog_invitation', 240))->toBe('Blog invitation #240')
        ->and(ActivityPresenter::target('post', '18'))->toBe('Post #18');
});

test('a target without a real id or type says only what it can', function () {
    expect(ActivityPresenter::target('user', 0))->toBe('User')
        ->and(ActivityPresenter::target('system', null))->toBe('System')
        ->and(ActivityPresenter::target(null, null))->toBe('');
});
