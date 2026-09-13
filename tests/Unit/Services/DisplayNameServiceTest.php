<?php

declare(strict_types=1);

use App\Models\UserModel;
use App\Models\UserPreferencesModel;
use App\Models\UserProfileModel;
use App\Services\DisplayNameService;

/**
 * One person must not read as two. Hiding the name falls back to the handle,
 * never to `users.username`, because the two are different strings as soon as
 * somebody picks a profile slug - which is how "admin" ended up displayed
 * beside "@theboss".
 */
beforeEach(function () {
    $this->service = new DisplayNameService(
        Mockery::mock(UserModel::class),
        Mockery::mock(UserPreferencesModel::class),
        Mockery::mock(UserProfileModel::class),
    );
});

afterEach(function () {
    Mockery::close();
});

test('the handle is the slug when there is one', function () {
    expect($this->service->handle('theboss', 'admin'))->toBe('theboss');
});

test('the handle falls back to the username when no slug is set', function () {
    expect($this->service->handle(null, 'admin'))->toBe('admin')
        ->and($this->service->handle('', 'admin'))->toBe('admin');
});

test('showing the name gives the full name', function () {
    expect($this->service->compute('name', 'Ada', 'Lovelace', 'theboss'))->toBe('Ada Lovelace');
});

test('showing the name falls back to the handle when the name is blank', function () {
    expect($this->service->compute('name', '', '', 'theboss'))->toBe('theboss');
});

test('hiding the name gives the handle, not the username', function () {
    // The regression: this used to return the raw username, so a reader saw
    // "admin" as the name and "@theboss" as the tag on the same comment.
    expect($this->service->compute('username', 'Ada', 'Lovelace', 'theboss'))->toBe('theboss');
});

test('hiding the name with no slug still gives the username', function () {
    expect($this->service->compute('username', 'Ada', 'Lovelace', $this->service->handle(null, 'admin')))
        ->toBe('admin');
});
